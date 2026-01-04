<?php
include 'includes/session.php';
include 'includes/db.php';

// Enviar Mensagem
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_msg'])) {
    $author = $_SESSION['admin_user'];
    $msg = $conn->real_escape_string(strip_tags($_POST['message']));

    if (!empty($msg)) {
        $conn->query("INSERT INTO rs_global_chat (author, message) VALUES ('$author', '$msg')");
        
        // Notifica o jogo via Redis (Opcional)
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');
            $redis->publish('redesplit:channel', "EXECUTE_CONSOLE;bc §6[ChatWeb] §f$author: §7$msg");
        } catch (Exception $e) {}
    }
    exit;
}

// Carregar Mensagens
if (isset($_GET['fetch'])) {
    $res = $conn->query("SELECT * FROM rs_global_chat ORDER BY id DESC LIMIT 20");
    $messages = [];
    while($row = $res->fetch_assoc()) {
        $messages[] = [
            'author' => $row['author'],
            'message' => htmlspecialchars($row['message']),
            'time' => date('H:i', strtotime($row['created_at']))
        ];
    }
    echo json_encode(array_reverse($messages));
    exit;
}