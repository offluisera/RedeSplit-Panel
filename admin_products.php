<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// LISTA DE ÍCONES
$available_icons = [
    'fa-crown' => 'Coroa', 'fa-gem' => 'Gema', 'fa-coins' => 'Moedas',
    'fa-box-open' => 'Caixa', 'fa-shield-halved' => 'Escudo', 'fa-user-tag' => 'Tag',
    'fa-rocket' => 'Foguete', 'fa-star' => 'Estrela', 'fa-shirt' => 'Armadura',
    'fa-hammer' => 'Ferramenta', 'fa-skull' => 'Caveira', 'fa-scroll' => 'Pergaminho', 
    'fa-ticket' => 'Ticket', 'fa-gift' => 'Presente', 'fa-fire' => 'Fogo', 'fa-bolt' => 'Raio',
    'fa-heart' => 'Coração', 'fa-trophy' => 'Troféu', 'fa-key' => 'Chave', 'fa-wand-magic-sparkles' => 'Varinha'
];

// Verifica e adiciona colunas necessárias
$checkUpgradeFrom = $conn->query("SHOW COLUMNS FROM rs_products LIKE 'upgrade_from_id'");
if (!$checkUpgradeFrom || $checkUpgradeFrom->num_rows == 0) {
    $conn->query("ALTER TABLE rs_products ADD COLUMN upgrade_from_id INT DEFAULT NULL COMMENT 'ID do produto anterior no upgrade'");
    $conn->query("ALTER TABLE rs_products ADD INDEX idx_upgrade_from (upgrade_from_id)");
}

// --- 1. TESTE DE ENTREGA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_delivery'])) {
    $pid = (int)$_POST['product_id'];
    $nick = trim($conn->real_escape_string($_POST['test_nick']));
    
    if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $nick)) {
        echo "<script>Swal.fire('Erro!', 'Nick inválido! Use apenas letras, números e underline (3-16 caracteres).', 'error');</script>";
    } else {
        $prod = $conn->query("SELECT name, command FROM rs_products WHERE id = $pid")->fetch_assoc();
        
        if ($prod) {
            $finalCommand = str_replace(['%player%', '{player}'], [$nick, $nick], $prod['command']);
            
            // Verifica qual tabela usar
            $tableCheck = $conn->query("SHOW TABLES LIKE 'rs_delivery_queue'");
            $useDeliveryQueue = ($tableCheck && $tableCheck->num_rows > 0);
            
            if ($useDeliveryQueue) {
                $conn->query("INSERT INTO rs_delivery_queue (player_name, command, status) VALUES ('$nick', '$finalCommand', 'PENDING')");
            } else {
                $conn->query("INSERT INTO rs_command_queue (command, status) VALUES ('$finalCommand', 'WAITING')");
            }
            
            // Log de Auditoria
            $admin = $_SESSION['admin_user'] ?? $_SESSION['user_name'] ?? 'Admin';
            $auditCheck = $conn->query("SHOW TABLES LIKE 'rs_audit_logs'");
            if ($auditCheck && $auditCheck->num_rows > 0) {
                $conn->query("INSERT INTO rs_audit_logs (username, action, permission) VALUES ('$admin', 'TESTE ENTREGA: {$prod['name']} -> $nick', 'DEBUG')");
            }

            echo "<script>Swal.fire({
                title: 'Comando Enviado!', 
                html: 'O servidor deve executar:<br><code class=\"text-primary\">$finalCommand</code>',
                icon: 'success',
                timer: 5000
            });</script>";
        } else {
            echo "<script>Swal.fire('Erro!', 'Produto não encontrado!', 'error');</script>";
        }
    }
}

