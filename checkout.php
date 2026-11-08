<?php
session_start();
require_once 'auth.php';
require_customer_login();
include 'db.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cartItems = [];
$cartTotal = 0.0;
if ($_SESSION['cart']) {
    $cartIdList = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
    $cartResult = $conn->query("SELECT id, name, price, image_path, quantity FROM products WHERE id IN ($cartIdList) ORDER BY name");
    while ($product = $cartResult->fetch_assoc()) {
        $quantity = min((int) $_SESSION['cart'][$product['id']], (int) $product['quantity']);
        if ($quantity < 1) {
            unset($_SESSION['cart'][$product['id']]);
            continue;
        }
        $product['cart_quantity'] = $quantity;
        $product['subtotal'] = $quantity * (float) $product['price'];
        $cartItems[] = $product;
        $cartTotal += $product['subtotal'];
    }
}

if (!$cartItems) {
    header('Location: products.php');
    exit;
}

$errors = [];
class CheckoutStockException extends RuntimeException {}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$profileLookup = $conn->prepare('SELECT full_name, municipality, province, barangay, street_address, checkout_email, contact_number FROM users WHERE id = ? LIMIT 1');
$profileUserId = (int) $_SESSION['user_id'];
$profileLookup->bind_param('i', $profileUserId);
$profileLookup->execute();
$savedProfile = $profileLookup->get_result()->fetch_assoc() ?: [];
$values = [
    'name' => $savedProfile['full_name'] ?? '',
    'municipality' => $savedProfile['municipality'] ?? '',
    'province' => $savedProfile['province'] ?? '',
    'barangay' => $savedProfile['barangay'] ?? '',
    'street_address' => $savedProfile['street_address'] ?? '',
    'email' => $savedProfile['checkout_email'] ?? '',
    'contact_number' => $savedProfile['contact_number'] ?? '',
    'payment_method' => 'cod',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) $errors[] = 'Your session expired. Refresh checkout and try again.';
    foreach ($values as $key => $default) {
        $values[$key] = trim($_POST[$key] ?? $default);
    }
    if ($values['name'] === '') $errors[] = 'Enter your name.';
    if ($values['municipality'] === '') $errors[] = 'Enter your municipality.';
    if ($values['province'] === '') $errors[] = 'Enter your province.';
    if ($values['barangay'] === '') $errors[] = 'Enter your barangay.';
    if ($values['street_address'] === '') $errors[] = 'Enter your street address.';
    if ($values['contact_number'] === '') $errors[] = 'Enter your contact number.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    foreach (['name'=>120,'municipality'=>100,'province'=>100,'barangay'=>100,'street_address'=>255,'contact_number'=>20,'email'=>150] as $field=>$limit) {
        if (strlen($values[$field]) > $limit) $errors[] = 'One or more shipping fields exceed the allowed length.';
    }
    if ($values['payment_method'] !== 'cod') $errors[] = 'Online card payments are not available yet. Choose cash on delivery.';

    if (!$errors) {
        // Card values are validated for this demo flow but are never stored or processed.
        try {
            $conn->begin_transaction();
            $userId = (int) $_SESSION['user_id'];
            $lockedProduct = $conn->prepare('SELECT name, price, image_path, quantity FROM products WHERE id = ? FOR UPDATE');
            $decrementStock = $conn->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ? AND quantity >= ?');
            $cartTotal = 0.0;
            foreach ($cartItems as $index => $item) {
                $productId = (int)$item['id'];
                $lockedProduct->bind_param('i', $productId);
                $lockedProduct->execute();
                $currentProduct = $lockedProduct->get_result()->fetch_assoc();
                $quantity = (int)$item['cart_quantity'];
                if (!$currentProduct || (int)$currentProduct['quantity'] < $quantity) throw new CheckoutStockException('Some items no longer have enough stock. Review your cart and try again.');
                $decrementStock->bind_param('iii', $quantity, $productId, $quantity);
                $decrementStock->execute();
                if ($decrementStock->affected_rows !== 1) throw new CheckoutStockException('Some items no longer have enough stock. Review your cart and try again.');
                $cartItems[$index]['name'] = $currentProduct['name'];
                $cartItems[$index]['price'] = (float)$currentProduct['price'];
                $cartItems[$index]['image_path'] = $currentProduct['image_path'];
                $cartItems[$index]['subtotal'] = $quantity * (float)$currentProduct['price'];
                $cartTotal += $cartItems[$index]['subtotal'];
            }
            $orderInsert = $conn->prepare('INSERT INTO orders (user_id, customer_name, municipality, province, barangay, street_address, customer_email, contact_number, payment_method, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $orderInsert->bind_param('issssssssd', $userId, $values['name'], $values['municipality'], $values['province'], $values['barangay'], $values['street_address'], $values['email'], $values['contact_number'], $values['payment_method'], $cartTotal);
            $orderInsert->execute();
            $orderId = (int) $orderInsert->insert_id;
            $itemInsert = $conn->prepare('INSERT INTO order_items (order_id, product_id, product_name, image_path, unit_price, quantity) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($cartItems as $item) {
                $productId = (int) $item['id'];
                $productName = $item['name'];
                $imagePath = $item['image_path'];
                $unitPrice = (float) $item['price'];
                $quantity = (int) $item['cart_quantity'];
                $itemInsert->bind_param('iissdi', $orderId, $productId, $productName, $imagePath, $unitPrice, $quantity);
                $itemInsert->execute();
            }
            $deliveryAddress = $values['street_address'] . ', ' . $values['barangay'] . ', ' . $values['municipality'] . ', ' . $values['province'];
            $saveProfile = $conn->prepare('UPDATE users SET full_name = ?, municipality = ?, province = ?, barangay = ?, street_address = ?, checkout_email = ?, email = ?, contact_number = ?, delivery_address = ? WHERE id = ?');
            $saveProfile->bind_param('sssssssssi', $values['name'], $values['municipality'], $values['province'], $values['barangay'], $values['street_address'], $values['email'], $values['email'], $values['contact_number'], $deliveryAddress, $userId);
            $saveProfile->execute();
            $conn->commit();
            $_SESSION['last_order'] = [
                'order_id' => $orderId,
                'name' => $values['name'],
                'municipality' => $values['municipality'],
                'province' => $values['province'],
                'barangay' => $values['barangay'],
                'street_address' => $values['street_address'],
                'email' => $values['email'],
                'contact_number' => $values['contact_number'],
                'payment_method' => $values['payment_method'],
                'items' => $cartItems,
                'total' => $cartTotal,
                'placed_at' => date('Y-m-d H:i:s'),
            ];
            $_SESSION['cart'] = [];
            header('Location: order-confirmation.php');
            exit;
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $errors[] = 'We could not save your order. Please try again.';
        } catch (CheckoutStockException $exception) {
            $conn->rollback();
            $errors[] = $exception->getMessage();
        }
    }
}

