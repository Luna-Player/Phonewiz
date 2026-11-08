<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_authenticated'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Administrator authentication required.']);
    exit;
}

require 'db.php';
$filter = $_GET['filter'] ?? 'daily';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$month = (int) ($_GET['month'] ?? 0);
$year = (int) ($_GET['year'] ?? 0);
$where = '';
$types = '';
$values = [];

switch ($filter) {
    case 'daily':
        $where = 'DATE(o.created_at) = CURDATE()';
        break;
    case 'weekly':
        $where = 'o.created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND o.created_at < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)';
        break;
    case 'monthly':
        $where = 'YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())';
        break;
    case 'custom':
        $startDate = DateTime::createFromFormat('!Y-m-d', $start);
        $endDate = DateTime::createFromFormat('!Y-m-d', $end);
        if (!$startDate || !$endDate || $startDate->format('Y-m-d') !== $start || $endDate->format('Y-m-d') !== $end || $start > $end) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Choose a valid date range.']);
            exit;
        }
        $where = 'o.created_at >= ? AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
        $types = 'ss';
        $values = [$start, $end];
        break;
    case 'monthyear':
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2200) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Choose a valid month and year.']);
            exit;
        }
        $where = 'YEAR(o.created_at) = ? AND MONTH(o.created_at) = ?';
        $types = 'ii';
        $values = [$year, $month];
        break;
    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unsupported report period.']);
        exit;
}

$sql = "SELECT o.id, o.customer_name, o.created_at, oi.product_name, oi.quantity, oi.unit_price FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id WHERE $where AND o.status <> 'Cancelled' ORDER BY o.created_at, o.id, oi.id";
$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$values);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $id = (int) $row['id'];
    if (!isset($orders[$id])) {
        $orders[$id] = ['id' => $id, 'customer_name' => $row['customer_name'], 'created_at' => $row['created_at'], 'items' => [], 'total' => 0.0];
    }
    if ($row['product_name'] !== null) {
        $quantity = (int) $row['quantity'];
        $price = (float) $row['unit_price'];
        $subtotal = $quantity * $price;
        $orders[$id]['items'][] = ['product_name' => $row['product_name'], 'quantity' => $quantity, 'unit_price' => $price, 'subtotal' => $subtotal];
        $orders[$id]['total'] += $subtotal;
    }
}

echo json_encode(['status' => 'success', 'data' => array_values($orders)], JSON_INVALID_UTF8_SUBSTITUTE);
