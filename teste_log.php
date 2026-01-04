<?php
// teste_log.php - Rode isso no navegador para testar
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'includes/session.php';
include 'includes/db.php';

echo "<h2>Diagnóstico de Logs</h2>";

// 1. Verifica Usuário
$user = isset($_SESSION['admin_user']) ? $_SESSION['admin_user'] : 'SISTEMA';
echo "Usuário da Sessão: <b>" . $user . "</b><br>";

if (empty($user)) {
    echo "<h3 style='color:red'>ERRO: Usuário vazio! O log recusa salvar sem nome.</h3>";
    // Tenta forçar um usuário para teste
    $user = "AdminTeste";
}

// 2. Tenta Criar a Tabela via PHP (Caso não tenha criado no SQL)
$sqlCreate = "CREATE TABLE IF NOT EXISTS rs_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(32) NOT NULL,
    action VARCHAR(64) NOT NULL,
    rank_id VARCHAR(32) NOT NULL,
    permission TEXT NOT NULL,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sqlCreate) === TRUE) {
    echo "Tabela 'rs_audit_logs': <span style='color:green'>VERIFICADA/CRIADA</span><br>";
} else {
    echo "Erro ao criar tabela: " . $conn->error . "<br>";
}

// 3. Tenta Inserir um Log de Teste
$stmt = $conn->prepare("INSERT INTO rs_audit_logs (username, action, rank_id, permission) VALUES (?, 'TESTE_DIAGNOSTICO', 'teste', 'permissao.teste')");

if (!$stmt) {
    echo "<h3 style='color:red'>ERRO NO PREPARE: " . $conn->error . "</h3>";
    exit;
}

$stmt->bind_param("s", $user);

if ($stmt->execute()) {
    echo "<h3 style='color:green'>SUCESSO! Log de teste inserido.</h3>";
    echo "Verifique se apareceu no histórico do site agora.";
} else {
    echo "<h3 style='color:red'>FALHA AO INSERIR: " . $stmt->error . "</h3>";
}
?>