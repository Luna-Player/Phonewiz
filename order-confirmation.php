<?php
session_start();
require_once 'auth.php';
require_customer_login();
$order = $_SESSION['last_order'] ?? null;
if (!$order) {
    header('Location: products.php');
    exit;
}
function confirmation_h($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order received | PhoneWiz</title>
    <link rel="icon" type="image/png" href="PhoneWiz.png">
    <link rel="stylesheet" href="products.css?v=2">
    <link rel="stylesheet" href="cart-design.css?v=11">
</head>
<body>
    <?php include 'user-sidebar.php'; ?>
    <main class="confirmation-page">
        <div class="customer-welcome">Welcome, <?php echo current_username(); ?> · <a href="logout.php">Log out</a></div>
        <section class="confirmation-card">
            <div class="confirmation-check" aria-hidden="true">✓</div>
            <p class="page-label">PHONEWIZ ORDER</p>
            <h1>Order received</h1>
            <p class="confirmation-intro">Thank you, <?php echo confirmation_h($order['name']); ?>. Your order details are ready.</p>
            <div class="confirmation-details">
                <p><span>Delivery address</span><strong><?php echo confirmation_h($order['street_address']); ?><br><?php echo confirmation_h($order['barangay'] . ', ' . $order['municipality'] . ', ' . $order['province']); ?></strong></p>
                <p><span>Email</span><strong><?php echo confirmation_h($order['email']); ?></strong></p>
                <p><span>Payment</span><strong><?php echo $order['payment_method'] === 'cod' ? 'Cash on delivery' : 'Credit card (demo)'; ?></strong></p>
            </div>
            <h2>Order summary</h2>
            <?php foreach ($order['items'] as $item) { ?>
                <div class="confirmation-item"><span><?php echo confirmation_h($item['name']); ?> × <?php echo (int) $item['cart_quantity']; ?></span><strong>PHP <?php echo number_format((float) $item['subtotal'], 2); ?></strong></div>
            <?php } ?>
            <p class="confirmation-total"><span>Total</span><strong>PHP <?php echo number_format((float) $order['total'], 2); ?></strong></p>
            <a class="place-order-button confirmation-link" href="my-orders.php">View my orders</a>
            <a class="place-order-button confirmation-link" href="products.php">Continue shopping</a>
        </section>
    </main>
</body>
</html>
