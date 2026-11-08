<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function require_customer_login(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['username'])) {
        $requested = $_SERVER['REQUEST_URI'] ?? 'products.php';
        header('Location: index.php?next=' . urlencode($requested));
        exit;
    }
}

function current_username(): string
{
    return htmlspecialchars((string) ($_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