// --- 2. EDITAR PRODUTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $id = (int)$_POST['product_id'];
    $name = trim($conn->real_escape_string($_POST['name']));
    $desc = trim($conn->real_escape_string($_POST['desc']));
    $price = (float)$_POST['price'];
    $old_price = !empty($_POST['old_price']) ? (float)$_POST['old_price'] : "NULL";
    $upsell = !empty($_POST['upsell_product_id']) ? (int)$_POST['upsell_product_id'] : "NULL";
    $upgrade_from = !empty($_POST['upgrade_from_id']) ? (int)$_POST['upgrade_from_id'] : "NULL";
    $stock = !empty($_POST['stock_qty']) ? (int)$_POST['stock_qty'] : "NULL";
    $promo_ends = !empty($_POST['promo_ends_at']) ? "'" . $conn->real_escape_string($_POST['promo_ends_at']) . "'" : "NULL";
    $cmd = trim($conn->real_escape_string($_POST['command']));
    $cat = $conn->real_escape_string($_POST['category']);
    $server = $conn->real_escape_string($_POST['server']);
    $icon = (!empty($_POST['custom_icon'])) ? $_POST['custom_icon'] : $_POST['icon_select'];
    $color = $conn->real_escape_string($_POST['icon_color']);

    if (empty($name) || empty($cmd) || $price <= 0) {
        echo "<script>Swal.fire('Erro!', 'Preencha todos os campos obrigatórios!', 'error');</script>";
    } else {
        if (strpos($cmd, '%player%') === false && strpos($cmd, '{player}') === false) {
            echo "<script>Swal.fire('Atenção!', 'O comando deve conter %player% ou {player} como placeholder.', 'warning');</script>";
        }
        
        $sql = "UPDATE rs_products SET 
                name='$name', 
                description='$desc', 
                price=$price, 
                old_price=$old_price, 
                upsell_product_id=$upsell, 
                upgrade_from_id=$upgrade_from,
                stock_qty=$stock, 
                promo_ends_at=$promo_ends, 
                command='$cmd', 
                category='$cat', 
                server='$server', 
                icon='$icon', 
                icon_color='$color' 
                WHERE id=$id";
        
        if ($conn->query($sql)) {
            echo "<script>Swal.fire('Sucesso', 'Produto atualizado!', 'success').then(() => window.location.href='admin_products.php');</script>";
        } else {
            echo "<script>Swal.fire('Erro!', 'Erro ao atualizar: " . addslashes($conn->error) . "', 'error');</script>";
        }
    }
}

// --- 3. CADASTRO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = trim($conn->real_escape_string($_POST['name']));
    $desc = trim($conn->real_escape_string($_POST['desc']));
    $price = (float)$_POST['price'];
    $old_price = !empty($_POST['old_price']) ? (float)$_POST['old_price'] : "NULL";
    $upsell = !empty($_POST['upsell_product_id']) ? (int)$_POST['upsell_product_id'] : "NULL";
    $upgrade_from = !empty($_POST['upgrade_from_id']) ? (int)$_POST['upgrade_from_id'] : "NULL";
    $stock = !empty($_POST['stock_qty']) ? (int)$_POST['stock_qty'] : "NULL";
    $promo_ends = !empty($_POST['promo_ends_at']) ? "'" . $conn->real_escape_string($_POST['promo_ends_at']) . "'" : "NULL";
    $cmd = trim($conn->real_escape_string($_POST['command']));
    $cat = $conn->real_escape_string($_POST['category']);
    $server = $conn->real_escape_string($_POST['server']);
    $icon = (!empty($_POST['custom_icon'])) ? $_POST['custom_icon'] : $_POST['icon_select'];
    $color = $conn->real_escape_string($_POST['icon_color']);

    if (empty($name) || empty($cmd) || $price <= 0) {
        echo "<script>Swal.fire('Erro!', 'Preencha todos os campos obrigatórios!', 'error');</script>";
    } else {
        if (strpos($cmd, '%player%') === false && strpos($cmd, '{player}') === false) {
            echo "<script>Swal.fire('Atenção!', 'O comando deve conter %player% ou {player} como placeholder.', 'warning');</script>";
        }
        
        $sql = "INSERT INTO rs_products (name, description, price, old_price, upsell_product_id, upgrade_from_id, stock_qty, promo_ends_at, command, category, server, icon, icon_color) 
                VALUES ('$name', '$desc', $price, $old_price, $upsell, $upgrade_from, $stock, $promo_ends, '$cmd', '$cat', '$server', '$icon', '$color')";
        
        if ($conn->query($sql)) {
            echo "<script>Swal.fire('Sucesso', 'Produto criado!', 'success').then(() => window.location.href='admin_products.php');</script>";
        } else {
            echo "<script>Swal.fire('Erro!', 'Erro ao criar produto: " . addslashes($conn->error) . "', 'error');</script>";
        }
    }
}

