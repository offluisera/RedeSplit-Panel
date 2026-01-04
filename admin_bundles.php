<?php
session_start();

// --- 1. CORREÇÃO DOS CAMINHOS (INCLUDES) ---
// Tenta encontrar o db.php na pasta 'includes' ou na raiz
if (file_exists('includes/db.php')) {
    include 'includes/db.php';
} elseif (file_exists('../includes/db.php')) { // Caso esteja em subpasta
    include '../includes/db.php';
} elseif (file_exists('db.php')) {
    include 'db.php';
} else {
    die("<b>Erro Crítico:</b> Não foi possível encontrar o arquivo de conexão 'db.php'. Verifique se ele está na pasta 'includes'.");
}

// Tenta encontrar o header.php
if (file_exists('includes/header.php')) {
    include 'includes/header.php';
} elseif (file_exists('header.php')) {
    include 'header.php';
} else {
    // Se não achar o header, cria um HTML básico para não quebrar a página
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<div class="container mt-5 alert alert-warning">Aviso: header.php não encontrado. O visual pode estar incompleto.</div>';
}

// --- 2. SUA LÓGICA DE PERMISSÃO (CORRETA) ---
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
// Verifica se o rank NÃO ESTÁ na lista de permitidos
if (!in_array($rank, ['administrador', 'master'])) {
    echo "<script>
        alert('Acesso Negado! Você não tem permissão para acessar esta página.');
        window.location='index.php';
    </script>";
    exit;
}

// --- 3. LÓGICA DOS BUNDLES (ADICIONAR/REMOVER) ---

// Adicionar Item
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_item'])) {
    $prod_id = intval($_POST['product_id']);
    $name = $conn->real_escape_string($_POST['item_name']);
    $cmd = $conn->real_escape_string($_POST['command']);
    
    // Inserção no banco
    $conn->query("INSERT INTO rs_bundle_items (product_id, item_name, command) VALUES ($prod_id, '$name', '$cmd')");
    
    // Refresh na página para mostrar o item novo
    echo "<script>window.location='admin_bundles.php';</script>";
    exit;
}

// Remover Item
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM rs_bundle_items WHERE id = $id");
    
    // Refresh na página
    echo "<script>window.location='admin_bundles.php';</script>";
    exit;
}

// Busca produtos para o select (Para evitar erro se a tabela estiver vazia)
$products = $conn->query("SELECT * FROM rs_products ORDER BY id DESC");
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12 mb-4">
            <h3 class="fw-bold text-warning"><i class="fa-solid fa-box-open me-2"></i>Gerenciador de Bundles</h3>
            <p class="text-muted">Crie pacotes que executam múltiplos comandos ao serem comprados (Ex: VIP + Coins + Itens).</p>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-warning text-dark fw-bold">
                    <i class="fa-solid fa-plus me-2"></i> Adicionar Item ao Pacote
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">SELECIONE O PACOTE (PRODUTO PAI)</label>
                            <select name="product_id" class="form-select" required>
                                <option value="" selected disabled>-- Escolha um produto --</option>
                                <?php 
                                if ($products && $products->num_rows > 0):
                                    while($p = $products->fetch_assoc()): 
                                ?>
                                    <option value="<?= $p['id'] ?>"><?= $p['name'] ?> (ID: <?= $p['id'] ?>)</option>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                    <option value="" disabled>Nenhum produto cadastrado.</option>
                                <?php endif; ?>
                            </select>
                            <small class="text-muted" style="font-size: 0.7rem;">Primeiro crie o produto na loja normal, depois selecione ele aqui.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">NOME DO ITEM (VISUAL)</label>
                            <input type="text" name="item_name" class="form-control" placeholder="Ex: 30 Dias de VIP" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">COMANDO A EXECUTAR</label>
                            <input type="text" name="command" class="form-control" placeholder="Ex: lp user {player} parent set vip" required>
                            <small class="text-muted" style="font-size: 0.75rem;">Use <b>{player}</b> ou <b>%player%</b> para o nick.</small>
                        </div>
                        <button type="submit" name="add_item" class="btn btn-warning w-100 fw-bold">ADICIONAR ITEM AO PACOTE</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold border-warning border-bottom border-2">
                    <i class="fa-solid fa-list me-2"></i> Itens Configurados nos Pacotes
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3">Produto (Pacote)</th>
                                    <th>Item Interno</th>
                                    <th>Comando</th>
                                    <th class="text-end pe-3">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $bundle_items = $conn->query("
                                    SELECT b.*, p.name as product_name 
                                    FROM rs_bundle_items b 
                                    JOIN rs_products p ON b.product_id = p.id 
                                    ORDER BY b.product_id DESC
                                ");
                                
                                if($bundle_items && $bundle_items->num_rows > 0):
                                    while($item = $bundle_items->fetch_assoc()):
                                ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-warning"><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                                    <td class="text-muted small"><code><?= htmlspecialchars($item['command']) ?></code></td>
                                    <td class="text-end pe-3">
                                        <a href="?delete=<?= $item['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza que deseja remover este item do pacote?');">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="4" class="text-center p-5 text-muted">
                                    <i class="fa-solid fa-box-open fa-2x mb-3 d-block"></i>
                                    Nenhum item de bundle configurado ainda.
                                </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Tenta incluir o footer corretamente
if (file_exists('includes/footer.php')) {
    include 'includes/footer.php';
} elseif (file_exists('footer.php')) {
    include 'footer.php';
}
?>