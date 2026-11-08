<?php
$host = getenv('PHONEWIZ_DB_HOST') ?: 'localhost';
$user = getenv('PHONEWIZ_DB_USER') ?: 'root';
$password = getenv('PHONEWIZ_DB_PASSWORD') ?: '';
$database = getenv('PHONEWIZ_DB_NAME') ?: 'phonewiz';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// These tables support the client-side category menu and the admin category tools.
$conn->query('CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
$conn->query('CREATE TABLE IF NOT EXISTS admin_accounts (id TINYINT UNSIGNED PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)');
$conn->query('CREATE TABLE IF NOT EXISTS subcategories (id INT AUTO_INCREMENT PRIMARY KEY, category_id INT NOT NULL, name VARCHAR(100) NOT NULL, FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE)');
$conn->query('CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, email VARCHAR(150) NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, full_name VARCHAR(120) NULL, delivery_address TEXT NULL, contact_number VARCHAR(20) NULL, municipality VARCHAR(100) NULL, province VARCHAR(100) NULL, barangay VARCHAR(100) NULL, street_address VARCHAR(255) NULL, checkout_email VARCHAR(150) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
$userColumnsResult = $conn->query('SHOW COLUMNS FROM users');
$userColumns = [];
while ($column = $userColumnsResult->fetch_assoc()) {
    $userColumns[$column['Field']] = true;
}

// Upgrade an existing email/password users table without removing its accounts.
if (!isset($userColumns['username'])) {
    $conn->query('ALTER TABLE users ADD username VARCHAR(50) NULL');
}
if (!isset($userColumns['password_hash'])) {
    $conn->query('ALTER TABLE users ADD password_hash VARCHAR(255) NULL');
}
if (!isset($userColumns['email'])) {
    $conn->query('ALTER TABLE users ADD email VARCHAR(150) NULL UNIQUE');
}
if (isset($userColumns['email'])) {
    $conn->query('ALTER TABLE users MODIFY email VARCHAR(150) NULL');
}
if (isset($userColumns['password'])) {
    $conn->query('ALTER TABLE users MODIFY password VARCHAR(255) NULL');
}
if (!isset($userColumns['full_name'])) {
    $conn->query('ALTER TABLE users ADD full_name VARCHAR(120) NULL');
}
if (!isset($userColumns['delivery_address'])) {
    $conn->query('ALTER TABLE users ADD delivery_address TEXT NULL');
}
if (!isset($userColumns['contact_number'])) {
    $conn->query('ALTER TABLE users ADD contact_number VARCHAR(20) NULL');
}
if (!isset($userColumns['municipality'])) {
    $conn->query('ALTER TABLE users ADD municipality VARCHAR(100) NULL');
}
if (!isset($userColumns['province'])) {
    $conn->query('ALTER TABLE users ADD province VARCHAR(100) NULL');
}
if (!isset($userColumns['barangay'])) {
    $conn->query('ALTER TABLE users ADD barangay VARCHAR(100) NULL');
}
if (!isset($userColumns['street_address'])) {
    $conn->query('ALTER TABLE users ADD street_address VARCHAR(255) NULL');
    $conn->query("UPDATE users SET street_address = LEFT(delivery_address, 255) WHERE street_address IS NULL AND delivery_address IS NOT NULL");
}
if (!isset($userColumns['checkout_email'])) {
    $conn->query('ALTER TABLE users ADD checkout_email VARCHAR(150) NULL');
    if (isset($userColumns['email'])) {
        $conn->query('UPDATE users SET checkout_email = email WHERE checkout_email IS NULL');
    }
}
if (!isset($userColumns['created_at'])) {
    $conn->query('ALTER TABLE users ADD created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
}

if (!isset($userColumns['username']) || !isset($userColumns['password_hash'])) {
    $legacyUsers = $conn->query('SELECT id, username, password_hash' . (isset($userColumns['password']) ? ', password' : ', NULL AS password') . (isset($userColumns['email']) ? ', email' : ', NULL AS email') . ' FROM users ORDER BY id');
    $updateUser = $conn->prepare('UPDATE users SET username = ?, password_hash = ? WHERE id = ?');
    while ($legacyUser = $legacyUsers->fetch_assoc()) {
        $name = trim((string) ($legacyUser['username'] ?? ''));
        if ($name === '') {
            $emailName = strstr((string) ($legacyUser['email'] ?? ''), '@', true);
            $name = preg_replace('/[^A-Za-z0-9_]/', '_', (string) ($emailName ?: 'customer'));
            $name = trim($name, '_');
            if (strlen($name) < 3) $name = 'user';
            $name = substr($name, 0, 40);
            $baseName = $name;
            $suffix = (string) $legacyUser['id'];
            while (true) {
                $checkName = $conn->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
                $checkName->bind_param('si', $name, $legacyUser['id']);
                $checkName->execute();
                if ($checkName->get_result()->num_rows === 0) break;
                $name = substr($baseName, 0, 50 - strlen($suffix) - 1) . '_' . $suffix;
                $suffix .= 'x';
            }
        }

        $hash = (string) ($legacyUser['password_hash'] ?? '');
        if ($hash === '') {
            $oldPassword = (string) ($legacyUser['password'] ?? '');
            $passwordInfo = password_get_info($oldPassword);
            $hash = ($passwordInfo['algoName'] ?? 'unknown') !== 'unknown'
                ? $oldPassword
                : password_hash($oldPassword, PASSWORD_DEFAULT);
        }
        $userId = (int) $legacyUser['id'];
        $updateUser->bind_param('ssi', $name, $hash, $userId);
        $updateUser->execute();
    }
}

$usernameIndexResult = $conn->query('SHOW INDEX FROM users');
$hasUsernameIndex = false;
while ($index = $usernameIndexResult->fetch_assoc()) {
    if ($index['Column_name'] === 'username' && (int) $index['Non_unique'] === 0) {
        $hasUsernameIndex = true;
        break;
    }
}
if (!$hasUsernameIndex) {
    $conn->query('ALTER TABLE users ADD UNIQUE KEY users_username_unique (username)');
}
$conn->query("CREATE TABLE IF NOT EXISTS orders (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, customer_name VARCHAR(120) NOT NULL, municipality VARCHAR(100) NULL, province VARCHAR(100) NULL, barangay VARCHAR(100) NULL, street_address VARCHAR(255) NULL, customer_email VARCHAR(150) NULL, delivery_address TEXT NULL, contact_number VARCHAR(20) NULL, payment_method ENUM('cod','card') NOT NULL, total_amount DECIMAL(12,2) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT 'Pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX (created_at))");
$orderColumnsResult = $conn->query('SHOW COLUMNS FROM orders');
$orderColumns = [];
while ($column = $orderColumnsResult->fetch_assoc()) {
    $orderColumns[$column['Field']] = true;
}
if (!isset($orderColumns['municipality'])) $conn->query('ALTER TABLE orders ADD municipality VARCHAR(100) NULL');
if (!isset($orderColumns['province'])) $conn->query('ALTER TABLE orders ADD province VARCHAR(100) NULL');
if (!isset($orderColumns['barangay'])) $conn->query('ALTER TABLE orders ADD barangay VARCHAR(100) NULL');
if (!isset($orderColumns['street_address'])) $conn->query('ALTER TABLE orders ADD street_address VARCHAR(255) NULL');
if (!isset($orderColumns['customer_email'])) $conn->query('ALTER TABLE orders ADD customer_email VARCHAR(150) NULL');
if (!isset($orderColumns['user_id'])) $conn->query('ALTER TABLE orders ADD user_id INT NULL');
if (!isset($orderColumns['contact_number'])) $conn->query('ALTER TABLE orders ADD contact_number VARCHAR(20) NULL');
if (!isset($orderColumns['payment_method'])) $conn->query("ALTER TABLE orders ADD payment_method ENUM('cod','card') NOT NULL DEFAULT 'cod'");
if (!isset($orderColumns['total_amount'])) $conn->query('ALTER TABLE orders ADD total_amount DECIMAL(12,2) NOT NULL DEFAULT 0');
if (!isset($orderColumns['status'])) $conn->query("ALTER TABLE orders ADD status VARCHAR(30) NOT NULL DEFAULT 'Pending'");
if (!isset($orderColumns['created_at'])) $conn->query('ALTER TABLE orders ADD created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
if (isset($orderColumns['delivery_address'])) $conn->query('ALTER TABLE orders MODIFY delivery_address TEXT NULL');
if (isset($orderColumns['contact_number'])) $conn->query('ALTER TABLE orders MODIFY contact_number VARCHAR(20) NULL');
$conn->query('CREATE TABLE IF NOT EXISTS order_items (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, product_id INT NULL, product_name VARCHAR(255) NOT NULL, image_path VARCHAR(500) NULL, unit_price DECIMAL(12,2) NOT NULL, quantity INT NOT NULL, FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE)');
$colorsColumn = $conn->query("SHOW COLUMNS FROM products LIKE 'colors'");
if ($colorsColumn && $colorsColumn->num_rows === 0) {
    $conn->query("ALTER TABLE products ADD colors VARCHAR(255) NOT NULL DEFAULT '' AFTER category");
}
$descriptionColumn = $conn->query("SHOW COLUMNS FROM products LIKE 'description'");
if ($descriptionColumn && $descriptionColumn->num_rows === 0) {
    $conn->query("ALTER TABLE products ADD description TEXT NULL AFTER name");
}
?>
