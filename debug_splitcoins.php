<?php
// ====================================================================
// ARQUIVO DE DIAGNÓSTICO - debug_splitcoins.php
// Execute este arquivo ANTES do admin_splitcoins.php
// ====================================================================
include 'includes/session.php';
include 'includes/db.php';

echo "<h1>Diagnóstico do Sistema SplitCoins</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .ok{color:green;} .erro{color:red;} .aviso{color:orange;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// 1. VERIFICAR CONEXÃO
echo "<h2>1. Conexão com Banco de Dados</h2>";
if ($conn) {
    echo "<p class='ok'>✓ Conexão estabelecida</p>";
} else {
    echo "<p class='erro'>✗ Erro na conexão</p>";
    die();
}

// 2. VERIFICAR SE A TABELA rs_players TEM A COLUNA splitcoins
echo "<h2>2. Verificar Coluna splitcoins na Tabela rs_players</h2>";
$checkColumn = $conn->query("SHOW COLUMNS FROM rs_players LIKE 'splitcoins'");
if ($checkColumn && $checkColumn->num_rows > 0) {
    echo "<p class='ok'>✓ Coluna 'splitcoins' existe em rs_players</p>";
} else {
    echo "<p class='erro'>✗ Coluna 'splitcoins' NÃO existe em rs_players</p>";
    echo "<p class='aviso'>EXECUTANDO CORREÇÃO...</p>";
    $addColumn = $conn->query("ALTER TABLE rs_players ADD COLUMN splitcoins INT DEFAULT 0");
    if ($addColumn) {
        echo "<p class='ok'>✓ Coluna adicionada com sucesso!</p>";
    } else {
        echo "<p class='erro'>✗ Erro ao adicionar coluna: " . $conn->error . "</p>";
    }
}

// 3. VERIFICAR SE A TABELA rs_splitcoins_log EXISTE
echo "<h2>3. Verificar Tabela rs_splitcoins_log</h2>";
$checkTable = $conn->query("SHOW TABLES LIKE 'rs_splitcoins_log'");
if ($checkTable && $checkTable->num_rows > 0) {
    echo "<p class='ok'>✓ Tabela 'rs_splitcoins_log' existe</p>";
    
    // Verificar estrutura
    echo "<h3>Estrutura Atual:</h3>";
    $structure = $conn->query("DESCRIBE rs_splitcoins_log");
    echo "<pre>";
    while ($row = $structure->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "</pre>";
    
} else {
    echo "<p class='erro'>✗ Tabela 'rs_splitcoins_log' NÃO existe</p>";
    echo "<p class='aviso'>CRIANDO TABELA...</p>";
    
    $createTable = $conn->query("
        CREATE TABLE rs_splitcoins_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            player VARCHAR(16) NOT NULL,
            amount INT NOT NULL,
            old_balance INT DEFAULT 0,
            new_balance INT DEFAULT 0,
            type ENUM('EARN','SPEND','ADMIN_ADD','ADMIN_SET','REDEEM') NOT NULL,
            reason TEXT,
            admin_user VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_player (player),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    if ($createTable) {
        echo "<p class='ok'>✓ Tabela criada com sucesso!</p>";
    } else {
        echo "<p class='erro'>✗ Erro ao criar tabela: " . $conn->error . "</p>";
    }
}

// 4. TESTAR SELECT NA TABELA
echo "<h2>4. Testar SELECT na Tabela</h2>";
$testSelect = $conn->query("SELECT * FROM rs_splitcoins_log LIMIT 1");
if ($testSelect !== false) {
    echo "<p class='ok'>✓ SELECT funcionando corretamente</p>";
    echo "<p>Registros encontrados: " . $testSelect->num_rows . "</p>";
} else {
    echo "<p class='erro'>✗ Erro no SELECT: " . $conn->error . "</p>";
}

// 5. VERIFICAR VERSÃO DO MySQL
echo "<h2>5. Informações do Servidor</h2>";
$version = $conn->query("SELECT VERSION() as version")->fetch_assoc();
echo "<p>Versão MySQL: " . $version['version'] . "</p>";

// 6. TESTE DE INSERÇÃO
echo "<h2>6. Teste de Inserção</h2>";
$testInsert = $conn->query("
    INSERT INTO rs_splitcoins_log (player, amount, old_balance, new_balance, type, reason, admin_user) 
    VALUES ('TESTE_DEBUG', 100, 0, 100, 'ADMIN_ADD', 'Teste automático', 'SISTEMA')
");

if ($testInsert) {
    echo "<p class='ok'>✓ INSERT funcionando corretamente</p>";
    // Remover registro de teste
    $conn->query("DELETE FROM rs_splitcoins_log WHERE player = 'TESTE_DEBUG'");
} else {
    echo "<p class='erro'>✗ Erro no INSERT: " . $conn->error . "</p>";
}

// 7. VERIFICAR PERMISSÕES
echo "<h2>7. Verificar Permissões do Usuário</h2>";
$grants = $conn->query("SHOW GRANTS");
if ($grants) {
    echo "<pre>";
    while ($row = $grants->fetch_row()) {
        echo $row[0] . "\n";
    }
    echo "</pre>";
} else {
    echo "<p class='aviso'>Não foi possível verificar permissões</p>";
}

// 8. VERIFICAR TIMEZONE
echo "<h2>8. Configuração de Timezone</h2>";
$timezone = $conn->query("SELECT @@global.time_zone, @@session.time_zone")->fetch_assoc();
echo "<p>Global: " . $timezone['@@global.time_zone'] . "</p>";
echo "<p>Session: " . $timezone['@@session.time_zone'] . "</p>";

echo "<hr>";
echo "<h2>RESUMO</h2>";
echo "<p>Se todos os testes acima estão ✓ OK, o sistema deve funcionar corretamente.</p>";
echo "<p>Se ainda houver erros, copie TODA esta página e me envie para análise.</p>";
echo "<br><a href='admin_splitcoins.php' style='background:#ffc107;color:#000;padding:15px 30px;text-decoration:none;border-radius:5px;font-weight:bold;'>TESTAR ADMIN_SPLITCOINS.PHP</a>";
?>