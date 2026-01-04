<?php
include 'includes/session.php';
include 'includes/db.php';

echo "<h1>Correção de Trigger</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .ok{color:green;} .erro{color:red;} pre{background:#f5f5f5;padding:10px;border-radius:5px;overflow:auto;}</style>";

// 1. LISTAR TODOS OS TRIGGERS
echo "<h2>1. Triggers Existentes na Tabela rs_players</h2>";
$triggers = $conn->query("SHOW TRIGGERS WHERE `Table` = 'rs_players'");

if ($triggers && $triggers->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
    echo "<tr><th>Nome do Trigger</th><th>Evento</th><th>Timing</th><th>Ação</th></tr>";
    
    $triggerNames = [];
    while ($trigger = $triggers->fetch_assoc()) {
        $triggerNames[] = $trigger['Trigger'];
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($trigger['Trigger']) . "</strong></td>";
        echo "<td>" . $trigger['Event'] . "</td>";
        echo "<td>" . $trigger['Timing'] . "</td>";
        echo "<td><pre style='max-width:600px;'>" . htmlspecialchars($trigger['Statement']) . "</pre></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. OPÇÃO PARA DROPAR OS TRIGGERS PROBLEMÁTICOS
    echo "<h2>2. Remover Triggers Problemáticos</h2>";
    echo "<p>Os triggers acima estão causando o erro. Você pode removê-los clicando nos botões abaixo:</p>";
    
    foreach ($triggerNames as $triggerName) {
        echo "<form method='POST' style='display:inline;margin-right:10px;'>";
        echo "<input type='hidden' name='drop_trigger' value='" . htmlspecialchars($triggerName) . "'>";
        echo "<button type='submit' style='background:#dc3545;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;font-weight:bold;'>REMOVER " . htmlspecialchars($triggerName) . "</button>";
        echo "</form>";
    }
    
    // 3. PROCESSAR REMOÇÃO SE SOLICITADO
    if (isset($_POST['drop_trigger'])) {
        $triggerToDrop = $conn->real_escape_string($_POST['drop_trigger']);
        echo "<h3>Removendo trigger: $triggerToDrop</h3>";
        
        if ($conn->query("DROP TRIGGER IF EXISTS `$triggerToDrop`")) {
            echo "<p class='ok'>✓ Trigger '$triggerToDrop' removido com sucesso!</p>";
            echo "<p><a href='fix_trigger.php' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Atualizar Página</a></p>";
        } else {
            echo "<p class='erro'>✗ Erro ao remover trigger: " . $conn->error . "</p>";
        }
    }
    
} else {
    echo "<p class='ok'>✓ Nenhum trigger encontrado na tabela rs_players</p>";
}

// 4. TESTE APÓS REMOÇÃO
if (!isset($_POST['drop_trigger'])) {
    echo "<h2>3. Testar UPDATE Novamente</h2>";
    $testUpdate = $conn->query("UPDATE rs_players SET splitcoins = 12 WHERE name = 'offluisera'");
    
    if ($testUpdate) {
        echo "<p class='ok'>✓ UPDATE FUNCIONOU! O problema está resolvido!</p>";
        echo "<p><a href='admin_splitcoins.php' style='background:#ffc107;color:#000;padding:15px 30px;text-decoration:none;border-radius:5px;font-weight:bold;'>IR PARA ADMIN SPLITCOINS</a></p>";
    } else {
        echo "<p class='erro'>✗ Ainda há erro: " . $conn->error . "</p>";
        echo "<p>Remova os triggers acima e tente novamente.</p>";
    }
}

echo "<hr>";
echo "<h2>Explicação do Problema</h2>";
echo "<p>Quando você atualiza a tabela <code>rs_players</code>, um <strong>trigger</strong> (gatilho automático) é disparado.</p>";
echo "<p>Este trigger tenta inserir dados em alguma tabela usando a coluna <code>created_at</code>, mas essa coluna não existe na estrutura esperada.</p>";
echo "<p>Removendo o trigger problemático, o UPDATE voltará a funcionar normalmente.</p>";
?>