<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

if (!$is_staff) { echo "<script>window.location.href='index.php';</script>"; exit; }
?>

<div class="row mb-4">
    <div class="col-12 text-center">
        <h2 class="fw-bold text-danger"><i class="fa-solid fa-triangle-exclamation"></i> Ranking de Infratores</h2>
        <p class="text-muted">Jogadores que mais dispararam o filtro de palavras proibidas.</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white fw-bold">Top 10 Recorrentes</div>
            <div class="list-group list-group-flush">
                <?php
                $ranking = $conn->query("SELECT player_name, COUNT(*) as total FROM rs_filter_alerts GROUP BY player_name ORDER BY total DESC LIMIT 10");
                $pos = 1;
                while($r = $ranking->fetch_assoc()):
                    $color = ($pos == 1) ? 'text-danger' : (($pos <= 3) ? 'text-warning' : '');
                ?>
                <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                    <div>
                        <span class="fw-bold me-2 <?= $color ?>">#<?= $pos ?></span>
                        <img src="https://minotar.net/avatar/<?= $r['player_name'] ?>/24.png" class="rounded me-2">
                        <span class="fw-bold"><?= $r['player_name'] ?></span>
                    </div>
                    <span class="badge bg-danger rounded-pill"><?= $r['total'] ?> alertas</span>
                </div>
                <?php $pos++; endwhile; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">Atividade Recente do Filtro</div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-sm table-hover mb-0 small">
                        <tbody>
                            <?php
                            $recent = $conn->query("SELECT * FROM rs_filter_alerts ORDER BY date DESC LIMIT 20");
                            while($rec = $recent->fetch_assoc()):
                                $sev_color = ($rec['severity'] == 'alto') ? 'danger' : (($rec['severity'] == 'medio') ? 'warning' : 'info');
                            ?>
                            <tr>
                                <td class="p-2 border-start border-4 border-<?= $sev_color ?>">
                                    <b><?= $rec['player_name'] ?></b>: <span class="text-muted italic">"<?= htmlspecialchars($rec['message']) ?>"</span>
                                    <div class="text-end small opacity-50"><?= date('d/m H:i', strtotime($rec['date'])) ?></div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="alert alert-dark border-0 shadow-sm text-center">
            <i class="fa-solid fa-circle-info me-2 text-info"></i> 
            <b>Dica de Moderador:</b> Jogadores com mais de 10 alertas automáticos em 24h geralmente são bots de spam ou jogadores tóxicos.
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>