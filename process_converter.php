<?php
include 'includes/session.php';
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: converter.php");
    exit;
}

$user = $_SESSION['admin_user'];
$server_from = $_POST['server_from'];
$server_to = $_POST['server_to'];
$type = $_POST['currency_type'];
$amount = (double)$_POST['amount'];

// --- 1. Validações Básicas ---
if ($amount <= 0) {
    header("Location: converter.php?status=error&msg=Valor inválido."); exit;
}
if ($server_from == $server_to) {
    header("Location: converter.php?status=error&msg=Servidores iguais."); exit;
}

// --- 2. Busca o UUID (Essencial para criar conta nova no destino) ---
$uuid_query = $conn->query("SELECT uuid FROM rs_players WHERE name = '$user' LIMIT 1");
if (!$uuid_query || $uuid_query->num_rows == 0) {
    header("Location: converter.php?status=error&msg=Jogador não encontrado."); exit;
}
$uuid_row = $uuid_query->fetch_assoc();
$uuid = $uuid_row['uuid'];

// --- 3. Configuração das Tabelas ---
$db_map = [
    'geral'    => ['table' => 'rs_players',       'col_coins' => 'coins',   'col_cash' => 'cash'],
    'skyblock' => ['table' => 'rs_stats_skyblock','col_coins' => 'balance', 'col_cash' => ''], 
    'survival' => ['table' => 'rs_stats_survival','col_coins' => 'balance', 'col_cash' => ''],
    'fullpvp'  => ['table' => 'rs_stats_fullpvp', 'col_coins' => 'balance', 'col_cash' => ''],
    'rankup'   => ['table' => 'rs_stats_rankup',  'col_coins' => 'balance', 'col_cash' => ''],
    'bedwars'  => ['table' => 'rs_stats_bedwars', 'col_coins' => 'coins',   'col_cash' => ''],
    'skywars'  => ['table' => 'rs_stats_skywars', 'col_coins' => 'coins',   'col_cash' => '']
];

$cfg_from = $db_map[$server_from];
$table_from = $cfg_from['table'];
$col_from = ($type == 'cash' && !empty($cfg_from['col_cash'])) ? $cfg_from['col_cash'] : ($type == 'cash' ? 'rs_players.cash' : $cfg_from['col_coins']);

$cfg_to = $db_map[$server_to];
$table_to = $cfg_to['table'];
$col_to = ($type == 'cash' && !empty($cfg_to['col_cash'])) ? $cfg_to['col_cash'] : ($type == 'cash' ? 'rs_players.cash' : $cfg_to['col_coins']);

if ($type == 'cash' && strpos($col_from, 'rs_players') !== false && strpos($col_to, 'rs_players') !== false) {
    header("Location: converter.php?status=error&msg=Cash é global, não precisa converter."); exit;
}

// --- 4. Transação Segura ---
$conn->begin_transaction();

try {
    // A. Verifica e Desconta da Origem
    $col_name_from = ($table_from == 'rs_players') ? 'name' : 'player_name';
    
    if (strpos($col_from, '.') !== false) {
        $check_sql = "SELECT cash as saldo FROM rs_players WHERE name = '$user' FOR UPDATE";
        $update_from_sql = "UPDATE rs_players SET cash = cash - $amount WHERE name = '$user'";
    } else {
        $check_sql = "SELECT $col_from as saldo FROM $table_from WHERE $col_name_from = '$user' FOR UPDATE";
        $update_from_sql = "UPDATE $table_from SET $col_from = $col_from - $amount WHERE $col_name_from = '$user'";
    }

    $res = $conn->query($check_sql);
    if (!$res || $res->num_rows == 0) {
        throw new Exception("Conta de origem não encontrada ou zerada.");
    }
    
    $row = $res->fetch_assoc();
    if ($row['saldo'] < $amount) {
        throw new Exception("Saldo insuficiente no servidor de origem.");
    }

    $conn->query($update_from_sql); // Desconta

    // B. Adiciona no Destino (COM CRIAÇÃO AUTOMÁTICA)
    $tax = 0.05;
    $receive = $amount - ($amount * $tax);

    if (strpos($col_to, '.') !== false) {
        $conn->query("UPDATE rs_players SET cash = cash + $receive WHERE name = '$user'");
    } else {
        $col_dest_name = $cfg_to['col_coins'];
        // Cria a linha se não existir, ou atualiza se existir
        $insert_sql = "INSERT INTO $table_to (uuid, player_name, $col_dest_name, playtime) 
                       VALUES ('$uuid', '$user', $receive, 0) 
                       ON DUPLICATE KEY UPDATE $col_dest_name = $col_dest_name + $receive";
        
        if (!$conn->query($insert_sql)) {
            throw new Exception("Erro ao creditar no destino: " . $conn->error);
        }
    }

    $conn->commit(); // Confirma no Banco

    // --- 5. NOTIFICAÇÃO VIA REDIS (Importante) ---
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379); 
        $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903'); 

        // Formato: BANK_UPDATE|NICK|ORIGEM|TIPO|VALOR
        $msg = "BANK_UPDATE|$user|$server_from|$type|$receive";
        $redis->publish('redesplit:channel', $msg);

    } catch (Exception $e) {
        // Ignora erro do redis, o dinheiro já está no banco
    }

    header("Location: converter.php?status=success&msg=Sucesso! Transferido para " . ucfirst($server_to));

} catch (Exception $e) {
    $conn->rollback();
    header("Location: converter.php?status=error&msg=" . $e->getMessage());
}
?>