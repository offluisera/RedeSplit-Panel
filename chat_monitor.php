<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// Sincroniza fuso horário
date_default_timezone_set('America/Sao_Paulo');
$conn->query("SET time_zone = '-03:00'");

// 1. Função Toxicidade Blindada
if (!function_exists('getPlayerToxicity')) {
    function getPlayerToxicity($conn, $player_name) {
        $player_name = $conn->real_escape_string($player_name);
        $sql = "SELECT COUNT(*) as total FROM rs_filter_alerts 
                WHERE player_name = '$player_name' AND `date` >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $res = $conn->query($sql);
        $count = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['total'] : 0;

        if ($count <= 2) return ['level' => 'Baixo', 'class' => 'bg-success', 'percent' => 20];
        if ($count <= 5) return ['level' => 'Médio', 'class' => 'bg-warning text-dark', 'percent' => 50];
        if ($count <= 10) return ['level' => 'Alto', 'class' => 'bg-orange', 'percent' => 80];
        return ['level' => 'Crítico', 'class' => 'bg-danger', 'percent' => 100];
    }
}

// 2. Dicionário de Palavras
$banned_data = [];
$bw_res = $conn->query("SELECT word, severity FROM rs_banned_words");
if ($bw_res) {
    while($row = $bw_res->fetch_assoc()) { $banned_data[$row['word']] = $row['severity']; }
}

$url_pattern = '/(https?:\/\/[^\s]+|www\.[^\s]+|[a-zA-Z0-0]+\.[a-zA-Z]{2,})/';
?>

<style>
    .bg-orange { background-color: #fd7e14 !important; color: white; }
    .opacity-75 { opacity: 0.75; }
    .progress { height: 4px; background-color: #e9ecef; border-radius: 2px; }
</style>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th style="width: 180px;">Jogador / Info</th>
                    <th>Mensagem</th>
                    <th>Data</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Busca logs de chat com proteção de palavra reservada
                $logs = $conn->query("SELECT * FROM rs_chat_logs ORDER BY `date` DESC LIMIT 50");
                
                if ($logs && $logs->num_rows > 0):
                    while ($l = $logs->fetch_assoc()):
                        $player = $l['player_name'];
                        $msg = htmlspecialchars($l['message']);
                        $msg_date = $l['date']; 
                        $is_suspicious = false;
                        $msg_severity = 'baixo';

                        // Detecção de links e palavras
                        if (preg_match($url_pattern, $msg)) {
                            $is_suspicious = true; $msg_severity = 'alto';
                            $msg = preg_replace($url_pattern, '<span class="text-danger fw-bold">$1</span>', $msg);
                        }
                        foreach ($banned_data as $word => $sev) {
                            if (stripos($msg, $word) !== false) {
                                $is_suspicious = true; $msg_severity = $sev;
                                $msg = str_ireplace($word, '<span class="badge bg-danger">'.$word.'</span>', $msg);
                            }
                        }

                        // Verificação de Punição Blindada
                        $is_resolved = false; $already_punished = false; $staff_name = "";
                        if ($is_suspicious) {
                            $p_esc = $conn->real_escape_string($player);
                            $check_p = $conn->query("SELECT operator, active FROM rs_punishments WHERE player_name = '$p_esc' AND type = 'MUTE' AND `date` >= '$msg_date' ORDER BY `date` DESC LIMIT 1");
                            if ($check_p && $check_p->num_rows > 0) {
                                $row_p = $check_p->fetch_assoc();
                                $is_resolved = true; 
                                $staff_name = $row_p['operator'];
                                if ($row_p['active'] == 1) $already_punished = true;
                            }
                        }

                        $toxicity = getPlayerToxicity($conn, $player);
                        $age = getAccountAge($conn, $player); // Agora deve retornar o tempo
                        $row_class = $is_suspicious ? ($is_resolved ? 'table-secondary opacity-75' : 'table-danger') : '';
                ?>
                <tr class="<?= $row_class ?>">
                    <td>
                        <div class="d-flex flex-column">
                            <span class="fw-bold"><?= $player ?></span>
                            <small class="text-muted" style="font-size: 0.65rem;">
                                <i class="fa-solid fa-clock"></i> Criada há: <b><?= $age ?></b>
                            </small>
                            <div class="progress mt-1" title="Toxicidade: <?= $toxicity['level'] ?>">
                                <div class="progress-bar <?= $toxicity['class'] ?>" style="width: <?= $toxicity['percent'] ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-break"><?= $msg ?></td>
                    <td class="small text-muted"><?= date('H:i:s', strtotime($msg_date)) ?></td>
                    <td class="text-end">
                        <?php if ($already_punished): ?>
                            <button class="btn btn-sm btn-success disabled">PUNIDO (<?= $staff_name ?>)</button>
                        <?php elseif ($is_resolved): ?>
                            <button class="btn btn-sm btn-outline-success disabled">RESOLVIDO</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm <?= $is_suspicious ? 'btn-danger' : 'btn-outline-secondary' ?>" 
                                    onclick="openMute('<?= $player ?>', '<?= $msg_severity ?>')">
                                <i class="fa-solid fa-gavel"></i> Mute
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>