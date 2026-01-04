<?php
// api_balance.php - Versão Corrigida
include 'includes/session.php';
include 'includes/db.php';

// Garante que a resposta seja JSON puro
header('Content-Type: application/json');

// Desativa exibição de erros do PHP na tela (quebra o JSON)
error_reporting(0);
ini_set('display_errors', 0);

if (!isset($_GET['server']) || !isset($_GET['type'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Faltam parâmetros', 'val' => 0]);
    exit;
}

$server = $_GET['server'];
$type = $_GET['type'];
$nick = $_SESSION['admin_user']; // Usa o nick da sessão

// Mapeamento idêntico ao do player manager
$db_map = [
    'geral'    => ['table' => 'rs_players',       'col_coins' => 'coins',   'col_cash' => 'cash'],
    'skyblock' => ['table' => 'rs_stats_skyblock','col_coins' => 'balance', 'col_cash' => 'rs_players.cash'], 
    'survival' => ['table' => 'rs_stats_survival','col_coins' => 'balance', 'col_cash' => 'rs_players.cash'],
    'fullpvp'  => ['table' => 'rs_stats_fullpvp', 'col_coins' => 'balance', 'col_cash' => 'rs_players.cash'],
    'rankup'   => ['table' => 'rs_stats_rankup',  'col_coins' => 'balance', 'col_cash' => 'rs_players.cash'],
    'bedwars'  => ['table' => 'rs_stats_bedwars', 'col_coins' => 'coins',   'col_cash' => 'rs_players.cash'],
    'skywars'  => ['table' => 'rs_stats_skywars', 'col_coins' => 'coins',   'col_cash' => 'rs_players.cash']
];

if (!isset($db_map[$server])) {
    echo json_encode(['status' => 'error', 'msg' => 'Servidor inválido', 'val' => 0]);
    exit;
}

$cfg = $db_map[$server];
$table = $cfg['table'];

// Seleciona a coluna correta
if ($type == 'cash') {
    // Cash geralmente é global (rs_players), a menos que especificado
    $col = 'cash';
    $table = 'rs_players'; 
    $where_col = 'name';
} else {
    $col = $cfg['col_coins'];
    // Se for tabela rs_players, a coluna de nome é 'name', senão é 'player_name'
    $where_col = ($table == 'rs_players') ? 'name' : 'player_name';
}

// Query de Busca
$sql = "SELECT $col as saldo FROM $table WHERE $where_col = '$nick' LIMIT 1";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    // Retorna o valor exato
    echo json_encode(['status' => 'success', 'val' => (double)$row['saldo']]);
} else {
    // Se não achou registro, assume que é 0
    echo json_encode(['status' => 'success', 'val' => 0]);
}
?>