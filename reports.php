<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// --- SEGURANÇA ---
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
$my_nick = $_SESSION['admin_user']; // Seu nick

if (!in_array($rank, ['moderador', 'administrador', 'master'])) {
    echo "<script>window.location='moderation.php';</script>";
    exit;
}

$msg = "";

// --- AÇÕES DO SISTEMA ---

// 1. PEGAR O CASO (Claim)
if (isset($_GET['claim'])) {
    $id = (int)$_GET['claim'];
    // Só deixa pegar se ninguém pegou ainda
    $conn->query("UPDATE rs_reports SET status = 'INVESTIGANDO', staff_handler = '$my_nick' WHERE id = $id AND (status = 'ABERTO' OR staff_handler IS NULL)");
    $msg = "<div class='alert alert-info'>🔎 Você assumiu o caso #$id! Investigue e decida o veredito.</div>";
}

// 2. FINALIZAR (Punido ou Inocente)
if (isset($_GET['solve']) && isset($_GET['verdict'])) {
    $id = (int)$_GET['solve'];
    $verdict = $_GET['verdict']; // 'PUNIDO' ou 'INOCENTE'
    
    // Atualiza para status final
    $conn->query("UPDATE rs_reports SET status = 'RESOLVIDO', solved_by = '$my_nick', solved_at = NOW(), reason = CONCAT(reason, ' [Veredito: $verdict]') WHERE id = $id");
    
    $color = ($verdict == 'PUNIDO') ? 'success' : 'secondary';
    $msg = "<div class='alert alert-$color'>⚖️ Caso #$id finalizado como <b>$verdict</b>!</div>";
}

// --- BUSCA: ABERTOS OU INVESTIGANDO ---
// Mostra casos abertos OU casos que EU estou investigando
$reports = $conn->query("SELECT * FROM rs_reports WHERE status = 'ABERTO' OR status = 'INVESTIGANDO' ORDER BY date DESC");
?>

<div class="row mb-4">
    <div class="col-12">
        <h3><i class="fa-solid fa-scale-balanced text-warning"></i> Central de Denúncias</h3>
        <p class="text-muted">Assuma investigações e mantenha a ordem no servidor.</p>
        <?= $msg ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>ID</th>
                        <th>Acusado</th>
                        <th>Motivo</th>
                        <th>Status Atual</th>
                        <th>Ações / Veredito</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($reports && $reports->num_rows > 0): ?>
                        <?php while($row = $reports->fetch_assoc()): ?>
                            
                        <?php 
                            // Lógica de Status
                            $status = strtoupper($row['status']);
                            $handler = $row['staff_handler'];
                            $is_mine = ($handler == $my_nick);
                            
                            // Define cor da linha se estiver sendo investigado
                            $row_class = ($status == 'INVESTIGANDO') ? 'table-warning' : '';
                        ?>

                        <tr class="<?= $row_class ?>">
                            <td>#<?= $row['id'] ?></td>
                            
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="https://minotar.net/avatar/<?= htmlspecialchars($row['reported']) ?>/24" class="rounded-circle me-2">
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($row['reported']) ?></div>
                                        <small class="text-muted">Reportado por: <?= htmlspecialchars($row['reporter']) ?></small>
                                    </div>
                                </div>
                            </td>
                            
                            <td><span class="badge bg-danger"><?= htmlspecialchars($row['reason']) ?></span></td>
                            
                            <td>
                                <?php if ($status == 'ABERTO'): ?>
                                    <span class="badge bg-success">DISPONÍVEL</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fa-solid fa-magnifying-glass"></i> EM ANÁLISE
                                    </span>
                                    <div class="small text-muted mt-1">
                                        Por: <b><?= ($is_mine) ? 'VOCÊ' : $handler ?></b>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                
                                <?php if ($status == 'ABERTO'): ?>
                                    <a href="reports.php?claim=<?= $row['id'] ?>" class="btn btn-sm btn-primary fw-bold">
                                        <i class="fa-solid fa-hand-paper"></i> PEGAR CASO
                                    </a>

                                <?php elseif ($status == 'INVESTIGANDO' && $is_mine): ?>
                                    <div class="btn-group">
                                        <a href="reports.php?solve=<?= $row['id'] ?>&verdict=PUNIDO" class="btn btn-sm btn-outline-success fw-bold" onclick="return confirm('Confirmar punição?')">
                                            <i class="fa-solid fa-gavel"></i> PUNIDO
                                        </a>
                                        <a href="reports.php?solve=<?= $row['id'] ?>&verdict=INOCENTE" class="btn btn-sm btn-outline-secondary fw-bold" onclick="return confirm('Marcar como inocente?')">
                                            <i class="fa-solid fa-xmark"></i> INOCENTE
                                        </a>
                                    </div>

                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled>
                                        <i class="fa-solid fa-lock"></i> EM USO
                                    </button>
                                <?php endif; ?>

                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-check-circle fa-3x mb-3 text-success opacity-50"></i>
                                <p class="mb-0">Tudo limpo! Nenhuma denúncia pendente.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-5">
    <h5 class="text-muted">📚 Últimos Casos Fechados</h5>
    <ul class="list-group">
        <?php
        $history = $conn->query("SELECT * FROM rs_reports WHERE status = 'RESOLVIDO' ORDER BY solved_at DESC LIMIT 5");
        if ($history && $history->num_rows > 0):
            while($h = $history->fetch_assoc()):
                // Detecta se foi punido ou inocentado pela string do motivo (truque simples)
                $is_punished = strpos($h['reason'], 'PUNIDO') !== false;
                $badge_color = $is_punished ? 'bg-danger' : 'bg-secondary';
                $badge_text = $is_punished ? 'PUNIDO' : 'INOCENTE';
                
                // Remove a tag do veredito para mostrar o motivo limpo
                $clean_reason = str_replace([" [Veredito: PUNIDO]", " [Veredito: INOCENTE]"], "", $h['reason']);
        ?>
        <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
            <span>
                <span class="badge <?= $badge_color ?> me-2"><?= $badge_text ?></span>
                <b><?= htmlspecialchars($h['reported']) ?></b> 
                <span class="text-muted">(<?= htmlspecialchars($clean_reason) ?>)</span>
            </span>
            <small class="text-muted">
                Resolvido por <b><?= htmlspecialchars($h['solved_by']) ?></b>
            </small>
        </li>
        <?php endwhile; endif; ?>
    </ul>
</div>

<?php include 'includes/footer.php'; ?>