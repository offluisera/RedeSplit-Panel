<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h3><i class="fa-solid fa-trophy text-warning"></i> Ranking de Economia</h3>
        <p class="text-muted">Os jogadores mais poderosos do servidor.</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fa-solid fa-coins"></i> Top Coins (Money)</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Jogador</th>
                            <th class="text-end">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $top_coins = $conn->query("SELECT name, coins, rank_id FROM rs_players ORDER BY coins DESC LIMIT 10");
                        $pos = 1;
                        if ($top_coins->num_rows > 0):
                            while ($row = $top_coins->fetch_assoc()):
                                $medal = "";
                                if($pos == 1) $medal = "🥇";
                                else if($pos == 2) $medal = "🥈";
                                else if($pos == 3) $medal = "🥉";
                        ?>
                        <tr>
                            <td class="fw-bold"><?= $medal ? $medal : $pos ?></td>
                            <td>
                                <img src="https://minotar.net/avatar/<?= $row['name'] ?>/24.png" class="rounded-circle me-2">
                                <b><?= $row['name'] ?></b>
                                <span class="badge bg-secondary ms-2" style="font-size: 0.7em"><?= strtoupper($row['rank_id']) ?></span>
                            </td>
                            <td class="text-end fw-bold text-success">
                                $ <?= number_format($row['coins'], 2, ',', '.') ?>
                            </td>
                        </tr>
                        <?php $pos++; endwhile; else: ?>
                            <tr><td colspan="3" class="text-center p-3">Nenhum dado encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fa-solid fa-gem"></i> Top Cash</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Jogador</th>
                            <th class="text-end">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $top_cash = $conn->query("SELECT name, cash, rank_id FROM rs_players ORDER BY cash DESC LIMIT 10");
                        $pos = 1;
                        if ($top_cash->num_rows > 0):
                            while ($row = $top_cash->fetch_assoc()):
                                $medal = "";
                                if($pos == 1) $medal = "🥇";
                                else if($pos == 2) $medal = "🥈";
                                else if($pos == 3) $medal = "🥉";
                        ?>
                        <tr>
                            <td class="fw-bold"><?= $medal ? $medal : $pos ?></td>
                            <td>
                                <img src="https://minotar.net/avatar/<?= $row['name'] ?>/24.png" class="rounded-circle me-2">
                                <b><?= $row['name'] ?></b>
                            </td>
                            <td class="text-end fw-bold text-warning">
                                ✪ <?= number_format($row['cash'], 0, ',', '.') ?>
                            </td>
                        </tr>
                        <?php $pos++; endwhile; else: ?>
                            <tr><td colspan="3" class="text-center p-3">Nenhum dado encontrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>