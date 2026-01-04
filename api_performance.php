<?php
// api_performance.php - VERSÃO NUCLEAR (IGUAL AO DEBUG)
include 'includes/session.php'; 

// 1. SEGURANÇA
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['administrador', 'master'])) {
    http_response_code(403); die();
}

// 2. MATAR O BUFFERING (APACHE/NGINX)
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
    @apache_setenv('dont-vary', 1);
}
@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 0);
@ini_set('implicit_flush', 1);

// Fecha sessão para liberar o navegador
session_write_close();

// 3. CABEÇALHOS HTTP
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); 
header('Content-Encoding: none');

// Limpa buffers anteriores
while (ob_get_level() > 0) ob_end_flush();
flush();

// 4. PADDING DE 8KB (O Segredo para destravar o Apache)
echo ":" . str_repeat(" ", 8192) . "\n\n";
flush();

set_time_limit(0);
ini_set('default_socket_timeout', -1);

try {
    $redis = new Redis();
    // Timeout curto (2s) para falhar rápido se não conectar
    $connected = $redis->connect('127.0.0.1', 6379, 2.0);
    
    if (!$connected) {
        throw new Exception("Erro de conexão Redis");
    }

    $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903'); 
    $redis->setOption(Redis::OPT_READ_TIMEOUT, 10); // Timeout de leitura do socket

    // Loop de Escuta
    while (true) {
        try {
            $redis->subscribe(['redesplit:channel'], function($redis, $channel, $msg) {
                
                // Formato esperado: PERFORMANCE|survival|{json}
                if (strpos($msg, 'PERFORMANCE|') === 0) {
                    $parts = explode('|', $msg, 3);
                    
                    if (count($parts) >= 3) {
                        $serverName = $parts[1]; // ex: survival
                        $jsonData   = $parts[2]; // ex: {"tps":...}
                        
                        // Garante que o nome venha limpo
                        if (empty($serverName)) $serverName = "unknown";

                        $packet = [
                            'server' => $serverName,
                            'stats'  => json_decode($jsonData)
                        ];

                        echo "data: " . json_encode($packet) . "\n\n";
                        flush(); // ENVIA IMEDIATAMENTE
                    }
                }
            });
        } catch (RedisException $e) {
            $msg = $e->getMessage();
            // Se for apenas timeout de leitura (normal), manda heartbeat
            if (strpos($msg, 'read error') !== false || strpos($msg, 'timed out') !== false) {
                echo ": heartbeat\n\n";
                flush();
                continue;
            }
            throw $e; // Erro real
        }
    }

} catch (Exception $e) {
    echo "data: " . json_encode(['error' => true, 'msg' => $e->getMessage()]) . "\n\n";
    flush();
}
?>