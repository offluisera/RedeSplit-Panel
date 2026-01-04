<?php
// ====================================================================
// ARQUIVO: admin_splitcoins.php - VERSÃO CORRIGIDA FINAL
// ====================================================================
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

$admin_user = $_SESSION['admin_user'] ?? $_SESSION['user_name'] ?? 'Admin';

// Proteção de rank
if (!isset($_SESSION['user_rank']) || !in_array($_SESSION['user_rank'], ['administrador', 'master'])) {
    echo "<script>window.location='index.php';</script>"; exit;
}

// Tabela de recompensas
$rewards = [
    ['coins' => 100, 'type' => 'CUPOM', 'value' => '5% OFF', 'description' => 'Cupom de 5% em qualquer compra'],
    ['coins' => 500, 'type' => 'CUPOM', 'value' => '10% OFF', 'description' => 'Cupom de 10% em qualquer compra'],
    ['coins' => 1000, 'type' => 'CUPOM', 'value' => '15% OFF', 'description' => 'Cupom de 15% em qualquer compra'],
    ['coins' => 2000, 'type' => 'CASH', 'value' => 'R$ 10,00', 'description' => 'R$ 10 em créditos'],
    ['coins' => 5000, 'type' => 'PRODUTO', 'value' => 'VIP Ouro', 'description' => 'VIP Ouro grátis'],
    ['coins' => 7000, 'type' => 'PRODUTO', 'value' => 'VIP Elite', 'description' => 'VIP Elite grátis'],
    ['coins' => 35000, 'type' => 'PIX', 'value' => 'R$ 1.500', 'description' => 'Resgate via PIX']
];

$msg = "";

