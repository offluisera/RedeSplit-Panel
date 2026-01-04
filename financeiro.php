<?php
include 'includes/session.php';
include 'includes/db.php';

$admin_user = $_SESSION['admin_user'];

// --- PROCESSAMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // OPERAÇÃO MANUAL DE SALDO
    if (isset($_POST['manual_transaction'])) {
        $target = $conn->real_escape_string($_POST['username']);
        $type = $_POST['type']; // ADD ou REMOVE
        $currency = $_POST['currency']; // cash ou coins
        $amount = (float)$_POST['amount'];
        $desc = $conn->real_escape_string($_POST['description']);
        
        if ($amount <= 0) {
            header("Location: financeiro.php?msg=invalid_amount");
            exit;
        }

        // 1. Verifica se jogador existe
        $check = $conn->query("SELECT uuid FROM rs_players WHERE name = '$target'");
        if ($check->num_rows > 0) {
            
            // 2. Atualiza o saldo na tabela rs_players
            // Nota: Certifique-se que sua tabela rs_players tem colunas 'cash' e 'coins'
            $operator = ($type == 'ADD') ? '+' : '-';
            $sqlUser = "UPDATE rs_players SET $currency = $currency $operator $amount WHERE name = '$target'";
            $conn->query($sqlUser);

            // 3. Registra a transação
            $sqlLog = "INSERT INTO rs_transactions (username, admin_user, action_type, currency, amount, description) 
                       VALUES ('$target', '$admin_user', '$type', '" . strtoupper($currency) . "', $amount, '$desc')";
            $conn->query($sqlLog);

            // 4. Log de Auditoria
            $conn->query("INSERT INTO rs_audit_logs (username, action, rank_id, permission) 
                          VALUES ('$admin_user', 'FINANCEIRO ($type)', 'N/A', '$amount $currency para $target')");

            header("Location: financeiro.php?msg=success&target=$target");
            exit;
        } else {
            header("Location: financeiro.php?msg=user_not_found");
            exit;
        }
    }
}

include 'includes/header.php';
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

// --- MENSAGENS SWEETALERT ---
$swal_script = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'success') $swal_script = "Swal.fire('Sucesso!', 'Saldo atualizado para o jogador " . htmlspecialchars($_GET['target']) . ".', 'success');";
    if ($_GET['msg'] == 'user_not_found') $swal_script = "Swal.fire('Erro', 'Jogador não encontrado no banco de dados.', 'error');";
    if ($_GET['msg'] == 'invalid_amount') $swal_script = "Swal.fire('Erro', 'O valor deve ser maior que zero.', 'warning');";
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="fa-solid fa-sack-dollar"></i> Financeiro & Economia</h3>
</div>

<?php if($swal_script) echo "<script>$swal_script</script>"; ?>

<div class="row mb-4">
    <?php
    // Soma total de Cash Injetado (ADD)
    $qCash = $conn->query("SELECT SUM(amount) as total FROM rs_transactions WHERE currency='CASH' AND action_type='ADD'");
    $totalCash = $qCash->fetch_assoc()['total'] ?? 0;

    // Soma total de Coins Injetados (ADD)
    $qCoins = $conn->query("SELECT SUM(amount) as total FROM rs_transactions WHERE currency='COINS' AND action_type='ADD'");
    $totalCoins = $qCoins->fetch_assoc()['total'] ?? 0;
    
    // Movimentações hoje
    $qToday = $conn->query("SELECT COUNT(*) as total FROM rs_transactions WHERE DATE(date) = CURDATE()");
    $totalToday = $qToday->fetch_assoc()['total'] ?? 0;
    ?>

    <div class="col-md-4">
        <div class="card shadow-sm border-0 bg-success text-white h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3"><i class="fa-solid fa-gem fa-3x opacity-50"></i></div>
                <div>
                    <h5 class="card-title mb-0">Total Cash (Circulação)</h5>
                    <h2 class="fw-bold mb-0"><?= number_format($totalCash, 0, ',', '.') ?></h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 bg-warning text-dark h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3"><i class="fa-solid fa-coins fa-3x opacity-50"></i></div>
                <div>
                    <h5 class="card-title mb-0">Total Coins (Circulação)</h5>
                    <h2 class="fw-bold mb-0"><?= number_format($totalCoins, 0, ',', '.') ?></h2>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 bg-info text-white h-100">
            <div class="card-body d-flex align-items-center">
                <div class="me-3"><i class="fa-solid fa-chart-line fa-3x opacity-50"></i></div>
                <div>
                    <h5 class="card-title mb-0">Transações Hoje</h5>
                    <h2 class="fw-bold mb-0"><?= $totalToday ?></h2>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="fa-solid fa-hand-holding-dollar"></i> Gerenciar Saldo</h5></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="manual_transaction" value="1">
                    
                    <div class="mb-3">
                        <label class="fw-bold small">Jogador</label>
                        <input type="text" name="username" class="form-control" placeholder="Nick exato" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="fw-bold small">Moeda</label>
                            <select name="currency" class="form-select">
                                <option value="cash">💎 Cash</option>
                                <option value="coins">⛃ Coins</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="fw-bold small">Ação</label>
                            <select name="type" class="form-select">
                                <option value="ADD" class="text-success">Adicionar (+)</option>
                                <option value="REMOVE" class="text-danger">Remover (-)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small">Quantidade</label>
                        <input type="number" name="amount" class="form-control" min="1" step="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small">Motivo / Descrição</label>
                        <input type="text" name="description" class="form-control" placeholder="Ex: Vencedor Evento Spleef" required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-bold" onclick="return confirm('Confirmar movimentação financeira?');">PROCESSAR</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h5 class="mb-0">Últimas Movimentações</h5></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Jogador</th>
                            <th>Ação</th>
                            <th>Valor</th>
                            <th>Admin/Sistema</th>
                            <th>Detalhe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $hist = $conn->query("SELECT * FROM rs_transactions ORDER BY date DESC LIMIT 15");
                        if ($hist->num_rows > 0):
                            while($t = $hist->fetch_assoc()):
                                $color = ($t['action_type'] == 'ADD') ? 'text-success' : 'text-danger';
                                $sign = ($t['action_type'] == 'ADD') ? '+' : '-';
                                $iconCurr = ($t['currency'] == 'CASH') ? '💎' : '⛃';
                        ?>
                        <tr>
                            <td class="text-muted small"><?= date('d/m H:i', strtotime($t['date'])) ?></td>
                            <td class="fw-bold"><?= $t['username'] ?></td>
                            <td>
                                <span class="badge <?= ($t['action_type'] == 'ADD') ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $t['action_type'] ?>
                                </span>
                            </td>
                            <td class="<?= $color ?> fw-bold font-monospace">
                                <?= $sign ?> <?= number_format($t['amount'], 0, ',', '.') ?> <?= $iconCurr ?>
                            </td>
                            <td class="small text-secondary"><?= $t['admin_user'] ?></td>
                            <td class="small text-muted"><?= $t['description'] ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">Nenhuma transação registrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>