<?php
// redis_stream.php - VERSÃO "NUCLEAR" ANTI-BUFFER
include 'includes/session.php'; 

// 1. SEGURANÇA
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['administrador', 'master'])) {
    http_response_code(403); die();
}

// 2. MATAR O BUFFERING (Diferentes métodos para diferentes servidores)
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
    @apache_setenv('dont-vary', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 0);
@ini_set('implicit_flush', 1);

// Fecha sessão para não travar o navegador
session_write_close();

// 3. CABEÇALHOS HTTP
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx
header('Content-Encoding: none');

// Limpa qualquer lixo anterior
while (ob_get_level() > 0) ob_end_flush();
flush();

// 4. O PULO DO GATO: Padding de 8KB
// Envia 8192 espaços vazios para estourar o buffer do Apache imediatamente
echo ":" . str_repeat(" ", 8192) . "\n\n";
flush();

set_time_limit(0);
ini_set('default_socket_timeout', -1);

try {
    // Tenta conectar
    $redis = new Redis();
    
    // Timeout de conexão: 2 segundos (Se demorar mais, é firewall bloqueando)
    $connected = $redis->connect('127.0.0.1', 6379, 2.0);
    
    if (!$connected) {
        throw new Exception("Não conectou ao Redis (Timeout de 2s). Verifique Firewall/IP.");
    }

    $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903'); 
    
    // Timeout de leitura do socket (para o loop não travar pra sempre)
    $redis->setOption(Redis::OPT_READ_TIMEOUT, 10);

    // Envia mensagem de sucesso IMEDIATAMENTE
    $initData = [
        'time' => date('H:i:s'),
        'type' => 'INFO',
        'content' => 'Conexão estabelecida! Monitorando...'
    ];
    echo "data: " . json_encode($initData) . "\n\n";
    flush();

    // Loop Infinito
    while (true) {
        try {
            $redis->subscribe(['redesplit:channel'], function($redis, $channel, $msg) {
                // Filtros
                $type = 'UNKNOWN';
                if (strpos($msg, 'PERFORMANCE|') === 0) $type = 'PERFORMANCE';
                if (strpos($msg, 'BANK_UPDATE') !== false) $type = 'BANK';
                if (strpos($msg, 'CMD') !== false) $type = 'CMD';
                if (strpos($msg, 'PERM_UPDATE') !== false) $type = 'PERM';

                $data = [
                    'time' => date('H:i:s'),
                    'type' => $type,
                    'content' => $msg
                ];
                echo "data: " . json_encode($data) . "\n\n";
                flush(); // IMPORTANTE
            });
        } catch (RedisException $e) {
            $msg = $e->getMessage();
            // Se for timeout de leitura (normal no subscribe), manda heartbeat
            if (strpos($msg, 'read error') !== false || strpos($msg, 'timed out') !== false) {
                echo ": heartbeat\n\n";
                flush();
                continue;
            }
            throw $e;
        }
    }

} catch (Exception $e) {
    // Se der erro, avisa o navegador na hora
    echo "data: " . json_encode(['type' => 'ERROR', 'content' => $e->getMessage()]) . "\n\n";
    flush();
}
?>