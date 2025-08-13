<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    $pdo = getDBConnection();
    
    $type = $_GET['type'] ?? '';
    $category_id = $_GET['category_id'] ?? null;
    
    if (empty($type)) {
        http_response_code(400);
        echo json_encode(['error' => 'Product type is required']);
        exit;
    }
    
    $sql = "SELECT s.id, s.name, s.description, s.category_id, c.name as category_name 
            FROM subcategories s 
            LEFT JOIN categories c ON s.category_id = c.id 
            WHERE s.type = ? AND s.is_active = 1";
    $params = [$type];
    
    // If category_id is provided, filter by specific category
    if ($category_id !== null) {
        $sql .= " AND s.category_id = ?";
        $params[] = $category_id;
    }
    
    $sql .= " ORDER BY c.name ASC, s.sort_order ASC, s.name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'subcategories' => $subcategories
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
