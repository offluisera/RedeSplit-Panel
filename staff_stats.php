<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// Apenas administradores ou masters podem ver o desempenho da equipe
if (!in_array($_SESSION['user_rank'], ['administrador', 'master'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

// Lógica de Filtro de Tempo
$filtro = isset($_GET['periodo']) ? $_GET['periodo'] : 'total';
$where_clause = "";
$titulo_filtro = "Total Histórico";

if ($filtro == 'hoje') {
    $where_clause = "WHERE DATE(date) = CURDATE()";
    $titulo_filtro = "Atividade de Hoje";
} elseif ($filtro == 'mes') {
    $where_clause = "WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())";
    $titulo_filtro = "Atividade deste Mês";
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h4><i class="fa-solid fa-chart-line text-primary"></i> Desempenho da Equipe Staff</h4>
        <p class="text-muted small">Análise de produtividade: <b><?= $titulo_filtro ?></b></p>
    </div>
    <div class="col-md-6 text-end">
        <div class="btn-group shadow-sm">
            <a href="staff_stats.php?periodo=total" class="btn btn-sm <?= $filtro == 'total' ? 'btn-primary' : 'btn-outline-primary' ?>">Tudo</a>
            <a href="staff_stats.php?periodo=mes" class="btn btn-sm <?= $filtro == 'mes' ? 'btn-primary' : 'btn-outline-primary' ?>">Este Mês</a>
            <a href="staff_stats.php?periodo=hoje" class="btn btn-sm <?= $filtro == 'hoje' ? 'btn-primary' : 'btn-outline-primary' ?>">Hoje</a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold border-bottom-0 pt-3">
                Ranking de Operações
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Staff</th>
                            <th class="text-center">Mutes</th>
                            <th class="text-center">Bans</th>
                            <th class="text-center">Kicks</th>
                            <th class="text-center fw-bold">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Consulta SQL dinâmica com base no filtro selecionado
                        $sql = "SELECT operator, 
                                SUM(CASE WHEN type = 'MUTE' THEN 1 ELSE 0 END) as total_mutes,
                                SUM(CASE WHEN type = 'BAN' THEN 1 ELSE 0 END) as total_bans,
                                SUM(CASE WHEN type = 'KICK' THEN 1 ELSE 0 END) as total_kicks,
                                COUNT(*) as total_geral
                                FROM rs_punishments 
                                $where_clause 
                                GROUP BY operator 
                                ORDER BY total_geral DESC";
                        
                        $stats = $conn->query($sql);

                        if ($stats && $stats->num_rows > 0):
                            while($s = $stats->fetch_assoc()):
                                $staff = $s['operator'];
                        ?>
                        <tr>
                            <td>
                                <img src="https://minotar.net/avatar/<?= $staff ?>/24.png" class="rounded me-2 shadow-sm">
                                <b><?= $staff ?></b>
                            </td>
                            <td class="text-center text-info"><?= $s['total_mutes'] ?></td>
                            <td class="text-center text-danger"><?= $s['total_bans'] ?></td>
                            <td class="text-center text-warning"><?= $s['total_kicks'] ?></td>
                            <td class="text-center fw-bold bg-light border-start"><?= $s['total_geral'] ?></td>
                        </tr>
                        <?php 
                            endwhile; 
                        else:
                        ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Nenhuma punição registada neste período.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white fw-bold">Atividade Recente</div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush small">
                    <?php
                    // Mostra os últimos registros filtrados pelo tempo
                    $sql_recent = "SELECT operator, player_name, type, date FROM rs_punishments $where_clause ORDER BY date DESC LIMIT 10";
                    $recent = $conn->query($sql_recent);
                    
                    if ($recent && $recent->num_rows > 0):
                        while($r = $recent->fetch_assoc()):
                            $icon = ($r['type'] == 'MUTE') ? 'comment-slash text-info' : 'gavel text-danger';
                    ?>
                    <div class="list-group-item d-flex justify-content-between align-items-start border-0 border-bottom">
                        <div>
                            <i class="fa-solid fa-<?= $icon ?> me-2"></i>
                            <b><?= $r['operator'] ?></b> &raquo; <b><?= $r['player_name'] ?></b>
                            <div class="text-muted" style="font-size: 0.7rem"><?= date('d/m/Y H:i', strtotime($r['date'])) ?></div>
                        </div>
                        <span class="badge bg-light text-dark border"><?= $r['type'] ?></span>
                    </div>
                    <?php 
                        endwhile;
                    else:
                        echo "<div class='p-4 text-center text-muted'>Sem atividade recente.</div>";
                    endif;
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>