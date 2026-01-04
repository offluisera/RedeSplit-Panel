<?php
// api_restart.php
include 'includes/session.php'; 

// Segurança: Apenas Master
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if ($rank !== 'master') {
    http_response_code(403);
    echo json_encode(['error' => 'Apenas Master pode reiniciar servidores.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['server'])) {
    $target = $_POST['server']; // ID do servidor (ex: survival)
    $time = 5; // Tempo fixo em minutos (pode ser dinâmico no futuro)
    
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379, 2.0);
        $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903'); 

        // Formato: RESTART | ALVO | TEMPO_MINUTOS
        $cmd = "RESTART|$target|$time";
        
        $redis->publish('redesplit:channel', $cmd);
        
        echo json_encode(['success' => true, 'msg' => "Comando enviado para: $target"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro no Redis: ' . $e->getMessage()]);
    }
}
?>