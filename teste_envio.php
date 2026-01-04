<?php
// Ativa exibição de todos os erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>🕵️ Diagnóstico de Envio Redis (PHP)</h2>";

try {
    // 1. Tenta carregar a classe
    if (!class_exists('Redis')) {
        throw new Exception("A classe 'Redis' não existe! A extensão do PHP não está instalada ou ativa.");
    }
    echo "✅ Extensão Redis detectada no PHP.<br>";

    // 2. Tenta Conectar
    $redis = new Redis();
    
    // Tenta conectar no IP Local (já que o site está na mesma máquina do Redis)
    // Se falhar, tente mudar para o IP Público da VPS: '82.39.107.62'
    if (!$redis->connect('127.0.0.1', 6379, 2.0)) { 
        throw new Exception("Não foi possível conectar em 127.0.0.1:6379");
    }
    echo "✅ Conectado na porta 6379.<br>";

    // 3. Tenta Autenticar
    $senha = 'UHAFDjbnakfye@@jouiayhfiqwer903';
    if (!$redis->auth($senha)) {
        throw new Exception("A senha foi recusada pelo servidor Redis.");
    }
    echo "✅ Senha aceita.<br>";

    // 4. Tenta Publicar
    $canal = 'redesplit:channel';
    $mensagem = 'MUTE;offluisera;1|Teste PHP para Java';
    
    $recebedores = $redis->publish($canal, $mensagem);
    
    echo "📡 Mensagem enviada: <b>$mensagem</b><br>";
    echo "channel: <b>$canal</b><br><br>";

    if ($recebedores > 0) {
        echo "<h3 style='color:green'>🎉 SUCESSO! $recebedores servidor(es) recebeu(ram) a mensagem.</h3>";
        echo "Olhe o console do Minecraft AGORA. Deve ter aparecido o DEBUG.";
    } else {
        echo "<h3 style='color:orange'>⚠️ ENVIADO, MAS NINGUÉM OUVIU.</h3>";
        echo "O PHP enviou com sucesso, mas o Redis disse que <b>0</b> pessoas receberam.<br>";
        echo "Isso significa que o Java NÃO está inscrito no canal 'redesplit:channel' ou caiu.";
    }

} catch (Exception $e) {
    echo "<h3 style='color:red'>❌ ERRO FATAL:</h3>";
    echo "<b>" . $e->getMessage() . "</b>";
}
?>