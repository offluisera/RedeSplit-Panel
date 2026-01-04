<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// Verifica se é admin
$rank = $_SESSION['user_rank'] ?? 'membro';
if (!in_array($rank, ['administrador', 'master'])) {
    header('Location: index.php');
    exit;
}

// Cria tabela de gateways se não existir
$conn->query("CREATE TABLE IF NOT EXISTS rs_payment_gateways (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    active TINYINT(1) DEFAULT 0,
    test_mode TINYINT(1) DEFAULT 1,
    client_id VARCHAR(255),
    client_secret VARCHAR(255),
    webhook_secret VARCHAR(255),
    config TEXT COMMENT 'JSON com configurações extras',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
)");

// Inicializa MisticPay se não existir
$checkMistic = $conn->query("SELECT * FROM rs_payment_gateways WHERE name = 'misticpay'");
if (!$checkMistic || $checkMistic->num_rows == 0) {
    $conn->query("INSERT INTO rs_payment_gateways (name, display_name, active, test_mode, client_id, client_secret) 
                  VALUES ('misticpay', 'MisticPay', 0, 1, '', '')");
}

// Processa atualização de gateway
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_gateway'])) {
    $gateway_id = (int)$_POST['gateway_id'];
    $active = isset($_POST['active']) ? 1 : 0;
    $test_mode = isset($_POST['test_mode']) ? 1 : 0;
    $client_id = $conn->real_escape_string(trim($_POST['client_id']));
    $client_secret = $conn->real_escape_string(trim($_POST['client_secret']));
    $webhook_secret = $conn->real_escape_string(trim($_POST['webhook_secret'] ?? ''));
    
    $config = json_encode([
        'min_amount' => (float)($_POST['min_amount'] ?? 1.00),
        'max_amount' => (float)($_POST['max_amount'] ?? 10000.00),
        'fee_percentage' => (float)($_POST['fee_percentage'] ?? 0),
        'fee_fixed' => (float)($_POST['fee_fixed'] ?? 0)
    ]);
    
    $sql = "UPDATE rs_payment_gateways SET 
            active = $active,
            test_mode = $test_mode,
            client_id = '$client_id',
            client_secret = '$client_secret',
            webhook_secret = '$webhook_secret',
            config = '$config'
            WHERE id = $gateway_id";
    
    if ($conn->query($sql)) {
        echo "<script>
            Swal.fire({
                icon: 'success',
                title: 'Salvo!',
                text: 'Configurações do gateway atualizadas.',
                timer: 2000
            });
        </script>";
    }
}

// Busca todos os gateways
$gateways = $conn->query("SELECT * FROM rs_payment_gateways ORDER BY name ASC");