// --- 4. DUPLICAR PRODUTO ---
if (isset($_GET['duplicate'])) {
    $id = (int)$_GET['duplicate'];
    $prod = $conn->query("SELECT * FROM rs_products WHERE id=$id")->fetch_assoc();
    
    if ($prod) {
        $name = $conn->real_escape_string($prod['name'] . ' (Cópia)');
        $desc = $conn->real_escape_string($prod['description']);
        $cmd = $conn->real_escape_string($prod['command']);
        $cat = $conn->real_escape_string($prod['category']);
        $server = $conn->real_escape_string($prod['server']);
        $icon = $conn->real_escape_string($prod['icon']);
        $color = $conn->real_escape_string($prod['icon_color']);
        $price = (float)$prod['price'];
        
        $sql = "INSERT INTO rs_products (name, description, price, command, category, server, icon, icon_color) 
                VALUES ('$name', '$desc', $price, '$cmd', '$cat', '$server', '$icon', '$color')";
        
        if ($conn->query($sql)) {
            echo "<script>Swal.fire('Sucesso', 'Produto duplicado!', 'success').then(() => window.location.href='admin_products.php');</script>";
        }
    }
}

// --- 5. REMOVER ---
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    
    $checkSales = $conn->query("SELECT COUNT(*) as total FROM rs_sales WHERE product_id = $id")->fetch_assoc();
    
    if ($checkSales['total'] > 0) {
        echo "<script>Swal.fire({
            title: 'Não é possível excluir!',
            html: 'Este produto possui <strong>{$checkSales['total']} vendas</strong> registradas.<br>Você pode desativá-lo ao invés de excluir.',
            icon: 'error'
        });</script>";
    } else {
        $conn->query("DELETE FROM rs_products WHERE id=$id");
        echo "<script>Swal.fire('Sucesso!', 'Produto excluído!', 'success').then(() => window.location.href='admin_products.php');</script>";
    }
}

// Busca produto para edição
$editProduct = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editProduct = $conn->query("SELECT * FROM rs_products WHERE id=$editId")->fetch_assoc();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="fa-solid fa-tags"></i> Gerenciar Produtos</h3>
    <div class="btn-group">
        <a href="financeiro_real.php" class="btn btn-outline-success"><i class="fa-solid fa-money-bill-wave"></i> Ver Vendas</a>
        <a href="admin_bundles.php" class="btn btn-outline-primary"><i class="fa-solid fa-box-open"></i> Pacotes</a>
    </div>
</div>

