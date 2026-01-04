<?php
include 'includes/session.php';
include 'includes/db.php';

$admin_user = $_SESSION['admin_user'];

// --- EXPORTAÇÃO (MANTIDA IGUAL) ---
if (isset($_GET['export_batch'])) {
    $note = $conn->real_escape_string($_GET['export_batch']);
    $sql = "SELECT code, reward_cmd, discount_percent FROM rs_keys WHERE note = '$note' AND uses < max_uses";
    $res = $conn->query($sql);
    
    $filename = "keys_" . preg_replace('/[^a-z0-9]/i', '_', $note) . ".txt";
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo "=== LOTE: $note ===\n";
    echo "Gerado em: " . date('d/m/Y H:i') . "\n";
    echo "---------------------------\n";
    while($row = $res->fetch_assoc()) { 
        $tipo = ($row['discount_percent'] > 0) ? "DESCONTO " . $row['discount_percent'] . "%" : "COMANDO";
        echo $row['code'] . " | $tipo\n"; 
    }
    exit;
}

function generateKey() {
    return strtoupper(substr(bin2hex(random_bytes(6)), 0, 4) . '-' . substr(bin2hex(random_bytes(6)), 0, 4) . '-' . substr(bin2hex(random_bytes(6)), 0, 4));
}

// --- PROCESSAMENTO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CRIAR KEY OU CUPOM
    if (isset($_POST['create_key'])) {
        $amount = (int)$_POST['amount']; // Se for cupom único, amount é 1
        $type = $_POST['type']; // 'cmd' ou 'discount'
        $val = $conn->real_escape_string($_POST['value']); // Comando ou Porcentagem
        $note = $conn->real_escape_string($_POST['note']);
        $uses = (int)$_POST['max_uses'];
        $custom_code = isset($_POST['custom_code']) ? strtoupper(trim($_POST['custom_code'])) : '';
        
        $cmd = ($type == 'cmd') ? $val : 'N/A';
        $disc = ($type == 'discount') ? (int)$val : 0;
        
        $count = 0;
        
        // Loop de criação
        for ($i = 0; $i < $amount; $i++) {
            $code = (!empty($custom_code) && $amount == 1) ? $custom_code : generateKey();
            
            // Verifica duplicidade se for custom
            if(!empty($custom_code)) {
                $check = $conn->query("SELECT id FROM rs_keys WHERE code = '$code'");
                if($check->num_rows > 0) {
                    header("Location: keys.php?msg=error_exists");
                    exit;
                }
            }

            $sql = "INSERT INTO rs_keys (code, reward_cmd, discount_percent, max_uses, note) VALUES ('$code', '$cmd', $disc, $uses, '$note')";
            if ($conn->query($sql)) $count++;
        }
        
        if ($count > 0) {
            $conn->query("INSERT INTO rs_audit_logs (username, action, rank_id, permission) VALUES ('$admin_user', 'KEYS ($count)', 'N/A', '$note')");
            header("Location: keys.php?msg=success&count=$count&note=" . urlencode($note));
            exit;
        }
    }
    
    // DELETAR
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $conn->query("DELETE FROM rs_keys WHERE id = $id");
        header("Location: keys.php?msg=deleted");
        exit;
    }
}

include 'includes/header.php';
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

// ALERTAS
$swal_script = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'success') $swal_script = "Swal.fire('Sucesso!', 'Chaves/Cupons gerados com sucesso!', 'success');";
    if ($_GET['msg'] == 'deleted') $swal_script = "const Toast = Swal.mixin({toast: true, position: 'top-end', showConfirmButton: false, timer: 3000}); Toast.fire({icon: 'success', title: 'Removido.'});";
    if ($_GET['msg'] == 'error_exists') $swal_script = "Swal.fire('Erro', 'Este código já existe!', 'error');";
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="fa-solid fa-ticket"></i> Gerenciador de Keys & Cupons</h3>
</div>

<?php if($swal_script) echo "<script>$swal_script</script>"; ?>

<div class="row">
    <div class="col-md-5">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-primary text-white fw-bold"><i class="fa-solid fa-plus-circle"></i> Criar Novo</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="create_key" value="1">
                    
                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="type" id="typeCmd" value="cmd" checked onclick="toggleType('cmd')">
                        <label class="btn btn-outline-primary fw-bold" for="typeCmd"><i class="fa-solid fa-terminal"></i> Item (Comando)</label>

                        <input type="radio" class="btn-check" name="type" id="typeDisc" value="discount" onclick="toggleType('discount')">
                        <label class="btn btn-outline-success fw-bold" for="typeDisc"><i class="fa-solid fa-percent"></i> Desconto (Loja)</label>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small">Nome / Identificação</label>
                        <input type="text" name="note" class="form-control" placeholder="Ex: Cupom Natal ou VIP Ouro" required>
                    </div>
                    
                    <div class="mb-3" id="groupValue">
                        <label class="fw-bold small">Comando de Recompensa</label>
                        <input type="text" name="value" id="inputValue" class="form-control font-monospace" placeholder="setrank %player% vip">
                        <div class="form-text small" id="helpValue">Use %player% para o nick.</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="fw-bold small">Quantidade a Gerar</label>
                            <input type="number" name="amount" class="form-control" value="1" min="1" max="100" required>
                        </div>
                        <div class="col-6">
                            <label class="fw-bold small">Máximo de Usos (cada)</label>
                            <input type="number" name="max_uses" class="form-control" value="1" min="1" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small">Código Personalizado (Opcional)</label>
                        <input type="text" name="custom_code" class="form-control fw-bold text-uppercase" placeholder="Deixe vazio para aleatório">
                        <div class="form-text small">Só funciona se Quantidade for 1.</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-bold">GERAR AGORA</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">Últimos Gerados</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Código / Nota</th>
                            <th>Tipo</th>
                            <th>Usos</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res = $conn->query("SELECT * FROM rs_keys ORDER BY id DESC LIMIT 10");
                        if($res->num_rows > 0):
                        while($row = $res->fetch_assoc()):
                            $isDisc = ($row['discount_percent'] > 0);
                            $icon = $isDisc ? '<i class="fa-solid fa-percent text-success"></i>' : '<i class="fa-solid fa-terminal text-primary"></i>';
                            $val = $isDisc ? $row['discount_percent'] . "% OFF" : substr($row['reward_cmd'], 0, 15) . "...";
                            $status = ($row['uses'] >= $row['max_uses']) ? 'text-decoration-line-through text-muted' : 'fw-bold';
                        ?>
                        <tr class="<?= $status ?>">
                            <td>
                                <code class="text-dark"><?= $row['code'] ?></code><br>
                                <small class="text-muted"><?= $row['note'] ?></small>
                            </td>
                            <td>
                                <?= $icon ?> <span class="small fw-bold"><?= $val ?></span>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= $row['uses'] ?>/<?= $row['max_uses'] ?></span>
                            </td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Apagar?');">
                                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                    <button class="btn btn-sm text-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleType(type) {
    const input = document.getElementById('inputValue');
    const help = document.getElementById('helpValue');
    
    if (type === 'discount') {
        input.placeholder = "20";
        input.type = "number";
        help.innerText = "Porcentagem de desconto (Ex: 20 para 20%).";
    } else {
        input.placeholder = "setrank %player% vip";
        input.type = "text";
        help.innerText = "Use %player% para o nick.";
    }
}
</script>

<?php include 'includes/footer.php'; ?>