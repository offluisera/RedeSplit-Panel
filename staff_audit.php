<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

if ($_SESSION['user_rank'] !== 'master') {
    echo "<script>window.location.href='index.php';</script>"; exit;
}
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white fw-bold">Auditoria de Ações da Staff</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Data</th>
                    <th>Staff</th>
                    <th>Ação</th>
                    <th>Detalhes</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $logs = $conn->query("SELECT * FROM rs_staff_actions_logs ORDER BY date DESC LIMIT 100");
                while ($row = $logs->fetch_assoc()):
                    $badge = 'bg-secondary';
                    if(strpos($row['action_type'], 'REMOVER') !== false) $badge = 'bg-danger';
                    if(strpos($row['action_type'], 'ADICIONAR') !== false) $badge = 'bg-success';
                ?>
                <tr>
                    <td class="small"><?= date('d/m/Y H:i', strtotime($row['date'])) ?></td>
                    <td><b><?= $row['operator'] ?></b></td>
                    <td><span class="badge <?= $badge ?>"><?= $row['action_type'] ?></span></td>
                    <td class="small"><?= htmlspecialchars($row['details']) ?></td>
                    <td class="text-muted small"><?= $row['ip_address'] ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>