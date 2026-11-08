<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['auth_csrf_token'])) $_SESSION['auth_csrf_token'] = bin2hex(random_bytes(32));

$errors = [];
$duplicateAccount = false;
$successNotice = $_SESSION['registration_notice'] ?? '';
unset($_SESSION['registration_notice']);
$mode = ($_GET['mode'] ?? $_POST['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$username = trim($_POST['username'] ?? '');
$profile = [
    'full_name' => trim($_POST['full_name'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'contact_number' => trim($_POST['contact_number'] ?? ''),
    'street_address' => trim($_POST['street_address'] ?? ''),
    'barangay' => trim($_POST['barangay'] ?? ''),
    'municipality' => trim($_POST['municipality'] ?? ''),
    'province' => trim($_POST['province'] ?? ''),
];
$next = $_POST['next'] ?? $_GET['next'] ?? 'products.php';
$nextParts = parse_url($next);
if ($nextParts === false || isset($nextParts['scheme']) || isset($nextParts['host']) || !str_starts_with($nextParts['path'] ?? '', '/')) {
    $next = 'products.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['auth_csrf_token'] ?? null) || !hash_equals($_SESSION['auth_csrf_token'], $_POST['auth_csrf_token'])) $errors[] = 'Your session expired. Refresh the page and try again.';
    $password = $_POST['password'] ?? '';
    if ($mode === 'register' && !preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3–50 characters using letters, numbers, or underscores.';
    }
    if ($mode === 'login' && !preg_match('/^[A-Za-z0-9_]{3,50}$/', $username) && !filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter your username or email address.';
    }
    if ($mode === 'register') {
        foreach (['full_name' => 'Enter your full name.', 'email' => 'Enter your email address.', 'contact_number' => 'Enter your contact number.', 'street_address' => 'Enter your street address.', 'barangay' => 'Enter your barangay.', 'municipality' => 'Enter your municipality or city.', 'province' => 'Enter your province.'] as $field => $error) {
            if ($profile[$field] === '') $errors[] = $error;
        }
        if ($profile['email'] !== '' && !filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
        if (strlen($profile['full_name']) > 120 || strlen($profile['street_address']) > 255 || strlen($profile['barangay']) > 100 || strlen($profile['municipality']) > 100 || strlen($profile['province']) > 100 || strlen($profile['contact_number']) > 20) $errors[] = 'One or more profile fields are too long.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if (($_POST['confirm_password'] ?? '') !== $password) $errors[] = 'Passwords do not match.';
        if (!$errors) {
            $existing = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $existing->bind_param('ss', $username, $profile['email']);
            $existing->execute();
            if ($existing->get_result()->num_rows > 0) {
                $duplicateAccount = true;
                $errors[] = 'An account with this username already exists. Please log in or choose another username.';
            } else {
                try {
                    $statement = $conn->prepare('INSERT INTO users (username, email, password_hash, full_name, delivery_address, contact_number, municipality, province, barangay, street_address, checkout_email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $deliveryAddress = $profile['street_address'] . ', ' . $profile['barangay'] . ', ' . $profile['municipality'] . ', ' . $profile['province'];
                    $statement->bind_param('sssssssssss', $username, $profile['email'], $hash, $profile['full_name'], $deliveryAddress, $profile['contact_number'], $profile['municipality'], $profile['province'], $profile['barangay'], $profile['street_address'], $profile['email']);
                    if ($statement->execute()) {
                        $_SESSION['registration_notice'] = 'Registration successful. Please log in with your new account.';
                        header('Location: index.php?mode=login');
                        exit;
                    }
                    $errors[] = 'Registration could not be completed. Please try again.';
                } catch (mysqli_sql_exception $exception) {
                    if ((int) $exception->getCode() === 1062) {
                        $duplicateAccount = true;
                        $errors[] = 'That username or email is already registered. Please log in or use different details.';
                    } else {
                        throw $exception;
                    }
                }
            }
        }
    } else {
        if ($password === '') $errors[] = 'Enter your password.';
        if (!$errors) {
            $statement = $conn->prepare('SELECT id, username, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1');
            $statement->bind_param('ss', $username, $username);
            $statement->execute();
            $user = $statement->get_result()->fetch_assoc();
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: index.php');
                exit;
            }
            $errors[] = 'Username or password is incorrect.';
        }
    }
}

if (!empty($_SESSION['user_id'])) {
    $safeUsername = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
    ?>
    <!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PhoneWiz | Mobile made simple</title><link rel="icon" type="image/png" href="PhoneWiz.png"><link rel="stylesheet" href="cart-design.css?v=11"><style>
    :root{--ink:#17223b;--muted:#72809a;--purple:#5146e5;--soft:#f5f7fd}*{box-sizing:border-box}body{margin:0;background:var(--soft);color:var(--ink);font-family:Arial,sans-serif}.home{max-width:1100px;margin:auto;padding:24px}.top{display:flex;justify-content:space-between;align-items:center}.brand{display:flex;gap:10px;align-items:center;font-weight:bold}.brand img{width:38px;height:38px;border-radius:10px}.top a{color:#5146e5;text-decoration:none}.welcome{padding:10px 14px;border-radius:9px;background:#e9e7ff}.orders-dropdown{position:relative;display:inline-block}.orders-dropdown summary{color:#5146e5;cursor:pointer;list-style:none}.orders-dropdown summary::-webkit-details-marker{display:none}.orders-dropdown div{position:absolute;right:0;z-index:5;min-width:155px;padding:7px;border:1px solid #e5e8f0;border-radius:9px;background:white;box-shadow:0 8px 20px #17223b1a}.orders-dropdown div a{display:block;padding:8px 10px;border-radius:6px;white-space:nowrap}.orders-dropdown div a:hover{background:#f5f7fd}.hero{padding:110px 0 80px;max-width:700px}.eyebrow{color:#5146e5;font-size:12px;font-weight:bold;letter-spacing:.14em}.hero h1{margin:8px 0;font-size:clamp(42px,7vw,74px);line-height:1}.hero p{color:var(--muted);font-size:18px;line-height:1.55}.button{display:inline-block;padding:14px 20px;border-radius:9px;background:var(--purple);color:#fff!important;font-weight:bold}.categories{display:flex;gap:10px;flex-wrap:wrap;padding-bottom:38px}.categories a{padding:11px 14px;border:1px solid #e4e7f0;border-radius:8px;background:white;color:var(--ink);text-decoration:none}.featured{padding:20px 0 45px}.featured h2{font-size:23px}.carousel{position:relative;height:360px;overflow:hidden;border:1px solid #e4e7f0;border-radius:18px;background:white;box-shadow:0 12px 30px #17223b0d}.slide{display:none;position:relative;width:100%;height:100%}.slide.active{display:block;animation:slide-in .35s ease}.slide img{width:100%;height:100%;object-fit:contain;padding:18px}.caption{position:absolute;right:0;bottom:0;left:0;padding:35px 18px 18px;background:linear-gradient(transparent,#17223b99);color:#fff;text-align:center;font-weight:700}.carousel-button{position:absolute;z-index:2;top:50%;transform:translateY(-50%);width:42px;height:42px;border:0;border-radius:50%;background:#fff;color:#17223b;font-size:22px;box-shadow:0 4px 12px #17223b2b;cursor:pointer}.carousel-button:hover{background:#5146e5;color:white}.carousel-button.prev{left:15px}.carousel-button.next{right:15px}.footer{text-align:center;padding:25px;color:var(--muted)}@keyframes slide-in{from{opacity:.35}to{opacity:1}}@media(max-width:650px){.hero{padding:70px 0 55px}.carousel{height:270px}}
    </style><style>.home{margin-left:max(245px,calc((100vw - 1100px)/2))}.footer{margin-left:245px}@media(max-width:760px){.home{margin-left:0;padding-top:68px}.footer{margin-left:0}}</style><style>.home{max-width:1100px;margin-left:max(269px,calc((100vw - 1100px)/2 + 245px));margin-right:24px}.footer{margin-left:245px}.hero{padding-top:70px}@media(max-width:760px){.home{margin:0;padding:68px 18px 24px}.footer{margin-left:0}.top{gap:14px;flex-wrap:wrap}}</style></head><body><?php if (!empty($_SESSION["user_id"])) include "user-sidebar.php"; ?><main class="home"><header class="top"><div class="brand"><img src="PhoneWiz.png" alt="">PhoneWiz</div><div><span class="welcome">Welcome, <?php echo $safeUsername; ?></span> &nbsp; <a href="account.php">Shipping info</a> &nbsp; <details class="orders-dropdown"><summary>My Orders</summary><div><a href="my-orders.php#order-history">Order History</a><a href="my-orders.php#order-total">Total Amount</a><a href="my-orders.php#order-status">Order Status</a></div></details> &nbsp; <a href="logout.php">Log out</a></div></header><section class="hero"><span class="eyebrow">MOBILE, MADE SIMPLE</span><h1>Your next phone, made easy.</h1><p>Browse a carefully selected catalog of phones, compare what matters, and find the right fit.</p><a class="button" href="products.php">Browse products</a></section><p class="eyebrow">EXPLORE CATEGORIES</p><nav class="categories"><a href="products.php?category=iOS">iOS</a><a href="products.php?category=Android">Android</a><a href="products.php?category=Gaming%20Phones">Gaming Phones</a><a href="products.php?category=Flagship%20Phones">Flagship Phones</a><a href="products.php?category=Budget%20Phones">Budget Phones</a></nav><section class="featured"><p class="eyebrow">FEATURED BRANDS</p><h2>Discover your favorite brands</h2><div class="carousel" id="brandCarousel" aria-label="Featured phone brands"><div class="slide active"><img src="Ecomerse%20Products/logos/iOS.jpg" alt="iOS"><div class="caption">Designed to feel effortless</div></div><div class="slide"><img src="Ecomerse%20Products/logos/Android.jpeg" alt="Android"><div class="caption">Built for everyone, made for you</div></div><div class="slide"><img src="Ecomerse%20Products/logos/Samsung.jpeg" alt="Samsung"><div class="caption">Innovation that moves you</div></div><div class="slide"><img src="Ecomerse%20Products/logos/Xiaomi.jpeg" alt="Xiaomi"><div class="caption">Smart tech, real value</div></div><div class="slide"><img src="Ecomerse%20Products/logos/Oneplus.jpeg" alt="OnePlus"><div class="caption">Never settle</div></div><div class="slide"><img src="Ecomerse%20Products/logos/Oppo.jpeg" alt="OPPO"><div class="caption">Inspiration ahead</div></div><button class="carousel-button prev" type="button" aria-label="Previous slide">&#10094;</button><button class="carousel-button next" type="button" aria-label="Next slide">&#10095;</button></div></section></main><footer class="footer">&copy; 2026 PhoneWiz. All rights reserved. · <a href="admin.php">Admin</a></footer><script>const brandSlides=document.querySelectorAll("#brandCarousel .slide");let brandIndex=0;function showBrandSlide(next){brandSlides[brandIndex].classList.remove("active");brandIndex=(next+brandSlides.length)%brandSlides.length;brandSlides[brandIndex].classList.add("active")}document.querySelector("#brandCarousel .next").addEventListener("click",()=>showBrandSlide(brandIndex+1));document.querySelector("#brandCarousel .prev").addEventListener("click",()=>showBrandSlide(brandIndex-1));setInterval(()=>showBrandSlide(brandIndex+1),3500);</script></body></html>
    <?php
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $mode === 'register' ? 'Create account' : 'Sign in'; ?> | PhoneWiz</title>
    <link rel="icon" type="image/png" href="PhoneWiz.png">
    <link rel="stylesheet" href="cart-design.css?v=11">
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f5f7fd;color:#17223b;font-family:Arial,sans-serif}.auth-card{width:min(100%,720px);padding:34px;background:#fff;border:1px solid #e5e8f0;border-radius:18px;box-shadow:0 16px 40px #17223b12}.brand{display:flex;align-items:center;gap:11px;margin-bottom:28px;font-size:20px;font-weight:800}.brand img{width:42px;height:42px;border-radius:11px;object-fit:cover}.eyebrow{margin:0 0 8px;color:#5146e5;font-size:11px;font-weight:800;letter-spacing:.14em}.auth-card h1{margin:0;font-size:30px;letter-spacing:-.04em}.intro{color:#6f7a91;line-height:1.5}.auth-card label{display:block;margin:17px 0 7px;font-size:13px;font-weight:700}.auth-card input{width:100%;padding:12px;border:1px solid #dce1ed;border-radius:8px;font:inherit}.auth-card .field-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}.submit{width:100%;margin-top:23px;padding:13px;border:0;border-radius:8px;background:#5146e5;color:white;font:inherit;font-weight:800;cursor:pointer}.switch{margin:20px 0 0;color:#6f7a91;text-align:center;font-size:14px}.switch a{color:#5146e5;font-weight:700;text-decoration:none}.errors,.success{margin:15px 0;padding:12px 15px;border-radius:8px;font-size:14px}.errors{border:1px solid #fecaca;background:#fef2f2;color:#991b1b}.success{border:1px solid #bbf7d0;background:#f0fdf4;color:#166534}.errors ul{margin:0;padding-left:20px}@media(max-width:600px){.auth-card .field-grid{grid-template-columns:1fr}.auth-card{padding:24px}}
    </style>
</head>
<body>
    <?php if (!empty($_SESSION['user_id'])) include 'user-sidebar.php'; ?>
    <main class="auth-card">
        <div class="brand"><img src="PhoneWiz.png" alt="PhoneWiz logo">PhoneWiz</div>
        <p class="eyebrow">CUSTOMER ACCOUNT</p>
        <h1><?php echo $mode === 'register' ? 'Create your account' : 'Welcome back'; ?></h1>
        <p class="intro"><?php echo $mode === 'register' ? 'Enter your account and shipping details. We’ll save them so checkout is quicker next time.' : 'Sign in or create an account to continue to PhoneWiz.'; ?></p>
        <?php if ($successNotice) { ?><div class="success" role="status"><?php echo htmlspecialchars($successNotice, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
        <?php if ($errors) { ?><div class="errors" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php } ?></ul></div><?php } ?>
        <form method="post" action="index.php">
            <input type="hidden" name="auth_csrf_token" value="<?php echo htmlspecialchars($_SESSION['auth_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="mode" value="<?php echo $mode; ?>"><input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($mode === 'register') { ?><div class="field-grid">
            <div><label for="full-name">Full name</label><input id="full-name" name="full_name" autocomplete="name" maxlength="120" value="<?php echo htmlspecialchars($profile['full_name'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
            <div><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" maxlength="150" value="<?php echo htmlspecialchars($profile['email'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
            <div><label for="contact-number">Contact number</label><input id="contact-number" name="contact_number" type="tel" autocomplete="tel" maxlength="20" value="<?php echo htmlspecialchars($profile['contact_number'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
            <div><label for="street-address">House number and street</label><input id="street-address" name="street_address" autocomplete="street-address" maxlength="255" value="<?php echo htmlspecialchars($profile['street_address'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
            <div><label for="barangay">Barangay</label><input id="barangay" name="barangay" maxlength="100" value="<?php echo htmlspecialchars($profile['barangay'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
            <div><label for="municipality">Municipality / City</label><input id="municipality" name="municipality" autocomplete="address-level2" maxlength="100" value="<?php echo htmlspecialchars($profile['municipality'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
            <div><label for="province">Province</label><input id="province" name="province" autocomplete="address-level1" maxlength="100" value="<?php echo htmlspecialchars($profile['province'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
            <div><label for="username">Username</label><input id="username" name="username" type="text" autocomplete="username" minlength="3" maxlength="50" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required></div>
            </div><?php } else { ?><label for="username">Username or email</label><input id="username" name="username" type="text" autocomplete="username" minlength="3" maxlength="150" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required><?php } ?>
            <label for="password">Password</label><input id="password" name="password" type="password" autocomplete="<?php echo $mode === 'register' ? 'new-password' : 'current-password'; ?>" required>
            <?php if ($mode === 'register') { ?><label for="confirm-password">Confirm password</label><input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" required><?php } ?>
            <button class="submit" type="submit"><?php echo $mode === 'register' ? 'Register' : 'Log in'; ?></button>
        </form>
        <p class="switch"><?php echo $mode === 'register' ? 'Already have an account?' : 'New to PhoneWiz?'; ?> <a href="index.php?mode=<?php echo $mode === 'register' ? 'login' : 'register'; ?>&amp;next=<?php echo urlencode($next); ?>"><?php echo $mode === 'register' ? 'Log in' : 'Create an account'; ?></a></p>
    </main>
    <?php if ($duplicateAccount) { ?><script>alert(<?php echo json_encode('An account with this username already exists. Please log in or choose another username.'); ?>);</script><?php } ?>
</body>
</html>
