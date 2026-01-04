<?php
session_start();
if (file_exists('includes/db.php')) include 'includes/db.php'; else include 'db.php';
if (file_exists('includes/header.php')) include 'includes/header.php'; else include 'header.php';

// Permissão
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['administrador', 'master'])) { echo "<script>window.location='index.php';</script>"; exit; }

// Limpar logs antigos (mais de 7 dias)
if (isset($_GET['clean'])) {
    $conn->query("DELETE FROM rs_cart_abandoned WHERE created_at < NOW() - INTERVAL 7 DAY");
    echo "<script>window.location='admin_abandoned.php';</script>";
}

// Deletar um específico (Já recuperado)
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM rs_cart_abandoned WHERE id=$id");
    echo "<script>window.location='admin_abandoned.php';</script>";
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-danger"><i class="fa-solid fa-cart-arrow-down"></i> Carrinhos Abandonados</h3>
            <p class="text-muted">Jogadores que abriram o modal de compra, digitaram o nick, mas não pagaram.</p>
        </div>
        <a href="?clean=1" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Limpar logs antigos?')"><i class="fa-solid fa-broom"></i> Limpar Antigos</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Data/Hora</th>
                            <th>Jogador (Nick)</th>
                            <th>Produto de Interesse</th>
                            <th>Ação Sugerida</th>
                            <th class="text-end">Opções</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Busca os últimos 50 registros
                        $query = $conn->query("SELECT * FROM rs_cart_abandoned ORDER BY id DESC LIMIT 50");
                        if ($query->num_rows > 0):
                            while ($row = $query->fetch_assoc()):
                                // Verifica se esse jogador já comprou este item DEPOIS de abandonar (Recuperado)
                                $checkSale = $conn->query("SELECT id FROM rs_sales WHERE player = '{$row['player']}' AND product_name LIKE '%{$row['product_name']}%' AND created_at > '{$row['created_at']}'");
                                $recovered = ($checkSale->num_rows > 0);
                        ?>
                        <tr class="<?= $recovered ? 'table-success opacity-50' : '' ?>">
                            <td>
                                <small class="fw-bold"><?= date('d/m H:i', strtotime($row['created_at'])) ?></small><br>
                                <small class="text-muted">há <?= intval((time() - strtotime($row['created_at'])) / 60) ?> min</small>
                            </td>
                            <td>
                                <img src="https://cravatar.eu/helmavatar/<?= $row['player'] ?>/24.png" class="me-1 rounded" width="20">
                                <span class="fw-bold text-primary"><?= htmlspecialchars($row['player']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['product_name']) ?></td>
                            <td>
                                <?php if ($recovered): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-check"></i> Compra Realizada!</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-brands fa-discord"></i> Chamar no Discord</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="?del=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" title="Remover"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Nenhum carrinho abandonado recente.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
if (file_exists('includes/footer.php')) include 'includes/footer.php'; else include 'footer.php';
?>