<!-- Estatísticas Rápidas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fa-solid fa-tags fa-2x text-primary mb-2"></i>
                <h4 class="fw-bold mb-0"><?= $conn->query("SELECT COUNT(*) as total FROM rs_products")->fetch_assoc()['total'] ?? 0 ?></h4>
                <small class="text-muted">Total de Produtos</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fa-solid fa-box-open fa-2x text-warning mb-2"></i>
                <h4 class="fw-bold mb-0"><?= $conn->query("SELECT COUNT(*) as total FROM rs_products WHERE stock_qty IS NOT NULL AND stock_qty <= 5")->fetch_assoc()['total'] ?? 0 ?></h4>
                <small class="text-muted">Estoque Baixo</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fa-solid fa-fire fa-2x text-danger mb-2"></i>
                <h4 class="fw-bold mb-0"><?= $conn->query("SELECT COUNT(*) as total FROM rs_products WHERE promo_ends_at IS NOT NULL AND promo_ends_at > NOW()")->fetch_assoc()['total'] ?? 0 ?></h4>
                <small class="text-muted">Promoções Ativas</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fa-solid fa-arrow-trend-up fa-2x text-success mb-2"></i>
                <h4 class="fw-bold mb-0"><?= $conn->query("SELECT COUNT(*) as total FROM rs_products WHERE upgrade_from_id IS NOT NULL")->fetch_assoc()['total'] ?? 0 ?></h4>
                <small class="text-muted">Com Upgrade</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                <span><?= $editProduct ? '<i class="fa-solid fa-pen"></i> Editar Produto' : '<i class="fa-solid fa-plus"></i> Novo Produto' ?></span>
                <?php if($editProduct): ?>
                    <a href="admin_products.php" class="btn btn-sm btn-light">Cancelar</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" id="productForm">
                    <?php if($editProduct): ?>
                        <input type="hidden" name="edit_product" value="1">
                        <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>">
                    <?php else: ?>
                        <input type="hidden" name="add_product" value="1">
                    <?php endif; ?>
                    
                    <div class="mb-2">
                        <label class="small fw-bold">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="100" value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>">
                    </div>
                    
                    <div class="row g-2 mb-2 p-2 bg-light border rounded mx-0">
                        <div class="col-6">
                            <label class="small fw-bold text-success">Preço (R$) <span class="text-danger">*</span></label>
                            <input type="number" name="price" class="form-control fw-bold border-success" step="0.01" min="0.01" required value="<?= $editProduct['price'] ?? '' ?>">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold text-muted">Preço Antigo</label>
                            <input type="number" name="old_price" class="form-control" step="0.01" min="0" value="<?= $editProduct['old_price'] ?? '' ?>">
                        </div>
                    </div>

                    <!-- NOVO: Sistema de Upgrade Flexível -->
                    <div class="mb-3 border border-info rounded p-3 bg-info bg-opacity-10">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="small fw-bold text-dark mb-0">
                                <i class="fa-solid fa-arrow-trend-up text-info"></i> Este produto é upgrade de:
                            </label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="enableUpgrade" onchange="toggleUpgradeSelect()" <?= !empty($editProduct['upgrade_from_id']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="enableUpgrade">Ativar</label>
                            </div>
                        </div>
                        <select name="upgrade_from_id" id="upgradeFromSelect" class="form-select form-select-sm" <?= empty($editProduct['upgrade_from_id']) ? 'disabled' : '' ?>>
                            <option value="">-- Selecione o produto anterior --</option>
                            <?php 
                            $upgradeList = $conn->query("SELECT id, name, price FROM rs_products ORDER BY price ASC, name ASC");
                            if($upgradeList): 
                                while($up = $upgradeList->fetch_assoc()): 
                                    $selected = ($editProduct && $editProduct['upgrade_from_id'] == $up['id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $up['id'] ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($up['name']) ?> (R$ <?= number_format($up['price'], 2) ?>)
                                </option>
                            <?php 
                                endwhile;
                            endif; 
                            ?>
                        </select>
                        <div class="form-text small mt-2">
                            <i class="fa-solid fa-lightbulb text-warning"></i> 
                            <strong>Como funciona:</strong> Se o jogador já possui o produto selecionado acima, ele pagará apenas a diferença de preço ao comprar este.
                        </div>
                    </div>

                    <div class="mb-3 border border-warning rounded p-2 bg-warning bg-opacity-10">
                        <label class="small fw-bold text-dark"><i class="fa-solid fa-fire text-danger"></i> Upsell (Oferta Adicional)</label>
                        <select name="upsell_product_id" class="form-select form-select-sm">
                            <option value="">-- Nenhum --</option>
                            <?php 
                            $plList = $conn->query("SELECT id, name, price FROM rs_products ORDER BY name ASC");
                            if($plList): 
                                while($pl = $plList->fetch_assoc()): 
                                    $selected = ($editProduct && $editProduct['upsell_product_id'] == $pl['id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $pl['id'] ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($pl['name']) ?> (+ R$ <?= number_format($pl['price'], 2) ?>)
                                </option>
                            <?php 
                                endwhile;
                            endif; 
                            ?>
                        </select>
                        <div class="form-text small">
                            <i class="fa-solid fa-info-circle"></i> Oferta exibida no checkout para aumentar ticket médio
                        </div>
                    </div>
                    
                    <div class="mb-2 p-2 border rounded bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="small fw-bold text-danger"><i class="fa-solid fa-box-open"></i> Estoque Limitado?</label>
                            <input type="number" name="stock_qty" class="form-control form-control-sm w-50" placeholder="Vazio = Infinito" min="0" value="<?= $editProduct['stock_qty'] ?? '' ?>">
                        </div>
                        <div class="form-text small" style="font-size: 0.7rem;">
                            Se preencher, o item irá parar de vender quando chegar a 0.
                        </div>
                    </div>

                    <div class="mb-2 p-2 border rounded bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="small fw-bold text-danger"><i class="fa-solid fa-stopwatch"></i> Flash Sale (Fim)</label>
                            <input type="datetime-local" name="promo_ends_at" class="form-control form-control-sm w-50" value="<?= $editProduct['promo_ends_at'] ?? '' ?>">
                        </div>
                        <div class="form-text small" style="font-size: 0.7rem;">
                            Deixe vazio para não ter contador regressivo.
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="small fw-bold">Categoria</label>
                            <select name="category" class="form-select">
                                <option value="VIPS" <?= ($editProduct && $editProduct['category'] == 'VIPS') ? 'selected' : '' ?>>VIPS</option>
                                <option value="TAGS" <?= ($editProduct && $editProduct['category'] == 'TAGS') ? 'selected' : '' ?>>TAGS</option>
                                <option value="COINS" <?= ($editProduct && $editProduct['category'] == 'COINS') ? 'selected' : '' ?>>COINS</option>
                                <option value="CASH" <?= ($editProduct && $editProduct['category'] == 'CASH') ? 'selected' : '' ?>>CASH</option>
                                <option value="CAIXAS" <?= ($editProduct && $editProduct['category'] == 'CAIXAS') ? 'selected' : '' ?>>CAIXAS</option>
                                <option value="ESPECIAIS" <?= ($editProduct && $editProduct['category'] == 'ESPECIAIS') ? 'selected' : '' ?>>ESPECIAIS</option>
                                <option value="UNBANS" <?= ($editProduct && $editProduct['category'] == 'UNBANS') ? 'selected' : '' ?>>UNBANS</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">Servidor</label>
                            <select name="server" class="form-select">
                                <option value="Global" <?= ($editProduct && $editProduct['server'] == 'Global') ? 'selected' : '' ?>>Global</option>
                                <option value="Survival" <?= ($editProduct && $editProduct['server'] == 'Survival') ? 'selected' : '' ?>>Survival</option>
                                <option value="RankUP" <?= ($editProduct && $editProduct['server'] == 'RankUP') ? 'selected' : '' ?>>RankUP</option>
                                <option value="Factions" <?= ($editProduct && $editProduct['server'] == 'Factions') ? 'selected' : '' ?>>Factions</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 p-2 border rounded bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="small fw-bold">Cor Ícone:</label>
                            <input type="color" name="icon_color" class="form-control form-control-color" value="<?= $editProduct['icon_color'] ?? '#0d6efd' ?>">
                        </div>
                        <div class="d-flex flex-wrap gap-2" style="max-height: 120px; overflow-y: auto;">
                            <?php foreach($available_icons as $fa => $label): 
                                $checked = ($editProduct && $editProduct['icon'] == $fa) || (!$editProduct && $fa == 'fa-crown');
                            ?>
                                <input type="radio" class="btn-check" name="icon_select" id="<?= $fa ?>" value="<?= $fa ?>" <?= $checked ? 'checked' : '' ?>>
                                <label class="btn btn-outline-secondary btn-sm" for="<?= $fa ?>" title="<?= htmlspecialchars($label) ?>">
                                    <i class="fa-solid <?= $fa ?>"></i>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <label class="small fw-bold">Descrição</label>
                        <textarea name="desc" class="form-control" maxlength="200" rows="2"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">Comando <span class="text-danger">*</span></label>
                        <input type="text" name="command" class="form-control font-monospace bg-light" placeholder="setrank %player% vip" required value="<?= htmlspecialchars($editProduct['command'] ?? '') ?>">
                        <div class="form-text small">Use %player% ou {player} como placeholder para o nick do jogador</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="fa-solid fa-<?= $editProduct ? 'save' : 'plus' ?>"></i> 
                        <?= $editProduct ? 'ATUALIZAR PRODUTO' : 'SALVAR PRODUTO' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span>Produtos Ativos</span>
                <span class="badge bg-primary"><?= $conn->query("SELECT COUNT(*) as total FROM rs_products")->fetch_assoc()['total'] ?? 0 ?> produtos</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle small">
                    <thead class="table-light">
                        <tr>
                            <th width="60">Ícone</th>
                            <th>Info</th>
                            <th width="100">Preço</th>
                            <th width="140" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res = $conn->query("SELECT p.*, 
                            (SELECT name FROM rs_products WHERE id = p.upgrade_from_id) as upgrade_from_name
                            FROM rs_products p 
                            ORDER BY p.server ASC, p.category ASC, p.price ASC");
                        if($res && $res->num_rows > 0): 
                            while($row = $res->fetch_assoc()):
                                $color = $row['icon_color'] ?? '#0d6efd';
                                
                                if (!empty($row['old_price']) && $row['old_price'] > $row['price']) {
                                    $priceDisplay = '<small class="text-decoration-line-through text-muted" style="font-size:0.7rem">R$ ' . 
                                                    number_format($row['old_price'], 2, ',', '.') . 
                                                    '</small><br><span class="text-success fw-bold" style="font-size: 0.9rem">R$ ' . 
                                                    number_format($row['price'], 2, ',', '.') . '</span>';
                                } else {
                                    $priceDisplay = '<span class="text-success fw-bold">R$ ' . number_format($row['price'], 2, ',', '.') . '</span>';
                                }
                                
                                $stockDisplay = ($row['stock_qty'] === null || $row['stock_qty'] == '') 
                                    ? '<span class="badge bg-success bg-opacity-10 text-success" style="font-size: 0.65rem">Infinito</span>' 
                                    : '<span class="badge ' . ($row['stock_qty'] <= 5 ? 'bg-danger' : 'bg-warning') . '" style="font-size: 0.65rem">Restam: ' . $row['stock_qty'] . '</span>';
                                
                                // Badge de upgrade
                                $upgradeBadge = '';
                                if (!empty($row['upgrade_from_name'])) {
                                    $upgradeBadge = '<br><span class="badge bg-info bg-opacity-10 text-info" style="font-size: 0.6rem" title="Upgrade de: ' . htmlspecialchars($row['upgrade_from_name']) . '">
                                        <i class="fa-solid fa-arrow-trend-up"></i> Upgrade
                                    </span>';
                                }
                        ?>
                        <tr>
                            <td class="text-center">
                                <div class="bg-light rounded p-2 d-inline-block shadow-sm">
                                    <i class="fa-solid <?= htmlspecialchars($row['icon']) ?> fa-lg" style="color: <?= htmlspecialchars($color) ?>;"></i>
                                </div>
                            </td>
                            <td>
                                <strong style="font-size: 0.9rem"><?= htmlspecialchars($row['name']) ?></strong><br>
                                <span class="badge bg-dark" style="font-size: 0.6rem;"><?= htmlspecialchars($row['server']) ?></span>
                                <span class="badge bg-secondary" style="font-size: 0.6rem;"><?= htmlspecialchars($row['category']) ?></span>
                                <?= $stockDisplay ?>
                                <?= $upgradeBadge ?>
                            </td>
                            <td class="text-success fw-bold"><?= $priceDisplay ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="?edit=<?= $row['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                    <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#testModal<?= $row['id'] ?>" title="Testar Entrega">
                                        <i class="fa-solid fa-flask"></i>
                                    </button>
                                    <a href="?duplicate=<?= $row['id'] ?>" class="btn btn-outline-secondary" title="Duplicar" onclick="return confirm('Deseja duplicar este produto?');">
                                        <i class="fa-solid fa-copy"></i>
                                    </a>
                                    <a href="?del=<?= $row['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Tem certeza que deseja excluir este produto?');" title="Excluir">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>

                                <!-- Modal de Teste -->
                                <div class="modal fade" id="testModal<?= $row['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered modal-sm">
                                        <div class="modal-content text-start">
                                            <form method="POST" onsubmit="return validateNickTest(<?= $row['id'] ?>)">
                                                <input type="hidden" name="test_delivery" value="1">
                                                <input type="hidden" name="product_id" value="<?= $row['id'] ?>">
                                                
                                                <div class="modal-header bg-info text-white">
                                                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-flask"></i> Testar Entrega</h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-warning py-2 small mb-3">
                                                        <i class="fa-solid fa-info-circle"></i> Isso executará o comando no servidor <strong>sem gerar venda</strong>.
                                                    </div>
                                                    <label class="small fw-bold mb-1">Nick Alvo:</label>
                                                    <input type="text" name="test_nick" id="testNick<?= $row['id'] ?>" class="form-control form-control-sm fw-bold" placeholder="Seu nick" required>
                                                    <div class="bg-light p-2 rounded mt-2 small font-monospace text-muted border">
                                                        <small>Cmd: <?= htmlspecialchars(substr($row['command'], 0, 50)) ?><?= strlen($row['command']) > 50 ? '...' : '' ?></small>
                                                    </div>
                                                    <div id="nickError<?= $row['id'] ?>" class="text-danger small mt-1 d-none"></div>
                                                </div>
                                                <div class="modal-footer p-2">
                                                    <button type="submit" class="btn btn-sm btn-info text-white w-100 fw-bold">
                                                        <i class="fa-solid fa-paper-plane"></i> ENVIAR COMANDO
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else: 
                        ?>
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <i class="fa-solid fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-0">Nenhum produto cadastrado.</p>
                                <small class="text-muted">Crie seu primeiro produto usando o formulário ao lado.</small>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Dica sobre Upgrades -->
        <div class="alert alert-info mt-3 mb-0">
            <div class="d-flex align-items-start">
                <i class="fa-solid fa-lightbulb fa-2x me-3 mt-1"></i>
                <div>
                    <h6 class="fw-bold mb-2"><i class="fa-solid fa-graduation-cap"></i> Como usar o Sistema de Upgrade?</h6>
                    <ol class="mb-0 small">
                        <li>Crie um produto base (ex: <strong>VIP Bronze</strong> - R$ 20,00)</li>
                        <li>Crie o próximo nível (ex: <strong>VIP Prata</strong> - R$ 40,00)</li>
                        <li>No produto <strong>VIP Prata</strong>, marque "Ativar" upgrade e selecione <strong>VIP Bronze</strong></li>
                        <li>Pronto! Quem já tem Bronze pagará apenas R$ 20,00 para ter o Prata</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle do select de upgrade
function toggleUpgradeSelect() {
    const checkbox = document.getElementById('enableUpgrade');
    const select = document.getElementById('upgradeFromSelect');
    select.disabled = !checkbox.checked;
    if (!checkbox.checked) {
        select.value = '';
    }
}

// Validação do nick para teste
function validateNickTest(productId) {
    const nick = document.getElementById('testNick' + productId).value;
    const errorDiv = document.getElementById('nickError' + productId);
    
    const nickRegex = /^[a-zA-Z0-9_]{3,16}$/;
    
    if (!nickRegex.test(nick)) {
        errorDiv.textContent = 'Nick inválido! Use 3-16 caracteres (letras, números, underline).';
        errorDiv.classList.remove('d-none');
        return false;
    }
    
    errorDiv.classList.add('d-none');
    return true;
}

// Validação do formulário de produto
document.getElementById('productForm')?.addEventListener('submit', function(e) {
    const command = this.querySelector('input[name="command"]').value;
    const price = parseFloat(this.querySelector('input[name="price"]').value);
    const oldPrice = parseFloat(this.querySelector('input[name="old_price"]').value) || 0;
    
    // Valida comando
    if (!command.includes('%player%') && !command.includes('{player}')) {
        e.preventDefault();
        Swal.fire({
            title: 'Atenção!',
            html: 'O comando deve conter <code>%player%</code> ou <code>{player}</code> como placeholder para o nick do jogador.',
            icon: 'warning'
        });
        return false;
    }
    
    // Valida preço
    if (price <= 0) {
        e.preventDefault();
        Swal.fire('Erro!', 'O preço deve ser maior que zero.', 'error');
        return false;
    }
    
    // Valida preço antigo
    if (oldPrice > 0 && oldPrice <= price) {
        e.preventDefault();
        Swal.fire({
            title: 'Preço Antigo Inválido',
            text: 'O preço antigo deve ser MAIOR que o preço atual para exibir desconto.',
            icon: 'warning'
        });
        return false;
    }
    
    return true;
});

// Preview da cor do ícone em tempo real
document.querySelector('input[name="icon_color"]')?.addEventListener('input', function() {
    const color = this.value;
    document.querySelectorAll('.btn-check:checked + label i').forEach(icon => {
        icon.style.color = color;
    });
});

// Atualiza preview ao selecionar ícone
document.querySelectorAll('.btn-check').forEach(radio => {
    radio.addEventListener('change', function() {
        const color = document.querySelector('input[name="icon_color"]').value;
        const icon = this.nextElementSibling.querySelector('i');
        icon.style.color = color;
    });
});

// Confirmação de exclusão melhorada
document.querySelectorAll('a[href*="del="]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const url = this.href;
        
        Swal.fire({
            title: 'Excluir Produto?',
            text: 'Esta ação não pode ser desfeita!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    });
});

// Confirmação de duplicação
document.querySelectorAll('a[href*="duplicate="]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const url = this.href;
        
        Swal.fire({
            title: 'Duplicar Produto?',
            text: 'Será criada uma cópia deste produto com o nome "(Cópia)"',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, duplicar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    });
});

// Auto-scroll para o formulário ao editar
<?php if($editProduct): ?>
document.querySelector('#productForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
<?php endif; ?>
</script>

<style>
/* Melhoria visual dos badges */
.badge {
    font-weight: 500;
    letter-spacing: 0.3px;
}

/* Efeito hover nos cards de estatísticas */
.card:hover {
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

/* Melhoria na tabela */
.table > tbody > tr:hover {
    background-color: rgba(0,0,0,0.02);
}

/* Preview do ícone no formulário */
.btn-check:checked + label {
    background-color: #0d6efd !important;
    color: white !important;
    border-color: #0d6efd !important;
}

/* Scrollbar customizada para lista de ícones */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 10px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<?php include 'includes/footer.php'; ?>