<?php
session_start();
// Ajuste os includes conforme sua estrutura
if (file_exists('includes/db.php')) include 'includes/db.php'; else include 'db.php';
if (file_exists('includes/header.php')) include 'includes/header.php'; else include 'header.php';

// Verificação de Permissão
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['administrador', 'master'])) {
    echo "<script>window.location='index.php';</script>"; exit;
}

// ADICIONAR ITEM
if (isset($_POST['add_item'])) {
    $label = $conn->real_escape_string($_POST['label']);
    $type = $_POST['type'];
    $value = (int)$_POST['value'];
    $chance = (int)$_POST['chance'];
    $color = $_POST['color'];
    $conn->query("INSERT INTO rs_roulette_items (label, type, value, chance, color) VALUES ('$label', '$type', $value, $chance, '$color')");
    echo "<script>window.location='admin_roulette.php';</script>";
}

// REMOVER ITEM
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM rs_roulette_items WHERE id=$id");
    echo "<script>window.location='admin_roulette.php';</script>";
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold"><i class="fa-solid fa-plus"></i> Novo Prêmio</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-2">
                            <label>Nome Visível</label>
                            <input type="text" name="label" class="form-control" placeholder="Ex: 10% Desconto" required>
                        </div>
                        <div class="mb-2">
                            <label>Tipo</label>
                            <select name="type" class="form-select" onchange="toggleValue(this.value)">
                                <option value="COUPON">Cupom de Desconto</option>
                                <option value="NOTHING">Nada / Tente Novamente</option>
                            </select>
                        </div>
                        <div class="mb-2" id="valDiv">
                            <label>Porcentagem (%)</label>
                            <input type="number" name="value" class="form-control" value="0">
                        </div>
                        <div class="mb-2">
                            <label>Chance (Peso)</label>
                            <input type="number" name="chance" class="form-control" placeholder="Ex: 50 (Alto) ou 1 (Raro)" required>
                            <small class="text-muted">Quanto maior o número, mais fácil de cair.</small>
                        </div>
                        <div class="mb-3">
                            <label>Cor na Roda</label>
                            <input type="color" name="color" class="form-control form-control-color w-100" value="#ffc107">
                        </div>
                        <button type="submit" name="add_item" class="btn btn-success w-100 fw-bold">ADICIONAR</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold"><i class="fa-solid fa-dharmachakra"></i> Configuração da Roleta</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Cor</th>
                                <th>Rótulo</th>
                                <th>Efeito</th>
                                <th>Chance</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = $conn->query("SELECT * FROM rs_roulette_items ORDER BY chance DESC");
                            while($row = $res->fetch_assoc()):
                            ?>
                            <tr>
                                <td><div style="width:25px; height:25px; background:<?=$row['color']?>; border-radius:50%; border:1px solid #ddd;"></div></td>
                                <td class="fw-bold"><?=$row['label']?></td>
                                <td><?= ($row['type'] == 'COUPON') ? 'Desconto: '.$row['value'].'%' : 'Sem prêmio' ?></td>
                                <td><span class="badge bg-secondary"><?=$row['chance']?></span></td>
                                <td class="text-end">
                                    <a href="?del=<?=$row['id']?>" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleValue(val) {
    document.getElementById('valDiv').style.display = (val === 'COUPON') ? 'block' : 'none';
}
</script>

<?php 
if (file_exists('includes/footer.php')) include 'includes/footer.php'; else include 'footer.php';
?>