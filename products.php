<?php
session_start();
require_once 'auth.php';
require_customer_login();
include 'db.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['cart_notice'] = 'Your session expired. Refresh the page and try again.';
        header('Location: products.php');
        exit;
    }
    $action = $_POST['cart_action'] ?? '';
    $cartProductId = (int) ($_POST['product_id'] ?? 0);
    if (in_array($action, ['add', 'increase'], true) && $cartProductId > 0) {
        $result = $conn->query("SELECT id, quantity FROM products WHERE id = $cartProductId LIMIT 1");
        $cartProduct = $result ? $result->fetch_assoc() : null;
        $currentQuantity = (int) ($_SESSION['cart'][$cartProductId] ?? 0);
        if ($cartProduct && $currentQuantity < (int) $cartProduct['quantity']) {
            $_SESSION['cart'][$cartProductId] = $currentQuantity + 1;
            $_SESSION['cart_notice'] = 'Product added to your cart.';
        } else {
            $_SESSION['cart_notice'] = 'This product has reached its available stock limit.';
        }
    } elseif ($action === 'decrease' && $cartProductId > 0 && isset($_SESSION['cart'][$cartProductId])) {
        $_SESSION['cart'][$cartProductId]--;
        if ($_SESSION['cart'][$cartProductId] <= 0) unset($_SESSION['cart'][$cartProductId]);
    } elseif ($action === 'remove' && $cartProductId > 0) {
        unset($_SESSION['cart'][$cartProductId]);
    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];
    }
    $returnTo = $_POST['return_to'] ?? 'products.php';
    $returnParts = parse_url($returnTo);
    $isLocalProductPage = $returnParts !== false
        && !isset($returnParts['scheme'], $returnParts['host'], $returnParts['user'], $returnParts['pass'], $returnParts['port'])
        && str_ends_with($returnParts['path'] ?? '', 'products.php');
    header('Location: ' . ($isLocalProductPage ? $returnTo : 'products.php'));
    exit;
}

$cartItems = [];
$cartTotal = 0.0;
$cartCount = 0;
if ($_SESSION['cart']) {
    $cartIdList = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
    $cartResult = $conn->query("SELECT id, name, price, image_path, quantity FROM products WHERE id IN ($cartIdList) ORDER BY name");
    while ($cartProduct = $cartResult->fetch_assoc()) {
        $quantity = min((int) $_SESSION['cart'][$cartProduct['id']], (int) $cartProduct['quantity']);
        if ($quantity < 1) { unset($_SESSION['cart'][$cartProduct['id']]); continue; }
        $cartProduct['cart_quantity'] = $quantity;
        $cartProduct['subtotal'] = $quantity * (float) $cartProduct['price'];
        $cartItems[] = $cartProduct;
        $cartCount += $quantity;
        $cartTotal += $cartProduct['subtotal'];
    }
}
$cartNotice = $_SESSION['cart_notice'] ?? '';
unset($_SESSION['cart_notice']);
$category = $_GET['category'] ?? '';
$category = $conn->real_escape_string($category);
$search = trim($_GET['search'] ?? '');
$search = $conn->real_escape_string($search);
$productId = (int) ($_GET['id'] ?? 0);