function checkout_h($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | PhoneWiz</title>
    <link rel="icon" type="image/png" href="PhoneWiz.png">
    <link rel="stylesheet" href="products.css?v=2">
    <link rel="stylesheet" href="simple.css?v=1">
    <link rel="stylesheet" href="cart-design.css?v=11">
</head>
<body>
    <?php include 'user-sidebar.php'; ?>
    <main class="checkout-page">
        <div class="customer-welcome">Welcome, <?php echo current_username(); ?> · <a href="logout.php">Log out</a></div>
        <a class="back-link" href="products.php">← Back to products</a>
        <header class="checkout-heading">
            <p class="page-label">PHONEWIZ CHECKOUT</p>
            <h1>Delivery and payment</h1>
            <p class="intro">Confirm your order details and choose how you would like to pay.</p>
        </header>
        <?php if ($errors) { ?><div class="checkout-errors" role="alert"><strong>Please check the following:</strong><ul><?php foreach ($errors as $error) { ?><li><?php echo checkout_h($error); ?></li><?php } ?></ul></div><?php } ?>
        <div class="checkout-layout">
            <form class="checkout-form" method="post" action="checkout.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo checkout_h($_SESSION['csrf_token']); ?>">
                <section class="checkout-section">
                    <h2>Customer information</h2>
                    <label for="customer-name">Full name</label>
                    <input id="customer-name" name="name" type="text" autocomplete="name" maxlength="120" value="<?php echo checkout_h($values['name']); ?>" required>
                    <label for="customer-municipality">Municipality</label>
                    <input id="customer-municipality" name="municipality" type="text" autocomplete="address-level2" maxlength="100" value="<?php echo checkout_h($values['municipality']); ?>" required>
                    <label for="customer-province">Province</label>
                    <input id="customer-province" name="province" type="text" autocomplete="address-level1" maxlength="100" value="<?php echo checkout_h($values['province']); ?>" required>
                    <label for="customer-barangay">Barangay</label>
                    <input id="customer-barangay" name="barangay" type="text" maxlength="100" value="<?php echo checkout_h($values['barangay']); ?>" required>
                    <label for="customer-street">Street address</label>
                    <input id="customer-street" name="street_address" type="text" autocomplete="street-address" maxlength="255" value="<?php echo checkout_h($values['street_address']); ?>" required>
                    <label for="customer-contact">Contact number</label>
                    <input id="customer-contact" name="contact_number" type="tel" autocomplete="tel" maxlength="20" value="<?php echo checkout_h($values['contact_number']); ?>" required>
                    <label for="customer-email">Email</label>
                    <input id="customer-email" name="email" type="email" autocomplete="email" maxlength="150" value="<?php echo checkout_h($values['email']); ?>" required>
                </section>
                <section class="checkout-section">
                    <h2>Payment option</h2>
                    <label class="payment-choice"><input type="radio" name="payment_method" value="cod" <?php echo $values['payment_method'] === 'cod' ? 'checked' : ''; ?>> <span><strong>Cash on delivery (COD)</strong><small>Pay when your order arrives.</small></span></label>
                    <label class="payment-choice"><input type="radio" name="payment_method" value="card" disabled> <span><strong>Credit card</strong><small>Unavailable until online payments are connected.</small></span></label>
                    <div class="card-fields" id="cardFields" <?php echo $values['payment_method'] === 'card' ? '' : 'hidden'; ?>>
                        <p class="card-demo-note">Card processing is not connected yet. Choose cash on delivery to place an order.</p>
                    </div>
                </section>
                <button class="place-order-button" type="submit">Place order · PHP <?php echo number_format($cartTotal, 2); ?></button>
            </form>
            <aside class="order-summary">
                <h2>Order summary</h2>
                <div class="checkout-customer-summary">
                    <strong><?php echo checkout_h($values['name']); ?></strong>
                    <span><?php echo checkout_h($values['street_address']); ?></span>
                    <span><?php echo checkout_h($values['barangay']); ?>, <?php echo checkout_h($values['municipality']); ?>, <?php echo checkout_h($values['province']); ?></span>
                    <span><?php echo checkout_h($values['email']); ?></span>
                </div>
                <div class="checkout-table-wrap"><table class="checkout-summary-table">
                    <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
                    <tbody><?php foreach ($cartItems as $item) { ?>
                        <tr><td><?php echo checkout_h($item['name']); ?></td><td><?php echo (int) $item['cart_quantity']; ?></td><td>PHP <?php echo number_format((float) $item['price'], 2); ?></td><td>PHP <?php echo number_format((float) $item['subtotal'], 2); ?></td></tr>
                    <?php } ?></tbody>
                    <tfoot><tr><th colspan="3">Total</th><th>PHP <?php echo number_format($cartTotal, 2); ?></th></tr></tfoot>
                </table></div>
            </aside>
        </div>
    </main>
    <script>
        const cardFields = document.getElementById('cardFields');
        document.querySelectorAll('input[name="payment_method"]').forEach((option) => {
            option.addEventListener('change', () => {
                const isCard = option.value === 'card' && option.checked;
                cardFields.hidden = !isCard;
                document.querySelector('.place-order-button').disabled = isCard;
            });
        });
        document.querySelector('.place-order-button').disabled = document.querySelector('input[name="payment_method"][value="card"]').checked;
    </script>
</body>
</html>
