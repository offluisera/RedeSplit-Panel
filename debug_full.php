<?php
include 'includes/session.php';
include 'includes/db.php';

echo "<h1>Diagnóstico Completo de Erro</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .ok{color:green;} .erro{color:red;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// TESTE 1: Verificar estrutura de rs_players
echo "<h2>1. Estrutura da tabela rs_players</h2>";
$result = $conn->query("DESCRIBE rs_players");
echo "<pre>";
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
echo "</pre>";

// TESTE 2: Verificar se splitcoins existe
echo "<h2>2. Coluna splitcoins existe?</h2>";
$check = $conn->query("SHOW COLUMNS FROM rs_players LIKE 'splitcoins'");
if ($check->num_rows > 0) {
    echo "<p class='ok'>✓ SIM</p>";
} else {
    echo "<p class='erro'>✗ NÃO - ADICIONAR COLUNA!</p>";
    $conn->query("ALTER TABLE rs_players ADD COLUMN splitcoins INT DEFAULT 0");
}

// TESTE 3: Testar UPDATE simples
echo "<h2>3. Testar UPDATE na tabela rs_players</h2>";
$testUpdate = $conn->query("UPDATE rs_players SET splitcoins = 100 WHERE name = 'offluisera'");
if ($testUpdate) {
    echo "<p class='ok'>✓ UPDATE funcionou!</p>";
    // Reverter
    $conn->query("UPDATE rs_players SET splitcoins = 12 WHERE name = 'offluisera'");
} else {
    echo "<p class='erro'>✗ Erro: " . $conn->error . "</p>";
}

// TESTE 4: Verificar todas as queries que usam created_at
echo "<h2>4. Procurar 'created_at' no banco</h2>";

$tables = ['rs_players', 'rs_audit_logs', 'rs_splitcoins_log'];
foreach ($tables as $table) {
    $checkTable = $conn->query("SHOW TABLES LIKE '$table'");
    if ($checkTable && $checkTable->num_rows > 0) {
        echo "<h3>Tabela: $table</h3>";
        $cols = $conn->query("DESCRIBE $table");
        echo "<pre>";
        $hasCreatedAt = false;
        while ($col = $cols->fetch_assoc()) {
            echo $col['Field'] . " (" . $col['Type'] . ")\n";
            if ($col['Field'] == 'created_at') {
                $hasCreatedAt = true;
            }
        }
        echo "</pre>";
        
        if ($hasCreatedAt) {
            echo "<p class='ok'>✓ Tem 'created_at'</p>";
        } else {
            echo "<p class='erro'>✗ NÃO tem 'created_at'</p>";
        }
    } else {
        echo "<p class='erro'>Tabela $table não existe</p>";
    }
}

// TESTE 5: Simular exatamente o que o código faz
echo "<h2>5. Simulação do Código Real</h2>";

$nick = 'offluisera';
$amount = 10;
$action = 'add';

$check = $conn->query("SELECT name, splitcoins FROM rs_players WHERE name = '$nick' LIMIT 1");

if ($check && $check->num_rows > 0) {
    $playerData = $check->fetch_assoc();
    $realNick = $playerData['name'];
    $oldBalance = (int)$playerData['splitcoins'];
    
    echo "<p>Jogador encontrado: $realNick</p>";
    echo "<p>Saldo atual: $oldBalance</p>";
    
    if ($action == 'add') {
        $newBalance = $oldBalance + $amount;
        $sql = "UPDATE rs_players SET splitcoins = $newBalance WHERE name = '$realNick'";
    } else {
        $newBalance = $amount;
        $sql = "UPDATE rs_players SET splitcoins = $newBalance WHERE name = '$realNick'";
    }
    
    echo "<p><strong>SQL que será executado:</strong></p>";
    echo "<pre>$sql</pre>";
    
    if ($conn->query($sql)) {
        echo "<p class='ok'>✓ UPDATE executado com sucesso!</p>";
        echo "<p>Novo saldo: $newBalance</p>";
        
        // Reverter para o valor original
        $conn->query("UPDATE rs_players SET splitcoins = $oldBalance WHERE name = '$realNick'");
        echo "<p class='ok'>✓ Revertido para o valor original</p>";
    } else {
        echo "<p class='erro'>✗ ERRO NO UPDATE: " . $conn->error . "</p>";
        echo "<p class='erro'>↑↑↑ ESTE É O ERRO QUE ESTÁ ACONTECENDO ↑↑↑</p>";
    }
}

echo "<hr><h2>CONCLUSÃO</h2>";
echo "<p>Se o erro 'Unknown column created_at' apareceu acima, copie a mensagem completa e me envie.</p>";
echo "<p>Se não apareceu erro, o problema está em outra parte do código.</p>";
?>