// Busca estatísticas de pagamentos
$stats = [];
$statsQuery = $conn->query("SELECT 
    payment_gateway,
    COUNT(*) as total_transactions,
    SUM(CASE WHEN status = 'APPROVED' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'APPROVED' THEN price_paid ELSE 0 END) as total_revenue,
    AVG(CASE WHEN status = 'APPROVED' THEN price_paid ELSE NULL END) as avg_ticket
    FROM rs_sales 
    WHERE payment_gateway IS NOT NULL
    GROUP BY payment_gateway
");

if ($statsQuery) {
    while ($row = $statsQuery->fetch_assoc()) {
        $stats[$row['payment_gateway']] = $row;
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1"><i class="fa-solid fa-credit-card text-primary"></i> Gateways de Pagamento</h3>
            <p class="text-muted mb-0">Configure e monitore suas formas de pagamento</p>
        </div>
        <a href="administration.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left"></i> Voltar
        </a>
    </div>

    <!-- Cards de Estatísticas Gerais -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 rounded p-3">
                                <i class="fa-solid fa-wallet fa-2x text-primary"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">Gateways Ativos</h6>
                            <h3 class="mb-0">
                                <?php
                                $activeCount = $conn->query("SELECT COUNT(*) as total FROM rs_payment_gateways WHERE active = 1")->fetch_assoc()['total'];
                                echo $activeCount;
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 rounded p-3">
                                <i class="fa-solid fa-check-circle fa-2x text-success"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">Transações (30d)</h6>
                            <h3 class="mb-0">
                                <?php
                                $totalTransactions = $conn->query("SELECT COUNT(*) as total FROM rs_sales WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['total'];
                                echo $totalTransactions;
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-warning bg-opacity-10 rounded p-3">
                                <i class="fa-solid fa-dollar-sign fa-2x text-warning"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">Receita (30d)</h6>
                            <h3 class="mb-0">
                                <?php
                                $totalRevenue = $conn->query("SELECT SUM(price_paid) as total FROM rs_sales WHERE status = 'APPROVED' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['total'] ?? 0;
                                echo 'R$ ' . number_format($totalRevenue, 2, ',', '.');
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info bg-opacity-10 rounded p-3">
                                <i class="fa-solid fa-chart-line fa-2x text-info"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">Ticket Médio</h6>
                            <h3 class="mb-0">
                                <?php
                                $avgTicket = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;
                                echo 'R$ ' . number_format($avgTicket, 2, ',', '.');
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Gateways -->
    <div class="row">
        <?php while ($gateway = $gateways->fetch_assoc()): 
            $config = json_decode($gateway['config'] ?? '{}', true);
            $gatewayStats = $stats[$gateway['name']] ?? null;
            
            $iconMap = [
                'misticpay' => 'fa-bolt',
                'mercadopago' => 'fa-shopping-cart',
                'pagarme' => 'fa-credit-card'
            ];
            
            $colorMap = [
                'misticpay' => 'primary',
                'mercadopago' => 'info',
                'pagarme' => 'success'
            ];
            
            $icon = $iconMap[$gateway['name']] ?? 'fa-credit-card';
            $color = $colorMap[$gateway['name']] ?? 'secondary';
        ?>
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-<?= $color ?> bg-opacity-10 border-0 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="fa-solid <?= $icon ?> fa-2x text-<?= $color ?> me-3"></i>
                        <div>
                            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($gateway['display_name']) ?></h5>
                            <small class="text-muted">
                                <?= $gateway['active'] ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>' ?>
                                <?= $gateway['test_mode'] ? '<span class="badge bg-warning">Modo Teste</span>' : '<span class="badge bg-primary">Produção</span>' ?>
                            </small>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-<?= $color ?>" data-bs-toggle="collapse" data-bs-target="#config<?= $gateway['id'] ?>">
                        <i class="fa-solid fa-cog"></i> Configurar
                    </button>
                </div>

                <div class="card-body">
                    <!-- Estatísticas do Gateway -->
                    <?php if ($gatewayStats): ?>
                    <div class="row text-center mb-3">
                        <div class="col-3">
                            <div class="bg-light rounded p-2">
                                <h6 class="mb-0 text-primary fw-bold"><?= $gatewayStats['total_transactions'] ?></h6>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="bg-light rounded p-2">
                                <h6 class="mb-0 text-success fw-bold"><?= $gatewayStats['approved'] ?></h6>
                                <small class="text-muted">Aprovados</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="bg-light rounded p-2">
                                <h6 class="mb-0 text-warning fw-bold">R$ <?= number_format($gatewayStats['total_revenue'], 0, ',', '.') ?></h6>
                                <small class="text-muted">Receita</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="bg-light rounded p-2">
                                <h6 class="mb-0 text-info fw-bold">R$ <?= number_format($gatewayStats['avg_ticket'], 2, ',', '.') ?></h6>
                                <small class="text-muted">Ticket Médio</small>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mb-3">
                        <i class="fa-solid fa-info-circle"></i> Nenhuma transação registrada ainda.
                    </div>
                    <?php endif; ?>

                    <!-- Formulário de Configuração (Colapsável) -->
                    <div class="collapse" id="config<?= $gateway['id'] ?>">
                        <hr>
                        <form method="POST">
                            <input type="hidden" name="update_gateway" value="1">
                            <input type="hidden" name="gateway_id" value="<?= $gateway['id'] ?>">

                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="active" id="active<?= $gateway['id'] ?>" <?= $gateway['active'] ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="active<?= $gateway['id'] ?>">
                                            Gateway Ativo
                                        </label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="test_mode" id="test<?= $gateway['id'] ?>" <?= $gateway['test_mode'] ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="test<?= $gateway['id'] ?>">
                                            Modo Teste
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Client ID</label>
                                <input type="text" name="client_id" class="form-control font-monospace" value="<?= htmlspecialchars($gateway['client_id']) ?>" placeholder="ci_xxxxxxxxxxxx">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Client Secret</label>
                                <div class="input-group">
                                    <input type="password" name="client_secret" id="secret<?= $gateway['id'] ?>" class="form-control font-monospace" value="<?= htmlspecialchars($gateway['client_secret']) ?>" placeholder="cs_xxxxxxxxxxxx">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('secret<?= $gateway['id'] ?>')">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small">Webhook Secret (Opcional)</label>
                                <input type="text" name="webhook_secret" class="form-control font-monospace" value="<?= htmlspecialchars($gateway['webhook_secret'] ?? '') ?>" placeholder="whsec_xxxxxxxxxxxx">
                                <small class="text-muted">Usado para validar webhooks</small>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Valor Mínimo (R$)</label>
                                    <input type="number" name="min_amount" class="form-control" step="0.01" min="0.01" value="<?= $config['min_amount'] ?? 1.00 ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Valor Máximo (R$)</label>
                                    <input type="number" name="max_amount" class="form-control" step="0.01" value="<?= $config['max_amount'] ?? 10000.00 ?>">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Taxa (%) - Opcional</label>
                                    <input type="number" name="fee_percentage" class="form-control" step="0.01" min="0" value="<?= $config['fee_percentage'] ?? 0 ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small">Taxa Fixa (R$) - Opcional</label>
                                    <input type="number" name="fee_fixed" class="form-control" step="0.01" min="0" value="<?= $config['fee_fixed'] ?? 0 ?>">
                                </div>
                            </div>

                            <?php if ($gateway['name'] === 'misticpay'): ?>
                            <div class="alert alert-info mb-3">
                                <strong><i class="fa-solid fa-info-circle"></i> Webhook URL:</strong><br>
                                <code>https://splitstore.com.br/webhooks/misticpay.php</code>
                                <button type="button" class="btn btn-sm btn-outline-info float-end" onclick="copyWebhook()">
                                    <i class="fa-solid fa-copy"></i> Copiar
                                </button>
                            </div>
                            <div class="alert alert-warning mb-3">
                                <strong><i class="fa-solid fa-book"></i> Documentação:</strong><br>
                                <a href="https://docs.misticpay.com" target="_blank" class="text-decoration-none">
                                    docs.misticpay.com <i class="fa-solid fa-external-link-alt small"></i>
                                </a>
                            </div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-<?= $color ?> w-100 fw-bold">
                                <i class="fa-solid fa-save"></i> Salvar Configurações
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- Seção de Logs Recentes -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-clock-rotate-left"></i> Transações Recentes</h5>
            <a href="financeiro_real.php" class="btn btn-sm btn-outline-primary">
                Ver Todas <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Player</th>
                        <th>Produto</th>
                        <th>Valor</th>
                        <th>Gateway</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $recentSales = $conn->query("SELECT * FROM rs_sales ORDER BY created_at DESC LIMIT 10");
                    if ($recentSales && $recentSales->num_rows > 0):
                        while ($sale = $recentSales->fetch_assoc()):
                            $statusColor = [
                                'APPROVED' => 'success',
                                'PENDING' => 'warning',
                                'FAILED' => 'danger',
                                'CANCELLED' => 'secondary'
                            ];
                            $color = $statusColor[$sale['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td><span class="badge bg-secondary">#<?= $sale['id'] ?></span></td>
                        <td class="small"><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></td>
                        <td>
                            <img src="https://cravatar.eu/helmavatar/<?= urlencode($sale['player']) ?>/20.png" class="rounded me-1" width="20" onerror="this.src='https://cravatar.eu/helmavatar/Steve/20.png'">
                            <strong><?= htmlspecialchars($sale['player']) ?></strong>
                        </td>
                        <td class="small"><?= htmlspecialchars(substr($sale['product_name'], 0, 30)) ?><?= strlen($sale['product_name']) > 30 ? '...' : '' ?></td>
                        <td class="fw-bold text-success">R$ <?= number_format($sale['price_paid'], 2, ',', '.') ?></td>
                        <td>
                            <?php if (!empty($sale['payment_gateway'])): ?>
                                <span class="badge bg-primary"><?= htmlspecialchars($sale['payment_gateway']) ?></span>
                            <?php else: ?>
                                <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= $color ?>"><?= $sale['status'] ?></span></td>
                    </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="fa-solid fa-inbox fa-2x mb-2"></i><br>
                            Nenhuma transação registrada ainda.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function togglePassword(id) {
    const input = document.getElementById(id);
    const icon = event.currentTarget.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function copyWebhook() {
    const text = 'https://splitstore.com.br/webhooks/misticpay.php';
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Webhook URL copiada!',
            showConfirmButton: false,
            timer: 2000
        });
    });
}

// Auto-colapsar outros painéis ao abrir um
document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(btn => {
    btn.addEventListener('click', function() {
        const target = this.getAttribute('data-bs-target');
        document.querySelectorAll('.collapse.show').forEach(panel => {
            if ('#' + panel.id !== target) {
                bootstrap.Collapse.getInstance(panel)?.hide();
            }
        });
    });
});
</script>

<style>
.hover-effect {
    transition: transform 0.2s;
}
.hover-effect:hover {
    transform: translateY(-2px);
}
</style>

<?php include 'includes/footer.php'; ?>