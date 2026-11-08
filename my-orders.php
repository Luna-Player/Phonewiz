<?php
session_start();
require_once 'auth.php';
require_customer_login();
include 'db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $postedToken = $_POST['csrf_token'] ?? '';
    $orderIdToCancel = (int) ($_POST['order_id'] ?? 0);
    if (!is_string($postedToken) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $message = 'Your session expired. Refresh and try again.';
    } elseif ($orderIdToCancel < 1) {
        $message = 'The order could not be found.';
    } else {
        try {
            $userId = (int) $_SESSION['user_id'];
            $conn->begin_transaction();
            $findOrder = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND status = 'Pending' FOR UPDATE");
            $findOrder->bind_param('ii', $orderIdToCancel, $userId);
            $findOrder->execute();
            if (!$findOrder->get_result()->fetch_assoc()) {
                $conn->rollback();
                $message = 'Only pending orders can be cancelled.';
            } else {
                $restoreStock = $conn->prepare('UPDATE products p JOIN order_items oi ON oi.product_id = p.id SET p.quantity = p.quantity + oi.quantity WHERE oi.order_id = ?');
                $restoreStock->bind_param('i', $orderIdToCancel);
                $restoreStock->execute();
                $cancelStatement = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ? AND user_id = ? AND status = 'Pending'");
                $cancelStatement->bind_param('ii', $orderIdToCancel, $userId);
                $cancelStatement->execute();
                $conn->commit();
                $message = 'Order #' . $orderIdToCancel . ' was cancelled.';
            }
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $message = 'The order could not be cancelled. Please try again.';
        }
    }
    header('Location: my-orders.php?message=' . urlencode($message) . '#order-history');
    exit;
}
$message = trim($_GET['message'] ?? '');
$userId = (int) $_SESSION['user_id'];
$totalStatement = $conn->prepare('SELECT COALESCE(SUM(total_amount), 0) AS total_spent FROM orders WHERE user_id = ?');
$totalStatement->bind_param('i', $userId);
$totalStatement->execute();
$totalSpent = (float) $totalStatement->get_result()->fetch_assoc()['total_spent'];
$ordersStatement = $conn->prepare('SELECT id, customer_name, municipality, province, barangay, street_address, customer_email, payment_method, total_amount, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC');
$ordersStatement->bind_param('i', $userId);
$ordersStatement->execute();
$orders = $ordersStatement->get_result();

