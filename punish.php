<?php
include 'includes/session.php';
include 'includes/db.php';
// ATENÇÃO: O Header visual (HTML) só pode ser carregado DEPOIS da lógica de redirecionamento
include 'includes/discord.php';

// --- 1. CONFIGURAÇÃO DE FUSO E LIMPEZA ---
date_default_timezone_set('America/Sao_Paulo');
$conn->query("SET time_zone = '-03:00'");
$conn->query("UPDATE rs_punishments SET active = 0 WHERE expires < NOW() AND expires NOT LIKE '2099%' AND active = 1");

// --- CONTROLE DE PERMISSÃO ---
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
$is_staff = in_array($rank, ['ajudante', 'moderador', 'administrador', 'master']);
$can_ban = in_array($rank, ['moderador', 'administrador', 'master']);

if (!$is_staff) {
    header("Location: index.php");
    exit;
}

$operator = $_SESSION['admin_user'];
$msg = "";

// --- PROCESSAMENTO: APLICAR PUNIÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_punish'])) {
    
    $player = trim($conn->real_escape_string($_POST['player']));
    $type = strtoupper($conn->real_escape_string($_POST['type'])); 
    $reason = trim($conn->real_escape_string($_POST['reason']));
    $duration = (int)$_POST['duration']; 
    $report_id = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;

    if (($type == 'BAN' || $type == 'KICK') && !$can_ban) {
        $msg = "<div class='alert alert-danger'>Erro: Seu cargo permite aplicar apenas MUTE.</div>";
    } else {
        $isActive = ($type === 'KICK') ? 0 : 1; 
        
        if ($duration > 0) {
            $expires = date('Y-m-d H:i:s', strtotime("+$duration minutes"));
        } else {
            $expires = date('2099-01-01 00:00:00'); 
        }

        // 1. SALVA NO MYSQL (Backup e Histórico Visual)
        // Adicionada coluna evidence_url para compatibilidade com o esquema novo, se existir
        // Se der erro de coluna, remova evidence_url daqui.
        $stmt = $conn->prepare("INSERT INTO rs_punishments (player_name, operator, reason, type, expires, active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $player, $operator, $reason, $type, $expires, $isActive);
        
        if ($stmt->execute()) {
            
            // 2. ENVIA PARA O REDIS (Execução Instantânea no Servidor)
            try {
                $redis = new Redis();
                // Ajuste o IP se o Redis não estiver na mesma máquina do PHP
                $redis->connect('127.0.0.1', 6379); 
                $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903'); 

                // CONSTRÓI O COMANDO BUKKIT
                // O Java espera: CMD;ALL;comando
                $cmd = "";
                
                // Sanitização básica para evitar injeção no comando
                $safeReason = str_replace(';', '', $reason); 

                switch ($type) {
                    case 'KICK':
                        $cmd = "kick $player $safeReason (Por: $operator)";
                        break;
                    case 'BAN':
                        // Se for permanente
                        if ($duration <= 0) {
                            $cmd = "ban $player $safeReason (Por: $operator)";
                        } else {
                            // Se for temporário (tempban player tempo motivo)
                            // Converter minutos para formato do Essentials/LiteBans (ex: 10m)
                            $cmd = "tempban $player {$duration}m $safeReason (Por: $operator)";
                        }
                        break;
                    case 'MUTE':
                        if ($duration <= 0) {
                            $cmd = "mute $player $safeReason (Por: $operator)";
                        } else {
                            $cmd = "tempmute $player {$duration}m $safeReason (Por: $operator)";
                        }
                        break;
                }

                if (!empty($cmd)) {
                    // Formato compatível com RedisSubscriber.java
                    $payload = "CMD;ALL;$cmd";
                    $redis->publish('redesplit:channel', $payload);
                }
                
            } catch (Exception $e) {
                // Se o Redis falhar, o código continua (os dados já estão salvos no MySQL)
            }

            // Atualiza status do Report se existir
            if ($report_id > 0) {
                $conn->query("UPDATE rs_reports SET status = 'FECHADO', resolution = '$type', staff_handler = '$operator' WHERE id = $report_id");
            }

            // Envia Log para o Discord
            if (function_exists('sendDiscordLog')) {
                $duration_txt = ($duration > 0) ? "$duration minutos" : "Permanente";
                if ($type == 'KICK') $duration_txt = "Instantâneo";
                
                sendDiscordLog("🔨 Punição Aplicada", "Painel Web", "e74c3c", [
                    ["name" => "Acusado", "value" => $player, "inline" => true],
                    ["name" => "Tipo", "value" => $type, "inline" => true],
                    ["name" => "Staff", "value" => $operator, "inline" => true],
                    ["name" => "Duração", "value" => $duration_txt, "inline" => true]
                ]);
            }
            
            // Redireciona
            header("Location: punish.php?status=success_apply&p=$player&t=$type");
            exit;

        } else {
            $msg = "<div class='alert alert-danger'>Erro SQL: " . $conn->error . "</div>";
        }
    }
}

