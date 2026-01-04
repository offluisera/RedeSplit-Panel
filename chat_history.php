<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// VERIFICAÇÃO DE PERMISSÃO (Apenas Admin e Master)
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['administrador', 'master'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

// Verifica se tem player na URL
if (!isset($_GET['player'])) {
    echo "<script>window.location.href='players.php';</script>";
    exit;
}

$target = $conn->real_escape_string($_GET['player']);
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h3><i class="fa-solid fa-comments text-primary"></i> Histórico de Chat</h3>
            <p class="text-muted mb-0">Visualizando logs de: <b><?= htmlspecialchars($target) ?></b></p>
        </div>
        <a href="players.php?search=<?= $target ?>" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Últimas 100 Mensagens</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 150px;">Data/Hora</th>
                        <th>Mensagem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Busca as últimas 100 mensagens desse jogador
                    $sql = "SELECT * FROM rs_chat_logs WHERE player_name = '$target' ORDER BY date DESC LIMIT 100";
                    $result = $conn->query($sql);

                    if ($result->num_rows > 0):
                        while ($row = $result->fetch_assoc()):
                            $date = date('d/m/Y H:i:s', strtotime($row['date']));
                            $msg = htmlspecialchars($row['message']); // Proteção contra XSS
                    ?>
                    <tr>
                        <td class="text-muted small">
                            <i class="fa-regular fa-clock"></i> <?= $date ?>
                        </td>
                        <td style="word-break: break-all;">
                            <?= $msg ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="2" class="text-center py-5 text-muted">
                            <i class="fa-regular fa-comment-dots fa-2x mb-3"></i><br>
                            Este jogador nunca falou no chat ou o histórico está vazio.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>