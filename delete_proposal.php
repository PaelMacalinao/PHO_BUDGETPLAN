<?php
/**
 * AJAX handler — Delete a budget proposal.
 */
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid proposal ID.']);
    exit;
}

try {
    $pdo  = getConnection();
    $stmt = $pdo->prepare("DELETE FROM tbl_budget_proposals WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Proposal not found.']);
    } else {
        echo json_encode(['status' => 'success', 'message' => 'Proposal deleted successfully.']);
    }
} catch (PDOException $e) {
    error_log('Delete Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'A database error occurred.']);
}
