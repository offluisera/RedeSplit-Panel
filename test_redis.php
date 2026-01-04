<?php
// Ativar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h3>Testando Conexão com Redis...</h3>";

try {
    // Verifica se a extensão Redis está instalada
    if (!class_exists('Redis')) {
        die("<b style='color:red'>ERRO FATAL:</b> A extensão 'Redis' não está instalada no PHP. Solicite à sua hospedagem ou instale (apt-get install php-redis).");
    }

    $redis = new Redis();
    
    // Tenta conectar (Aumentei o timeout para 2.5s)
    echo "Tentando conectar em 82.39.107.62:6379...<br>";
    $conectou = $redis->connect('82.39.107.62', 6379, 2.5);

    if (!$conectou) {
        die("<b style='color:red'>FALHA:</b> Não foi possível conectar ao servidor. Verifique o IP e a Porta (Firewall?).");
    }

    echo "<b style='color:green'>SUCESSO:</b> Conexão TCP estabelecida!<br>";

    // Tenta autenticar
    echo "Tentando autenticar...<br>";
    $auth = $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');

    if (!$auth) {
        die("<b style='color:red'>FALHA:</b> Senha incorreta.");
    }

    echo "<b style='color:green'>SUCESSO:</b> Senha aceita!<br>";

    // Tenta enviar um PING
    echo "Enviando PING... Resposta: <b>" . $redis->ping() . "</b><br>";

    // Tenta enviar mensagem de teste
    echo "Enviando mensagem de teste para o canal 'redesplit:channel'...<br>";
    $redis->publish('redesplit:channel', 'CMD;ALL;say [Teste] O Site conseguiu falar com o Servidor!');

    echo "<hr><h2 style='color:green'>TUDO FUNCIONANDO!</h2>";
    echo "Se você viu a mensagem '[Teste]' no chat do jogo, a conexão está perfeita.";

} catch (Exception $e) {
    echo "<hr><b style='color:red'>ERRO DE EXCEÇÃO:</b> " . $e->getMessage();
}
?>