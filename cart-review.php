<?php
session_start();
require_once 'auth.php';
require_customer_login();
require 'db.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
$cartItems = [];
$cartTotal = 0.0;
if ($_SESSION['cart']) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
    $result = $conn->query("SELECT id, name, price, image_path, quantity FROM products WHERE id IN ($ids) ORDER BY name");
    while ($product = $result->fetch_assoc()) {
        $quantity = min((int)$_SESSION['cart'][$product['id']], (int)$product['quantity']);
        if ($quantity < 1) { unset($_SESSION['cart'][$product['id']]); continue; }
        $product['cart_quantity'] = $quantity;
        $product['subtotal'] = $quantity * (float)$product['price'];
        $cartItems[] = $product;
        $cartTotal += $product['subtotal'];
    }
}
function cart_review_h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Checkout | PhoneWiz</title><link rel="icon" type="image/png" href="PhoneWiz.png"><link rel="stylesheet" href="products.css?v=2"><link rel="stylesheet" href="cart-design.css?v=11"></head><body>
<?php include 'user-sidebar.php'; ?>
<main class="cart-review-page"><p class="page-label">PHONEWIZ CHECKOUT</p><h1>Review your cart</h1><p class="cart-review-intro">Check your products and quantities before entering your shipping information.</p>
<?php if (!$cartItems) { ?><section class="cart-review-empty"><h2>No products added</h2><p>Please add or browse for a product to checkout.</p><a class="checkout-button" href="products.php">Browse products <span>→</span></a></section><?php } else { ?>
<div class="cart-review-table-wrap"><table class="cart-review-table"><thead><tr><th>Product</th><th>Unit price</th><th>Quantity</th><th>Subtotal</th><th></th></tr></thead><tbody>
<?php foreach ($cartItems as $item) { ?><tr><td class="review-product"><?php if($item['image_path']){?><img src="<?php echo cart_review_h($item['image_path']);?>" alt=""><?php }?><strong><?php echo cart_review_h($item['name']);?></strong></td><td>PHP <?php echo number_format((float)$item['price'],2);?></td><td><?php echo (int)$item['cart_quantity'];?></td><td>PHP <?php echo number_format((float)$item['subtotal'],2);?></td><td><a class="text-link" href="products.php">Edit cart</a></td></tr><?php } ?>
</tbody><tfoot><tr><th colspan="3">Total</th><th colspan="2">PHP <?php echo number_format($cartTotal,2);?></th></tr></tfoot></table></div>
<div class="cart-review-actions"><a class="review-back" href="products.php">Continue shopping</a><a class="checkout-button" href="checkout.php">Continue to shipping and payment <span>→</span></a></div>
<?php } ?></main></body></html>
