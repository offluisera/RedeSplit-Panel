<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

$player = $_SESSION['admin_user']; // Nick do usuário logado
$msg = "";

// 1. ENVIAR APELAÇÃO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['appeal_reason'])) {
    // Verifica se já tem pedido pendente
    $check = $conn->query("SELECT id FROM rs_appeals WHERE player_name='$player' AND status='PENDENTE'");
    
    if ($check->num_rows > 0) {
        $msg = "<div class='alert alert-warning'>⏳ Você já tem uma revisão pendente! Aguarde a resposta.</div>";
    } else {
        $reason = $conn->real_escape_string($_POST['appeal_reason']);
        $conn->query("INSERT INTO rs_appeals (player_name, reason) VALUES ('$player', '$reason')");
        $msg = "<div class='alert alert-success'>✅ Pedido enviado! A Staff analisará em breve.</div>";
    }
}

// 2. BUSCAR STATUS ATUAL
$my_appeals = $conn->query("SELECT * FROM rs_appeals WHERE player_name='$player' ORDER BY created_at DESC LIMIT 5");
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <?= $msg ?>
            
            <div class="card shadow-lg border-0">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0"><i class="fa-solid fa-gavel"></i> Revisão de Banimento</h4>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted">Acha que foi punido injustamente? Explique seu caso abaixo.</p>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="fw-bold">Por que devemos remover sua punição?</label>
                            <textarea name="appeal_reason" class="form-control" rows="5" required placeholder="Explique o que aconteceu, apresente provas se tiver..."></textarea>
                        </div>
                        <button class="btn btn-danger w-100 fw-bold">ENVIAR PEDIDO DE REVISÃO</button>
                    </form>
                </div>
            </div>

            <div class="mt-4">
                <h5>Seus Pedidos Recentes</h5>
                <div class="list-group">
                    <?php while($row = $my_appeals->fetch_assoc()): ?>
                        <?php
                            $status_color = 'warning';
                            if($row['status'] == 'ACEITO') $status_color = 'success';
                            if($row['status'] == 'NEGADO') $status_color = 'danger';
                        ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-<?= $status_color ?>"><?= $row['status'] ?></span>
                                <small class="text-muted ms-2"><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small>
                                <div class="mt-1 small text-dark">"<?= htmlspecialchars($row['reason']) ?>"</div>
                            </div>
                            <?php if($row['status'] != 'PENDENTE'): ?>
                                <small class="text-muted">Analisado por: <b><?= $row['staff_handler'] ?></b></small>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>