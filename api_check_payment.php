<?php
/**
 * API para verificar status de pagamento
 */

header('Content-Type: application/json');

include 'includes/db.php';

$saleId = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;

if ($saleId <= 0) {
    echo json_encode(['error' => 'Sale ID inválido']);
    exit;
}

// Busca status da venda
$sale = $conn->query("SELECT status, approved_at FROM rs_sales WHERE id = $saleId")->fetch_assoc();

if (!$sale) {
    echo json_encode(['error' => 'Venda não encontrada']);
    exit;
}

echo json_encode([
    'status' => $sale['status'],
    'approved_at' => $sale['approved_at']
]);
?>