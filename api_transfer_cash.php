<?php
// api_transfer_cash.php
include 'includes/session.php';
include 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método inválido']);
    exit;
}

// --- CORREÇÃO AQUI: Usar a sessão correta ---
$sender = $_SESSION['admin_user']; 
// -------------------------------------------

$target = $conn->real_escape_string($_POST['target_player']);
$amount = (int)$_POST['amount'];

// 1. VALIDAÇÕES BÁSICAS
if ($amount <= 0) {
    echo json_encode(['error' => 'Valor inválido.']);
    exit;
}
if (strtolower($sender) == strtolower($target)) {
    echo json_encode(['error' => 'Você não pode enviar para si mesmo.']);
    exit;
}

// 2. INÍCIO DA TRANSAÇÃO SEGURA
$conn->begin_transaction();

try {
    // A. Verifica Saldo do Remetente (Bloqueando a linha para leitura)
    $stmt = $conn->prepare("SELECT cash FROM rs_players WHERE name = ? FOR UPDATE");
    $stmt->bind_param("s", $sender);
    $stmt->execute();
    $resSender = $stmt->get_result()->fetch_assoc();

    // Se não achar o usuário ou saldo for menor
    if (!$resSender) {
        throw new Exception("Erro: Conta de origem ($sender) não encontrada.");
    }
    if ($resSender['cash'] < $amount) {
        throw new Exception("Saldo insuficiente.");
    }

    // B. Verifica se o Destinatário existe
    $stmt = $conn->prepare("SELECT uuid, cash FROM rs_players WHERE name = ? FOR UPDATE");
    $stmt->bind_param("s", $target);
    $stmt->execute();
    $resTarget = $stmt->get_result()->fetch_assoc();

    if (!$resTarget) {
        throw new Exception("Jogador '$target' não encontrado.");
    }

    // C. Executa a Transferência
    // Tira do Sender
    $conn->query("UPDATE rs_players SET cash = cash - $amount WHERE name = '$sender'");
    
    // Põe no Receiver
    $conn->query("UPDATE rs_players SET cash = cash + $amount WHERE name = '$target'");

    // D. Salva no Log
    $ip = $_SERVER['REMOTE_ADDR'];
    $conn->query("INSERT INTO rs_cash_logs (sender, receiver, amount, ip_sender) VALUES ('$sender', '$target', $amount, '$ip')");

    // CONFIRMA TUDO
    $conn->commit();

    // --- NOTIFICAÇÃO REDIS ---
    try {
        $redis = new Redis();
        // Ajuste IP/Porta conforme sua config real
        $redis->connect('127.0.0.1', 6379, 1); 
        $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');
        
        // Mensagem: CASH_TRANS | QUEM_ENVIOU | QUEM_RECEBEU | VALOR
        $msg = "CASH_TRANS|$sender|$target|$amount";
        $redis->publish('redesplit:channel', $msg);
    } catch (Exception $e) {
        // Ignora erro de Redis
    }

    echo json_encode(['success' => true, 'new_balance' => ($resSender['cash'] - $amount)]);

} catch (Exception $e) {
    $conn->rollback(); // Cancela tudo se der erro
    echo json_encode(['error' => $e->getMessage()]);
}
?>