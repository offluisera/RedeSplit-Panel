<?php
include 'includes/session.php'; // Proteção de sessão
include 'includes/db.php';      // Conexão DB
include 'includes/header.php';  // Navbar e permissões

if (!$is_admin) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h3><i class="fa-solid fa-eye text-primary"></i> Monitoramento de Staff</h3>
        <p class="text-muted">Acompanhe quem está em Vanish ou Staff-Mode agora.</p>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Staff</th>
                            <th>Cargo</th>
                            <th class="text-center">Vanish</th>
                            <th class="text-center">Staff-Mode</th>
                            <th>Última Atividade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Join com rs_players para pegar o Rank atualizado
                        $sql = "SELECT s.*, p.rank_id FROM rs_staff_status s 
                                JOIN rs_players p ON s.player_name = p.name 
                                ORDER BY s.last_update DESC";
                        $result = $conn->query($sql);

                        if ($result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                                $vanish_badge = $row['is_vanished'] ? 'bg-success' : 'bg-secondary opacity-50';
                                $mode_badge = $row['is_staff_mode'] ? 'bg-primary' : 'bg-secondary opacity-50';
                        ?>
                        <tr>
                            <td>
                                <img src="https://minotar.net/avatar/<?= $row['player_name'] ?>/24.png" class="rounded-circle me-2">
                                <b><?= $row['player_name'] ?></b>
                            </td>
                            <td><span class="badge bg-dark"><?= strtoupper($row['rank_id']) ?></span></td>
                            <td class="text-center">
                                <span class="badge <?= $vanish_badge ?>">
                                    <?= $row['is_vanished'] ? '<i class="fa-solid fa-check"></i> ATIVO' : 'INATIVO' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $mode_badge ?>">
                                    <?= $row['is_staff_mode'] ? '<i class="fa-solid fa-ghost"></i> ATIVO' : 'INATIVO' ?>
                                </span>
                            </td>
                            <td class="text-muted small">
                                <?= date('H:i:s - d/m', strtotime($row['last_update'])) ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" class="text-center py-4">Nenhum membro da staff logado no momento.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>