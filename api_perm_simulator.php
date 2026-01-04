<?php
include 'includes/session.php';
include 'includes/db.php';

header('Content-Type: application/json');

// 1. Recebe os dados
$nick = isset($_POST['nick']) ? $conn->real_escape_string($_POST['nick']) : '';
$checkPerm = isset($_POST['permission']) ? trim($_POST['permission']) : '';
$serverContext = isset($_POST['server']) ? $_POST['server'] : 'GLOBAL';
$worldContext = isset($_POST['world']) ? $_POST['world'] : 'GLOBAL';

if (empty($nick) || empty($checkPerm)) {
    echo json_encode(['error' => 'Preencha todos os campos.']);
    exit;
}

// 2. Descobre o Rank
$rank_id = 'membro'; // Default
$res = $conn->query("SELECT rank_id FROM rs_players WHERE name = '$nick'");
if ($res && $res->num_rows > 0) {
    $rank_id = $res->fetch_assoc()['rank_id'];
}

// 3. Coleta Permissões Recursivamente
$effective_permissions = [];
$processed_ranks = [];

function collectPermissions($rk, $conn, $server, $world) {
    global $effective_permissions, $processed_ranks;
    if (in_array($rk, $processed_ranks)) return;
    $processed_ranks[] = $rk;

    // Permissões diretas
    $sql = "SELECT permission FROM rs_ranks_permissions 
            WHERE rank_id = ? 
            AND (server_scope = 'GLOBAL' OR server_scope = ?) 
            AND (world_scope = 'GLOBAL' OR world_scope = ?)
            AND (expiration IS NULL OR expiration > NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $rk, $server, $world);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $effective_permissions[] = $row['permission'];

}

collectPermissions($rank_id, $conn, $serverContext, $worldContext);

// 4. Verifica Lógica
$parts = explode('.', $checkPerm);
$wildcardCheck = (count($parts) > 1) ? implode('.', array_slice($parts, 0, -1)) . ".*" : "";

$foundExactNeg = false; $foundExactPos = false;
$foundWildNeg = false; $foundWildPos = false; $foundStar = false;

foreach ($effective_permissions as $p) {
    $isNeg = (substr($p, 0, 1) === '-');
    $cleanP = $isNeg ? substr($p, 1) : $p;

    if ($cleanP === $checkPerm) $isNeg ? $foundExactNeg = true : $foundExactPos = true;
    if ($wildcardCheck && $cleanP === $wildcardCheck) $isNeg ? $foundWildNeg = true : $foundWildPos = true;
    if ($cleanP === '*') if (!$isNeg) $foundStar = true;
}

// Decisão Final
if ($foundExactNeg) { $res = false; $reason = "Bloqueado por: <b>-$checkPerm</b>"; }
elseif ($foundExactPos) { $res = true; $reason = "Permitido por: <b>$checkPerm</b>"; }
elseif ($foundWildNeg) { $res = false; $reason = "Bloqueado por: <b>-$wildcardCheck</b>"; }
elseif ($foundWildPos) { $res = true; $reason = "Permitido por: <b>$wildcardCheck</b>"; }
elseif ($foundStar) { $res = true; $reason = "Permitido por OP/Star (*)"; }
else { $res = false; $reason = "Nenhuma permissão encontrada."; }

echo json_encode(['allowed' => $res, 'reason' => $reason, 'rank' => $rank_id]);
?>