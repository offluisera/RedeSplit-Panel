<?php
header('Content-Type: application/json');
include 'includes/session.php'; // Proteção: Só staff logada acessa
include 'includes/db.php';

if (!isset($_GET['term'])) {
    echo json_encode([]);
    exit;
}

$term = $conn->real_escape_string($_GET['term']);

// Busca permissões que contêm o termo digitado (limite 10 para não travar a tela)
$sql = "SELECT permission FROM rs_known_permissions WHERE permission LIKE '%$term%' LIMIT 10";
$result = $conn->query($sql);

$suggestions = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $suggestions[] = $row['permission'];
    }
}

echo json_encode($suggestions);
?>