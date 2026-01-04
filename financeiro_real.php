<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

$admin_user = $_SESSION['admin_user'];

// --- APROVAR VENDA ---
if (isset($_GET['approve'])) {
    $saleId = (int)$_GET['approve'];
    
    // 1. Busca detalhes da venda
    $sale = $conn->query("SELECT s.*, p.command FROM rs_sales s JOIN rs_products p ON s.product_id = p.id WHERE s.id = $saleId")->fetch_assoc();
    
    if ($sale && $sale['status'] == 'PENDING') {
        // 2. Prepara o comando
        $finalCmd = str_replace('%player%', $sale['player'], $sale['command']);
        
        // 3. Atualiza venda para APROVADO
        $conn->query("UPDATE rs_sales SET status = 'APPROVED', approved_at = NOW() WHERE id = $saleId");
        
        // 4. Insere na Fila de Comandos (Para o Java executar)
        // Dica: Seu plugin Java precisará ler essa tabela a cada X segundos ou usar Redis
        $stmt = $conn->prepare("INSERT INTO rs_command_queue (command) VALUES (?)");
        $stmt->bind_param("s", $finalCmd);
        $stmt->execute();
        
        // 5. Notifica Redis (Opcional, para execução instantânea se tiver listener)
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            $redis->publish('redesplit:channel', "EXECUTE_CONSOLE;PainelFinanceiro;" . $finalCmd);
        } catch (Exception $e) {}

        echo "<script>Swal.fire('Aprovado!', 'Venda confirmada e comando enviado.', 'success').then(() => window.location.href='financeiro_real.php');</script>";
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="fa-solid fa-hand-holding-dollar"></i> Financeiro Real (R$)</h3>
    <a href="admin_products.php" class="btn btn-outline-dark">Gerenciar Produtos</a>
</div>

<div class="row mb-4">
    <?php
    $totalR = $conn->query("SELECT SUM(price_paid) as t FROM rs_sales WHERE status='APPROVED'")->fetch_assoc()['t'] ?? 0;
    $pendR = $conn->query("SELECT SUM(price_paid) as t FROM rs_sales WHERE status='PENDING'")->fetch_assoc()['t'] ?? 0;
    ?>
    <div class="col-md-6">
        <div class="card bg-success text-white shadow-sm border-0">
            <div class="card-body">
                <h5>Receita Total (Aprovada)</h5>
                <h2 class="fw-bold">R$ <?= number_format($totalR, 2, ',', '.') ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-warning text-dark shadow-sm border-0">
            <div class="card-body">
                <h5>Pendente (Aguardando)</h5>
                <h2 class="fw-bold">R$ <?= number_format($pendR, 2, ',', '.') ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">Histórico de Vendas</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#ID</th>
                    <th>Jogador</th> <th>Produto</th>
                    <th>Valor</th>
                    <th>Status</th>
                    <th>Data</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sales = $conn->query("SELECT * FROM rs_sales ORDER BY created_at DESC LIMIT 20");
                while($s = $sales->fetch_assoc()):
                    $statusBadge = $s['status'] == 'APPROVED' ? 'bg-success' : ($s['status'] == 'PENDING' ? 'bg-warning text-dark' : 'bg-danger');
                ?>
                <tr>
                    <td>#<?= $s['id'] ?></td>
                    
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="https://cravatar.eu/helmavatar/<?= $s['player'] ?>/32.png" class="rounded me-2 shadow-sm" width="32" height="32">
                            <span class="fw-bold"><?= $s['player'] ?></span>
                        </div>
                    </td>
                    <td><?= $s['product_name'] ?></td>
                    <td class="text-success fw-bold">R$ <?= number_format($s['price_paid'], 2, ',', '.') ?></td>
                    <td><span class="badge <?= $statusBadge ?>"><?= $s['status'] ?></span></td>
                    <td class="small text-muted"><?= date('d/m H:i', strtotime($s['created_at'])) ?></td>
                    <td class="text-end">
                        <?php if($s['status'] == 'PENDING'): ?>
                            <a href="?approve=<?= $s['id'] ?>" class="btn btn-sm btn-success fw-bold" onclick="return confirm('Confirmar pagamento recebido?');">
                                <i class="fa-solid fa-check"></i> APROVAR
                            </a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-secondary disabled"><i class="fa-solid fa-check-double"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>