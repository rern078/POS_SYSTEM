<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $pdo = getDBConnection();

    $type = $_GET['type'] ?? '';
    $parent_id = $_GET['parent_id'] ?? null;

    if (empty($type)) {
        http_response_code(400);
        echo json_encode(['error' => 'Product type is required']);
        exit;
    }

    $sql = "SELECT id, name, description, parent_id FROM categories WHERE type = ? AND is_active = 1";
    $params = [$type];

    // If parent_id is provided, get subcategories
    if ($parent_id !== null) {
        $sql .= " AND parent_id = ?";
        $params[] = $parent_id;
    } else {
        // Get main categories (no parent)
        $sql .= " AND parent_id IS NULL";
    }

    $sql .= " ORDER BY sort_order ASC, name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
