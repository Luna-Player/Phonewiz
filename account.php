<?php
session_start();
require_once 'auth.php';
require_customer_login();
require 'db.php';

$userId = (int) $_SESSION['user_id'];
$statement = $conn->prepare('SELECT username, full_name, checkout_email, contact_number, street_address, barangay, municipality, province FROM users WHERE id = ? LIMIT 1');
$statement->bind_param('i', $userId);
$statement->execute();
$profile = $statement->get_result()->fetch_assoc();
if (!$profile) { header('Location: logout.php'); exit; }
$errors = [];
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$saved = isset($_GET['saved']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) $errors[] = 'Your session expired. Refresh and try again.';
    foreach (['full_name','checkout_email','contact_number','street_address','barangay','municipality','province'] as $field) $profile[$field] = trim($_POST[$field] ?? '');
    if ($profile['full_name'] === '' || strlen($profile['full_name']) > 120) $errors[] = 'Enter your name (up to 120 characters).';
    if (!filter_var($profile['checkout_email'], FILTER_VALIDATE_EMAIL) || strlen($profile['checkout_email']) > 150) $errors[] = 'Enter a valid email address.';
    if ($profile['contact_number'] === '' || strlen($profile['contact_number']) > 20) $errors[] = 'Enter a contact number (up to 20 characters).';
    foreach (['street_address'=>255,'barangay'=>100,'municipality'=>100,'province'=>100] as $field=>$limit) if ($profile[$field] === '' || strlen($profile[$field]) > $limit) $errors[] = 'Enter a valid ' . str_replace('_',' ',$field) . ' (up to ' . $limit . ' characters).';
    if (!$errors) {
        $deliveryAddress = $profile['street_address'] . ', ' . $profile['barangay'] . ', ' . $profile['municipality'] . ', ' . $profile['province'];
        $update = $conn->prepare('UPDATE users SET full_name=?, checkout_email=?, email=?, contact_number=?, street_address=?, barangay=?, municipality=?, province=?, delivery_address=? WHERE id=?');
        $update->bind_param('sssssssssi', $profile['full_name'], $profile['checkout_email'], $profile['checkout_email'], $profile['contact_number'], $profile['street_address'], $profile['barangay'], $profile['municipality'], $profile['province'], $deliveryAddress, $userId);
        try {
            $update->execute();
            header('Location: account.php?saved=1');
            exit;
        } catch (mysqli_sql_exception $exception) {
            if ((int)$exception->getCode() === 1062) $errors[] = 'That email address belongs to another account.';
            else throw $exception;
        }
    }
}
function account_h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>My account | PhoneWiz</title><link rel="icon" type="image/png" href="PhoneWiz.png"><link rel="stylesheet" href="simple.css?v=1"><link rel="stylesheet" href="cart-design.css?v=11"><style>
body{margin:0;background:#f5f7fd;color:#17223b;font-family:Arial,sans-serif}.account-page{max-width:850px;margin:40px auto;padding:0 20px}.account-nav{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}.account-nav a{color:#5146e5;text-decoration:none}.account-card{padding:30px;border:1px solid #e5e8f0;border-radius:16px;background:#fff}.account-card h1{margin-top:0}.account-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 18px}.account-grid label{display:block;margin:15px 0 7px;font-size:13px;font-weight:700}.account-grid input{width:100%;padding:12px;border:1px solid #dce1ed;border-radius:8px;font:inherit}.account-submit{margin-top:22px;padding:13px 18px;border:0;border-radius:8px;background:#5146e5;color:white;font-weight:800;cursor:pointer}.feedback{padding:12px 15px;border-radius:8px;background:#f0fdf4;color:#166534}.feedback.error{background:#fef2f2;color:#991b1b}@media(max-width:620px){.account-grid{grid-template-columns:1fr}.account-card{padding:22px}}
</style></head><body><?php include 'user-sidebar.php'; ?><main class="account-page"><nav class="account-nav"><a href="index.php">← PhoneWiz home</a><span><a href="my-orders.php">My orders</a> · <a href="logout.php">Log out</a></span></nav><section class="account-card"><p class="eyebrow">CUSTOMER ACCOUNT</p><h1>Shipping information</h1><p class="muted">These details are saved to your account and filled in automatically at checkout.</p><?php if($saved){?><p class="feedback" role="status">Your information has been updated.</p><?php }?><?php if($errors){?><div class="feedback error" role="alert"><ul><?php foreach($errors as $error){?><li><?php echo account_h($error);?></li><?php }?></ul></div><?php }?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo account_h($_SESSION['csrf_token']);?>"><div class="account-grid"><div><label for="full-name">Full name</label><input id="full-name" name="full_name" autocomplete="name" maxlength="120" value="<?php echo account_h($profile['full_name']);?>" required></div><div><label for="checkout-email">Email address</label><input id="checkout-email" name="checkout_email" type="email" autocomplete="email" maxlength="150" value="<?php echo account_h($profile['checkout_email']);?>" required></div><div><label for="contact-number">Contact number</label><input id="contact-number" name="contact_number" type="tel" autocomplete="tel" maxlength="20" value="<?php echo account_h($profile['contact_number']);?>" required></div><div><label for="street-address">House number and street</label><input id="street-address" name="street_address" autocomplete="street-address" maxlength="255" value="<?php echo account_h($profile['street_address']);?>" required></div><div><label for="barangay">Barangay</label><input id="barangay" name="barangay" maxlength="100" value="<?php echo account_h($profile['barangay']);?>" required></div><div><label for="municipality">Municipality / City</label><input id="municipality" name="municipality" autocomplete="address-level2" maxlength="100" value="<?php echo account_h($profile['municipality']);?>" required></div><div><label for="province">Province</label><input id="province" name="province" autocomplete="address-level1" maxlength="100" value="<?php echo account_h($profile['province']);?>" required></div></div><button class="account-submit" type="submit">Save shipping information</button></form></section></main></body></html>
