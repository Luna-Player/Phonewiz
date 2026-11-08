<?php
session_start();
require 'db.php';
if (!empty($_SESSION['admin_authenticated'])) {
    header('Location: admin.php');
    exit;
}
$error = '';
$adminHost = strtolower(preg_replace('/:\\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$adminRemote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$isLocalAdminRequest = in_array($adminHost, ['localhost', '127.0.0.1', '[::1]', '::1'], true)
    && in_array($adminRemote, ['127.0.0.1', '::1'], true);
$adminUsername = getenv('PHONEWIZ_ADMIN_USERNAME') ?: ($isLocalAdminRequest ? 'admin' : '');
$adminPassword = getenv('PHONEWIZ_ADMIN_PASSWORD') ?: ($isLocalAdminRequest ? 'admin123' : '');
$storedAdminResult = $conn->query('SELECT username, password_hash FROM admin_accounts WHERE id = 1');
$storedAdmin = $storedAdminResult ? $storedAdminResult->fetch_assoc() : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $validStoredLogin = $storedAdmin && hash_equals($storedAdmin['username'], $username) && password_verify($password, $storedAdmin['password_hash']);
    $validFallbackLogin = !$storedAdmin && $adminUsername !== '' && $adminPassword !== '' && hash_equals($adminUsername, $username) && hash_equals($adminPassword, $password);
    if ($validStoredLogin || $validFallbackLogin) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_username'] = $storedAdmin['username'] ?? $adminUsername;
        header('Location: admin.php');
        exit;
    }
    $error = (!$storedAdmin && ($adminUsername === '' || $adminPassword === '')) ? 'Admin sign in is not configured. Set PHONEWIZ_ADMIN_USERNAME and PHONEWIZ_ADMIN_PASSWORD in the server environment.' : 'The username or password is incorrect.';
}
if (!$storedAdmin && ($adminUsername === '' || $adminPassword === '')) {
    $error = 'Admin sign in must be configured with PHONEWIZ_ADMIN_USERNAME and PHONEWIZ_ADMIN_PASSWORD before deployment.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin login | PhoneWiz</title><link rel="icon" type="image/png" href="PhoneWiz.png">
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:22px;background:#f5f7fd;color:#17223b;font-family:Arial,sans-serif}.login-card{width:min(100%,420px);padding:34px;border:1px solid #e5e8f0;border-radius:16px;background:#fff;box-shadow:0 15px 36px #17223b12}.brand{display:flex;align-items:center;gap:10px;margin-bottom:28px;font-size:19px;font-weight:800}.brand img{width:40px;height:40px;border-radius:10px}.eyebrow{color:#5146e5;font-size:11px;font-weight:800;letter-spacing:.14em}.login-card h1{margin:8px 0;font-size:29px}.login-card p{color:#6f7a91;line-height:1.5}.login-card label{display:block;margin:17px 0 7px;font-size:13px;font-weight:700}.login-card input{width:100%;padding:12px;border:1px solid #dce1ed;border-radius:8px;font:inherit}.login-card button{width:100%;margin-top:22px;padding:13px;border:0;border-radius:8px;background:#5146e5;color:#fff;font:inherit;font-weight:800;cursor:pointer}.error{padding:11px;border-radius:8px;background:#fef2f2;color:#991b1b!important}
    </style>
</head>
<body><main class="login-card"><div class="brand"><img src="PhoneWiz.png" alt="">PhoneWiz Admin</div><span class="eyebrow">ADMINISTRATOR ACCESS</span><h1>Sign in</h1><p>Log in to manage products and customer orders.</p><?php if($error){?><p class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8');?></p><?php }?><form method="post"><label for="username">Username</label><input id="username" name="username" autocomplete="username" required><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><button type="submit">Log in to admin</button></form></main></body>
</html>
