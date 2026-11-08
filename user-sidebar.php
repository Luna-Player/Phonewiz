<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$userCartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) $userCartCount = array_sum(array_map('intval', $_SESSION['cart']));
$userCurrentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
?>
<aside class="user-sidebar" id="userSidebar" aria-label="Customer navigation">
    <a class="user-sidebar-brand" href="index.php"><img src="PhoneWiz.png" alt=""><span>PhoneWiz<small>CUSTOMER ACCOUNT</small></span></a>
    <nav>
        <a class="<?php echo $userCurrentPage==='index.php'?'active':'';?>" href="index.php">Home</a>
        <a class="<?php echo $userCurrentPage==='products.php'?'active':'';?>" href="products.php">Products</a>
        <a href="<?php echo $userCurrentPage==='products.php'?'#cartPanel':'products.php#cartPanel';?>" class="<?php echo $userCurrentPage==='products.php'?'open-cart':'';?>">Shopping cart <span class="user-cart-badge"><?php echo $userCartCount;?></span></a>
        <a class="<?php echo $userCurrentPage==='my-orders.php'?'active':'';?>" href="my-orders.php">My orders</a>
        <a class="<?php echo in_array($userCurrentPage,['cart-review.php','checkout.php'],true)?'active':'';?>" href="cart-review.php">Checkout</a>
        <a class="<?php echo $userCurrentPage==='account.php'?'active':'';?>" href="account.php">Shipping information</a>
    </nav>
    <a class="user-sidebar-logout" href="logout.php">Log out</a>
</aside>