function orders_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Orders | PhoneWiz</title>
    <link rel="icon" type="image/png" href="PhoneWiz.png">
    <link rel="stylesheet" href="products.css?v=2">
    <link rel="stylesheet" href="simple.css?v=1">
    <link rel="stylesheet" href="cart-design.css?v=11">
    <style>
        .orders-page{max-width:1000px;margin:0 auto;padding:42px 24px 64px}.orders-heading{margin:0 0 24px}.orders-heading h1{margin:0;font-size:clamp(30px,4vw,42px);letter-spacing:-.04em}.orders-list{display:grid;gap:16px}.customer-order{padding:22px;border:1px solid #e5e8f0;border-radius:14px;background:#fff;box-shadow:0 8px 24px #17223b0a}.order-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding-bottom:15px;border-bottom:1px solid #e5e8f0}.order-top h2{margin:0 0 5px;font-size:18px}.order-date{margin:0;color:#6f7a91;font-size:13px}.status-pill{padding:6px 10px;border-radius:999px;background:#e9e7ff;color:#483fc7;font-size:12px;font-weight:800;white-space:nowrap}.order-lines{display:grid;gap:10px;padding:12px 0}.order-line{display:flex;justify-content:space-between;gap:15px;font-size:14px}.order-line span:last-child{font-weight:700;white-space:nowrap}.order-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:8px;padding:14px;border-radius:9px;background:#f5f7fd}.order-meta p{margin:0;font-size:13px;line-height:1.5;overflow-wrap:anywhere}.order-meta strong{display:block;margin-bottom:3px}.order-total{display:flex;justify-content:space-between;margin:16px 0 0;font-size:17px}.orders-summary{margin:0 0 20px;padding:18px 20px;border:1px solid #e5e8f0;border-radius:12px;background:#fff}.orders-summary span{color:#6f7a91}.orders-summary strong{margin-left:10px;font-size:20px}.order-actions{display:flex;align-items:center;gap:10px}.cancel-order{padding:7px 10px;border:1px solid #fecaca;border-radius:7px;background:#fff;color:#b42318;font:inherit;font-size:12px;font-weight:700;cursor:pointer}.order-notice{margin:0 0 18px;padding:12px 14px;border-radius:9px;background:#dcfce7;color:#166534;font-weight:700}.empty-orders{padding:44px 22px;border:1px dashed #cfd5e3;border-radius:14px;background:#fff;text-align:center}.empty-orders p{color:#6f7a91}.empty-orders .add-cart{display:inline-block;text-decoration:none}@media(max-width:620px){.orders-page{padding:30px 16px 48px}.customer-order{padding:16px}.order-meta{grid-template-columns:1fr}.order-line{font-size:13px}.order-top{align-items:flex-start}}
    </style>
</head>
<body>
    <?php include 'user-sidebar.php'; ?>
    <main class="orders-page">
        <div class="customer-welcome">Welcome, <?php echo orders_h($_SESSION['username']); ?> · <a href="logout.php">Log out</a></div>
        <a class="back-link" href="products.php">← Continue shopping</a>
        <header class="orders-heading" id="order-history">
            <p class="page-label">YOUR ACCOUNT</p>
            <h1>My Orders</h1>
            <p class="intro">View your purchases and check their latest status.</p>
        </header>
        <?php if ($message) { ?><p class="order-notice" role="status"><?php echo orders_h($message); ?></p><?php } ?>
        <p class="orders-summary" id="order-total"><span>Total spent on your orders</span><strong>PHP <?php echo number_format($totalSpent, 2); ?></strong></p>
        <div id="order-status"></div>
        <?php if ($orders->num_rows === 0) { ?>
            <section class="empty-orders"><strong>No orders yet</strong><p>Your completed checkouts will appear here.</p><a class="add-cart" href="products.php">Browse products</a></section>
        <?php } else { ?>
            <div class="orders-list">
                <?php while ($order = $orders->fetch_assoc()) {
                    $orderId = (int) $order['id'];
                    $itemsStatement = $conn->prepare('SELECT product_name, image_path, unit_price, quantity FROM order_items WHERE order_id = ? ORDER BY id');
                    $itemsStatement->bind_param('i', $orderId);
                    $itemsStatement->execute();
                    $items = $itemsStatement->get_result();
                ?>
                    <article class="customer-order">
                        <header class="order-top"><div><h2>Order #<?php echo $orderId; ?></h2><p class="order-date">Placed <?php echo orders_h($order['created_at']); ?></p></div><div class="order-actions"><span class="status-pill"><?php echo orders_h($order['status']); ?></span><?php if ($order['status'] === 'Pending') { ?><form method="post" onsubmit="return confirm('Cancel order #<?php echo $orderId; ?>?');"><input type="hidden" name="csrf_token" value="<?php echo orders_h($_SESSION['csrf_token']); ?>"><input type="hidden" name="order_id" value="<?php echo $orderId; ?>"><button class="cancel-order" type="submit" name="cancel_order">Cancel order</button></form><?php } ?></div></header>
                        <div class="order-lines">
                            <?php while ($item = $items->fetch_assoc()) { ?>
                                <div class="order-line"><span><?php echo orders_h($item['product_name']); ?> × <?php echo (int) $item['quantity']; ?></span><span>PHP <?php echo number_format((float) $item['unit_price'] * (int) $item['quantity'], 2); ?></span></div>
                            <?php } ?>
                        </div>
                        <div class="order-meta"><p><strong>Deliver to</strong><?php echo orders_h($order['customer_name']); ?><br><?php echo orders_h($order['street_address']); ?><br><?php echo orders_h($order['barangay'] . ', ' . $order['municipality'] . ', ' . $order['province']); ?></p><p><strong>Email and payment</strong><?php echo orders_h($order['customer_email']); ?><br><?php echo $order['payment_method'] === 'cod' ? 'Cash on delivery' : 'Credit card'; ?></p></div>
                        <p class="order-total"><span>Order total</span><strong>PHP <?php echo number_format((float) $order['total_amount'], 2); ?></strong></p>
                    </article>
                <?php } ?>
            </div>
        <?php } ?>
    </main>
</body>
</html>