// === ATUALIZAR SALDO ===
if (isset($_POST['update_coins'])) {
    $nick = trim($conn->real_escape_string($_POST['player_nick']));
    $amount = (int)$_POST['amount'];
    $action = $_POST['action'];
    $reason = trim($conn->real_escape_string($_POST['reason'] ?? 'Ajuste manual'));

    $check = $conn->query("SELECT name, splitcoins FROM rs_players WHERE name = '$nick' LIMIT 1");
    
    if ($check && $check->num_rows > 0) {
        $playerData = $check->fetch_assoc();
        $realNick = $playerData['name'];
        $oldBalance = (int)$playerData['splitcoins'];

        if ($action == 'add') {
            $newBalance = $oldBalance + $amount;
            $sql = "UPDATE rs_players SET splitcoins = $newBalance WHERE name = '$realNick'";
            $tipoLog = "ADICIONOU $amount SC";
        } else {
            $newBalance = $amount;
            $sql = "UPDATE rs_players SET splitcoins = $newBalance WHERE name = '$realNick'";
            $tipoLog = "DEFINIU $newBalance SC";
        }

        if ($conn->query($sql)) {
            // Log na auditoria
            $auditCheck = $conn->query("SHOW TABLES LIKE 'rs_audit_logs'");
            if ($auditCheck && $auditCheck->num_rows > 0) {
                $conn->query("INSERT INTO rs_audit_logs (username, action, rank_id, permission) 
                              VALUES ('$admin_user', 'SPLITCOIN: $tipoLog para $realNick - Motivo: $reason', 'ADMIN', 'SPLITCOINS')");
            }
            
            
            $msg = "success|Transação concluída! $realNick: " . number_format($oldBalance, 0, ',', '.') . " → " . number_format($newBalance, 0, ',', '.') . " SC";
        } else {
            $msg = "danger|Erro ao atualizar banco: " . $conn->error;
        }
    } else {
        $msg = "warning|O jogador '$nick' nunca logou no site ou não existe.";
    }
}

// === BUSCAR ESTATÍSTICAS (COM PROTEÇÃO DE ERRO) ===
$totalCoins = 0;
$activeUsers = 0;
$topUser = null;

try {
    $totalResult = $conn->query("SELECT COALESCE(SUM(splitcoins), 0) as total FROM rs_players");
    if ($totalResult) {
        $totalCoins = $totalResult->fetch_assoc()['total'];
    }
    
    $activeResult = $conn->query("SELECT COUNT(*) as total FROM rs_players WHERE splitcoins > 0");
    if ($activeResult) {
        $activeUsers = $activeResult->fetch_assoc()['total'];
    }
    
    $topResult = $conn->query("SELECT name, splitcoins FROM rs_players ORDER BY splitcoins DESC LIMIT 1");
    if ($topResult && $topResult->num_rows > 0) {
        $topUser = $topResult->fetch_assoc();
    }
} catch (Exception $e) {
    // Silenciosamente ignora erros de estatísticas
}
?>

<style>
.coin-manager-container { max-width: 1200px; margin: 40px auto; }
.card-custom { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }
.card-header-gold { background: linear-gradient(45deg, #f39c12, #f1c40f); color: white; padding: 20px; border: none; }
.coin-icon-bg { width: 60px; height: 60px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; }
.form-control-custom { border-radius: 10px; border: 2px solid #eee; padding: 12px; transition: all 0.3s; }
.form-control-custom:focus { border-color: #ffc107; box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25); }
.btn-apply { background: #ffc107; border: none; color: #000; font-weight: 700; border-radius: 10px; padding: 12px; transition: all 0.3s; }
.btn-apply:hover { background: #e0ac06; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3); }
.reward-card { border: 2px solid #e9ecef; border-radius: 10px; padding: 15px; text-align: center; transition: all 0.3s; cursor: pointer; }
.reward-card:hover { border-color: #ffc107; transform: translateY(-3px); box-shadow: 0 5px 15px rgba(255, 193, 7, 0.2); }
.reward-badge { background: linear-gradient(45deg, #ffc107, #ff9800); color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; display: inline-block; }
.stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; padding: 20px; }
</style>

<div class="container coin-manager-container">
    
    <?php if($msg): $data = explode('|', $msg); ?>
        <div class="alert alert-<?= $data[0] ?> alert-dismissible fade show shadow-sm mb-4">
            <i class="fa-solid fa-<?= $data[0] == 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i> 
            <?= $data[1] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Estatísticas -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card text-center">
                <i class="fa-solid fa-coins fa-2x mb-2"></i>
                <h3 class="fw-bold mb-0">
                    <?= number_format($totalCoins, 0, ',', '.') ?>
                </h3>
                <small>Total em Circulação</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="fa-solid fa-users fa-2x mb-2"></i>
                <h3 class="fw-bold mb-0">
                    <?= number_format($activeUsers, 0, ',', '.') ?>
                </h3>
                <small>Usuários com Saldo</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card text-center" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="fa-solid fa-trophy fa-2x mb-2"></i>
                <h3 class="fw-bold mb-0">
                    <?= $topUser ? number_format($topUser['splitcoins'], 0, ',', '.') : '0' ?>
                </h3>
                <small>Maior Saldo: <?= $topUser ? htmlspecialchars($topUser['name']) : 'N/A' ?></small>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Formulário de Gestão -->
        <div class="col-md-6">
            <div class="card card-custom mb-4">
                <div class="card-header-gold text-center">
                    <div class="coin-icon-bg">
                        <i class="fa-solid fa-coins fa-2x text-white"></i>
                    </div>
                    <h4 class="mb-0 fw-bold">Gestão de SplitCoins</h4>
                    <p class="small opacity-75 mb-0">Adicionar ou definir saldo manualmente</p>
                </div>
                
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">NICK DO JOGADOR</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0">
                                    <i class="fa-solid fa-user text-muted"></i>
                                </span>
                                <input type="text" name="player_nick" class="form-control form-control-custom" 
                                       placeholder="Ex: offluisera" required list="playersList">
                                <datalist id="playersList">
                                    <?php 
                                    $playersQuery = $conn->query("SELECT name FROM rs_players ORDER BY name ASC LIMIT 100");
                                    if ($playersQuery) {
                                        while($p = $playersQuery->fetch_assoc()): 
                                    ?>
                                        <option value="<?= htmlspecialchars($p['name']) ?>">
                                    <?php 
                                        endwhile;
                                    }
                                    ?>
                                </datalist>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">QUANTIDADE</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0">
                                    <i class="fa-solid fa-money-bill-transfer text-muted"></i>
                                </span>
                                <input type="number" name="amount" class="form-control form-control-custom" 
                                       placeholder="0" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">TIPO DE OPERAÇÃO</label>
                            <select name="action" class="form-select form-control-custom">
                                <option value="add">➕ Adicionar ao saldo atual</option>
                                <option value="set">🎯 Definir valor exato</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">MOTIVO (Opcional)</label>
                            <input type="text" name="reason" class="form-control form-control-custom" 
                                   placeholder="Ex: Compensação por bug, Promoção especial...">
                        </div>

                        <button type="submit" name="update_coins" class="btn btn-apply w-100">
                            <i class="fa-solid fa-check-circle me-2"></i> CONFIRMAR TRANSAÇÃO
                        </button>
                    </form>

                    <hr class="my-4">

                    <div class="alert alert-info mb-0 small">
                        <h6 class="fw-bold mb-2">
                            <i class="fa-solid fa-lightbulb text-warning me-2"></i> DICAS
                        </h6>
                        <ul class="mb-0 small">
                            <li>Para <strong>remover</strong> moedas, use "Adicionar" com valor <strong>negativo</strong> (ex: -50)</li>
                            <li>As alterações são <strong>instantâneas</strong> no cabeçalho do jogador</li>
                            <li>Todo ajuste é <strong>registrado</strong> no log de auditoria</li>
                            <li><strong>Taxa de Conversão:</strong> R$ 1,00 gasto = 1 SplitCoin</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Recompensas -->
        <div class="col-md-6">
            <div class="card card-custom mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0 fw-bold">
                        <i class="fa-solid fa-gift me-2"></i> Tabela de Recompensas
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <?php foreach($rewards as $reward): 
                            $typeColor = [
                                'CUPOM' => 'warning',
                                'CASH' => 'success',
                                'PRODUTO' => 'primary',
                                'PIX' => 'danger'
                            ];
                            $color = $typeColor[$reward['type']] ?? 'secondary';
                        ?>
                        <div class="col-12">
                            <div class="reward-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="text-start">
                                        <div class="reward-badge mb-2">
                                            <?= number_format($reward['coins'], 0, ',', '.') ?> SC
                                        </div>
                                        <h6 class="fw-bold mb-1"><?= htmlspecialchars($reward['value']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($reward['description']) ?></small>
                                    </div>
                                    <div>
                                        <span class="badge bg-<?= $color ?> p-2">
                                            <?= htmlspecialchars($reward['type']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="alert alert-warning mt-3 mb-0 small">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        <strong>Resgate automático em breve!</strong> Por enquanto, jogadores devem abrir ticket no Discord.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>