if ($productId > 0) {
    $selectedProduct = $conn->query("SELECT * FROM products WHERE id = $productId")->fetch_assoc();
    $products = null;
} elseif ($category !== '') {
    $products = $conn->query("SELECT * FROM products WHERE category = '$category' ORDER BY id DESC");
    $selectedProduct = null;
} elseif ($search !== '') {
    $products = $conn->query("SELECT * FROM products WHERE name LIKE '%$search%' OR description LIKE '%$search%' OR category LIKE '%$search%' ORDER BY id DESC");
    $selectedProduct = null;
} else {
    $products = $conn->query('SELECT * FROM products ORDER BY id DESC');
    $selectedProduct = null;
}
$clientCategories = $conn->query('SELECT id, name FROM categories ORDER BY name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PhoneWiz Products</title>
    <link rel="icon" type="image/png" href="PhoneWiz.png">
    <link rel="stylesheet" href="products.css?v=2">
    <link rel="stylesheet" href="simple.css?v=1">
    <link rel="stylesheet" href="cart-design.css?v=11">
</head>
<body>
    <?php include 'user-sidebar.php'; ?>
    <main class="page-content">
    <div class="customer-welcome">Welcome, <?php echo current_username(); ?> · <a href="logout.php">Log out</a></div>
    <?php if ($cartNotice) { ?><p class="cart-notice" role="status"><?php echo htmlspecialchars($cartNotice); ?></p><?php } ?>
    <?php if ($selectedProduct) { ?>
        <a class="back-link" href="products.php">← Back to all products</a>
        <article class="product-detail">
            <div class="detail-image"><?php if ($selectedProduct['image_path']) { ?><img src="<?php echo htmlspecialchars($selectedProduct['image_path']); ?>" alt="<?php echo htmlspecialchars($selectedProduct['name']); ?>"><?php } ?></div>
            <div class="detail-content"><p class="page-label"><?php echo htmlspecialchars($selectedProduct['category']); ?></p><h1><?php echo htmlspecialchars($selectedProduct['name']); ?></h1>
                <p class="detail-price">PHP <?php echo number_format($selectedProduct['price'], 2); ?></p>
                <?php if (!empty($selectedProduct['description'])) { ?><p class="detail-description"><?php echo htmlspecialchars($selectedProduct['description']); ?></p><?php } ?>
                <dl class="product-specs">
                    <div>
                        <dt>Available stock</dt>
                        <dd><?php echo $selectedProduct['quantity']; ?> unit<?php echo $selectedProduct['quantity'] === '1' ? '' : 's'; ?></dd>
                    </div>
                    <div>
                        <dt>Available colors</dt>
                        <dd><?php echo htmlspecialchars($selectedProduct['colors'] ?: 'Not specified'); ?></dd>
                    </div>
                </dl>
                <?php if ((int) $selectedProduct['quantity'] > 0) { ?><form method="post" class="add-cart-form"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="cart_action" value="add"><input type="hidden" name="product_id" value="<?php echo $selectedProduct['id']; ?>"><input type="hidden" name="return_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>"><button class="add-cart" type="submit">Add to cart</button></form><?php } else { ?><p class="out-of-stock">Out of stock</p><?php } ?>
            </div>
        </article>
    <?php } elseif ($productId > 0) { ?>
        <div class="top">
            <div>
                <p class="page-label">PHONEWIZ CATALOG</p>
                <h1>Product not found</h1>
                <p class="intro">This product may no longer be available.</p>
            </div>
        </div>
        <a class="back-link" href="products.php">View all products</a>
    <?php } elseif ($products->num_rows === 0) { ?>
        <div class="top">
            <div>
                <p class="page-label">PHONEWIZ CATALOG</p>
                <h1>No products found</h1>
                <p class="intro">Try another search term or browse all products.</p>
            </div>
        </div>
        <a class="back-link" href="products.php">View all products</a>
    <?php } else { ?>
        <div class="top">
            <div>
                <p class="page-label">PHONEWIZ CATALOG</p>
                <h1><?php echo $category ? htmlspecialchars($category) : 'Find your next phone'; ?></h1>
                <p class="intro"><?php echo $products->num_rows; ?> carefully selected device<?php echo $products->num_rows === 1 ? '' : 's'; ?> available now.</p>
            </div>
            <form class="search-form" method="get">
                <input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search products">
                <button type="submit">Search</button>
            </form>
        </div>
        <section class="products">
            <?php while ($product = $products->fetch_assoc()) { ?>
                <article class="product-card">
                    <a class="product-link" href="products.php?id=<?php echo $product['id']; ?>">
                        <div class="image-area"><?php if ($product['image_path']) { ?>
                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>"><?php } ?>
                    </div>
                    <h2><?php echo htmlspecialchars($product['name']); ?></h2>
                    <p class="category"><?php echo htmlspecialchars($product['category']); ?></p>
                    <?php if (!empty($product['description'])) { ?><p class="description"><?php echo htmlspecialchars($product['description']); ?></p><?php } ?>
                    <?php if (!empty($product['colors'])) { ?><p class="colors"><span>Colors</span> <?php echo htmlspecialchars($product['colors']); ?></p><?php } ?>
                    <div class="price-stock">
                        <p class="price">PHP <?php echo number_format($product['price'], 2); ?></p>
                        <p>Stock: <?php echo $product['quantity']; ?></p>
                    </div>
                </a>
                <?php if ((int) $product['quantity'] > 0) { ?><form method="post" class="card-cart-form"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="cart_action" value="add"><input type="hidden" name="product_id" value="<?php echo $product['id']; ?>"><input type="hidden" name="return_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>"><button class="add-cart" type="submit">Add to cart</button></form><?php } else { ?><p class="out-of-stock">Out of stock</p><?php } ?>
                </article>
            <?php } ?>
        </section>
    <?php } ?></main>
    <button class="cart-fab open-cart" type="button" aria-controls="cartPanel" aria-label="Open shopping cart">&#128722;<span class="cart-badge"><?php echo $cartCount; ?></span></button>
    <div class="cart-overlay" id="cartOverlay"></div>
    <aside class="cart-panel" id="cartPanel" aria-label="Shopping cart" aria-hidden="true">
        <header class="cart-header"><h2>Your cart</h2><button class="close-cart" type="button" aria-label="Close cart">&times;</button></header>
        <div class="cart-content"><?php if ($cartItems) { foreach ($cartItems as $cartItem) { ?><article class="cart-item"><?php if ($cartItem['image_path']) { ?><img src="<?php echo htmlspecialchars($cartItem['image_path']); ?>" alt="<?php echo htmlspecialchars($cartItem['name']); ?>"><?php } ?><div><h3><?php echo htmlspecialchars($cartItem['name']); ?></h3><p>PHP <?php echo number_format($cartItem['subtotal'], 2); ?></p><div class="quantity-controls"><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="cart_action" value="decrease"><input type="hidden" name="product_id" value="<?php echo $cartItem['id']; ?>"><button type="submit">&minus;</button></form><span><?php echo $cartItem['cart_quantity']; ?></span><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="cart_action" value="increase"><input type="hidden" name="product_id" value="<?php echo $cartItem['id']; ?>"><button type="submit">+</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="cart_action" value="remove"><input type="hidden" name="product_id" value="<?php echo $cartItem['id']; ?>"><button class="remove-cart-item" type="submit">Remove</button></form></div></div></article><?php } } else { ?><div class="empty-cart"><strong>Your cart is empty</strong><p>Add a phone to see it here.</p></div><?php } ?></div>
        <footer class="cart-footer"><p>Total <strong>PHP <?php echo number_format($cartTotal, 2); ?></strong></p><?php if ($cartItems) { ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="cart_action" value="clear"><button class="clear-cart" type="submit">Clear cart</button></form><?php } ?></footer>
        <?php if ($cartItems) { ?><a class="checkout-button" href="cart-review.php">Proceed to checkout <span>→</span></a><?php } else { ?><a class="checkout-button checkout-button-empty" href="products.php">Browse products</a><?php } ?>
    </aside>
    <script src="jquery-4.0.0.min.js"></script>
    <script>
        $(document).ready(function() {
            function toggleCart(show) { $('#cartPanel').toggleClass('active', show).attr('aria-hidden', !show); $('#cartOverlay').toggleClass('active', show); }
            $('.open-cart').click(function(e) { e.preventDefault(); toggleCart(true); });
            $('.close-cart, #cartOverlay').click(function() { toggleCart(false); });
            $(document).on('keydown', function(e) { if (e.key === 'Escape') toggleCart(false); });
        });
    </script>
</body>
</html>
