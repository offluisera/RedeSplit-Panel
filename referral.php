<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// Verifica se o jogador já tem código
$uuid = $_SESSION['user_uuid']; // Certifique-se que sua session salva o UUID
$my_code = "ERRO";

// 1. Gera código se não existir
$stmt = $conn->prepare("SELECT code FROM rs_referral_codes WHERE uuid = ?");
$stmt->bind_param("s", $uuid);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $my_code = $res->fetch_assoc()['code'];
} else {
    // Gera código: 4 primeiras letras do nick + 3 numeros aleatórios (Ex: LUIZ-921)
    $nick_part = strtoupper(substr($_SESSION['user_name'], 0, 4));
    $rand_part = rand(100, 999);
    $my_code = "$nick_part-$rand_part";
    
    $stmt = $conn->prepare("INSERT INTO rs_referral_codes (uuid, code) VALUES (?, ?)");
    $stmt->bind_param("ss", $uuid, $my_code);
    $stmt->execute();
}
?>

<div class="row justify-content-center mb-5">
    <div class="col-md-8 text-center">
        <h2 class="fw-bold text-primary"><i class="fa-solid fa-users-rays"></i> Sistema de Indicação</h2>
        <p class="text-muted">Convide amigos para jogar na Rede Split! Quando eles completarem <b class="text-dark">1 hora de jogo</b>, ambos ganham prêmios!</p>
    </div>
</div>

<div class="row justify-content-center mb-5">
    <div class="col-md-6">
        <div class="card shadow border-warning">
            <div class="card-body text-center p-5">
                <h5 class="text-muted mb-3">SEU CÓDIGO DE INDICAÇÃO</h5>
                <div class="display-4 fw-bold text-dark letter-spacing-2 mb-3" id="codeText"><?= $my_code ?></div>
                
                <button class="btn btn-warning fw-bold btn-lg w-100" onclick="copyCode()">
                    <i class="fa-regular fa-copy"></i> COPIAR CÓDIGO
                </button>
                <div class="mt-3 text-muted small">
                    <i class="fa-solid fa-circle-info"></i> Seu amigo deve digitar <code>/indique <?= $my_code ?></code> no servidor.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <h4 class="fw-bold mb-3"><i class="fa-solid fa-list-check"></i> Seus Indicados</h4>
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Jogador</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Progresso (Tempo)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Busca quem você convidou
                            $sql = "SELECT r.*, p.name, p.playtime 
                                    FROM rs_referrals r 
                                    JOIN rs_players p ON r.invited_uuid = p.uuid 
                                    WHERE r.inviter_uuid = '$uuid' 
                                    ORDER BY r.status ASC, r.started_at DESC";
                            $result = $conn->query($sql);

                            if ($result->num_rows > 0):
                                while ($row = $result->fetch_assoc()):
                                    // Calcula progresso (1 hora = 3600000 ms)
                                    $playtime_ms = $row['playtime'];
                                    $target_ms = 3600000;
                                    $percent = min(100, round(($playtime_ms / $target_ms) * 100));
                                    $is_completed = ($row['status'] == 'COMPLETED');
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold">
                                    <img src="https://minotar.net/avatar/<?= $row['name'] ?>/24.png" class="rounded-circle me-2">
                                    <?= $row['name'] ?>
                                </td>
                                <td class="text-muted small"><?= date('d/m/Y', strtotime($row['started_at'])) ?></td>
                                <td>
                                    <?php if ($is_completed): ?>
                                        <span class="badge bg-success">RECOMPENSA ENTREGUE</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">JOGANDO...</span>
                                    <?php endif; ?>
                                </td>
                                <td style="width: 30%;">
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?= $is_completed ? 'bg-success' : 'progress-bar-striped progress-bar-animated bg-warning' ?>" 
                                             style="width: <?= $percent ?>%">
                                             <?= $percent ?>%
                                        </div>
                                    </div>
                                    <small class="text-muted"><?= floor($playtime_ms / 60000) ?> min / 60 min</small>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-user-plus fa-2x mb-3"></i><br>
                                    Você ainda não indicou ninguém. Copie seu código acima!
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyCode() {
    var code = document.getElementById("codeText").innerText;
    navigator.clipboard.writeText(code);
    alert("Código " + code + " copiado!");
}
</script>

<?php include 'includes/footer.php'; ?>