// --- PROCESSAMENTO: REVOGAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_id'])) {
    if ($can_ban) {
        $id = (int)$_POST['revoke_id'];
        $query = $conn->query("SELECT player_name, type FROM rs_punishments WHERE id = $id");
        
        if ($query && $query->num_rows > 0) {
            $punishment = $query->fetch_assoc();
            
            // 1. ATUALIZA NO MYSQL
            $conn->query("UPDATE rs_punishments SET active = 0 WHERE id = $id");
            
            // 2. ENVIA O UNBAN/UNMUTE PELO REDIS
            try {
                $redis = new Redis();
                $redis->connect('82.39.107.62', 6379);
                $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');

                $cmd = "";
                if ($punishment['type'] == 'BAN') {
                    $cmd = "pardon " . $punishment['player_name']; // ou unban
                } elseif ($punishment['type'] == 'MUTE') {
                    $cmd = "unmute " . $punishment['player_name'];
                }
                
                if (!empty($cmd)) {
                    $payload = "CMD;ALL;$cmd";
                    $redis->publish('redesplit:channel', $payload);
                }

            } catch (Exception $e) {
                // Silêncio
            }
            
            header("Location: punish.php?status=success_revoke");
            exit;
        }
    } else {
        $msg = "<div class='alert alert-danger'>Erro: Sem permissão para revogar.</div>";
    }
}

// --- MENSAGENS PÓS-REDIRECT ---
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success_apply') {
        $p = htmlspecialchars($_GET['p']);
        $t = htmlspecialchars($_GET['t']);
        $msg = "<div class='alert alert-success'>Punição ($t) aplicada com sucesso em <b>$p</b>!</div>";
    } elseif ($_GET['status'] == 'success_revoke') {
        $msg = "<div class='alert alert-success'>Punição revogada com sucesso!</div>";
    }
}

include 'includes/header.php'; 

// Prepara campos automáticos
$pre_player = isset($_GET['player']) ? htmlspecialchars($_GET['player']) : '';
$pre_reason = isset($_GET['reason']) ? htmlspecialchars($_GET['reason']) : '';
$pre_report_id = isset($_GET['report_id']) ? htmlspecialchars($_GET['report_id']) : '';
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <?= $msg ?>
        
        <div class="card shadow-sm border-danger mb-5">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fa-solid fa-gavel"></i> Aplicar Punição</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="apply_punish" value="true">
                    <input type="hidden" name="report_id" value="<?= $pre_report_id ?>">

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Jogador</label>
                            <input type="text" name="player" class="form-control fw-bold" value="<?= $pre_player ?>" placeholder="Nick exato" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Tipo</label>
                            <select name="type" class="form-select">
                                <option value="MUTE">Mute</option>
                                <?php if ($can_ban): ?>
                                    <option value="BAN">Ban</option>
                                    <option value="KICK">Kick</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Duração (Minutos)</label>
                            <input type="number" name="duration" class="form-control" value="0" placeholder="0 = Permanente">
                            <small class="text-muted" style="font-size: 0.75rem">0 = Eterno/Permanente</small>
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-danger w-100 fw-bold">PUNIR</button>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">Motivo</label>
                        <input type="text" name="reason" class="form-control" value="<?= $pre_reason ?>" placeholder="Motivo da punição" required>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-5">
            <div class="card-header bg-white border-bottom border-danger">
                <h5 class="mb-0 text-danger"><i class="fa-solid fa-circle-exclamation"></i> Punições Ativas</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Jogador</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Staff</th>
                            <th>Expira</th>
                            <?php if($can_ban): ?> <th class="text-end">Ação</th> <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql_active = "SELECT * FROM rs_punishments WHERE active = 1 AND type != 'KICK' ORDER BY id DESC LIMIT 15";
                        $res_active = $conn->query($sql_active);
                        
                        if ($res_active && $res_active->num_rows > 0):
                            while ($row = $res_active->fetch_assoc()):
                                $is_perm = (strpos($row['expires'], '2099') !== false);
                                $expires = $is_perm ? '<span class="badge bg-danger">PERMANENTE</span>' : date('d/m H:i', strtotime($row['expires']));
                        ?>
                        <tr>
                            <td class="fw-bold">
                                <img src="https://minotar.net/avatar/<?= $row['player_name'] ?>/20.png" class="rounded-circle me-1">
                                <?= $row['player_name'] ?>
                            </td>
                            <td><span class="badge bg-danger"><?= $row['type'] ?></span></td>
                            <td><?= htmlspecialchars($row['reason']) ?></td>
                            <td><small class="text-muted"><?= $row['operator'] ?></small></td>
                            <td><?= $expires ?></td>
                            <?php if($can_ban): ?>
                            <td class="text-end">
                                <form method="POST" onsubmit="return confirm('Revogar punição?');">
                                    <input type="hidden" name="revoke_id" value="<?= $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Revogar"><i class="fa-solid fa-unlock"></i></button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center py-3 text-muted">Nenhuma punição ativa.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card shadow-sm border-0 bg-light">
             </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>