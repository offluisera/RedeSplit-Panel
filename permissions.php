<?php
include 'includes/session.php';
include 'includes/db.php';

// Adiciona o SweetAlert2 (Biblioteca de Popups)
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

// --- 0. EXPORTAR JSON (Backup) ---
if (isset($_GET['action']) && $_GET['action'] == 'export_json' && isset($_GET['rank'])) {
    $rank_id = $_GET['rank'];
    $stmt = $conn->prepare("SELECT permission, server_scope, world_scope, expiration FROM rs_ranks_permissions WHERE rank_id = ?");
    $stmt->bind_param("s", $rank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $export_data = [];
    while ($row = $result->fetch_assoc()) $export_data[] = $row;
    
    $filename = "backup_{$rank_id}_" . date('Y-m-d_Hi') . ".json";
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($export_data, JSON_PRETTY_PRINT);
    exit;
}

include 'includes/header.php';

$selected_rank = isset($_GET['rank']) ? $_GET['rank'] : null;
$admin_user = $_SESSION['admin_user']; 
$swal_script = ""; // Variável para armazenar o script do alerta que será exibido no final

// Função Redis
function notifyGameServer($rank_id) {
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379); 
        $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903'); // Descomente se tiver senha
        $redis->publish('redesplit:channel', "PERM_UPDATE|ALL|$rank_id");
    } catch (Exception $e) {}
}

// Função Log (Agora segura contra erros de tamanho)
function logAction($conn, $user, $action, $rank, $perm) {
    try {
        $stmt = $conn->prepare("INSERT INTO rs_audit_logs (username, action, rank_id, permission) VALUES (?, ?, ?, ?)");
        // Corta a string se for muito grande para não travar, caso o banco não tenha sido alterado
        $actionSafe = substr($action, 0, 254); 
        $stmt->bind_param("ssss", $user, $actionSafe, $rank, $perm);
        $stmt->execute();
    } catch (Exception $e) {
        // Silencia erro de log para não travar a página
    }
}

// --- 1. ROLLBACK ---
if (isset($_GET['rollback_id']) && $selected_rank) {
    $logId = (int)$_GET['rollback_id'];
    $q = $conn->query("SELECT * FROM rs_audit_logs WHERE id = $logId AND rank_id = '$selected_rank'");
    
    if ($q && $q->num_rows > 0) {
        $log = $q->fetch_assoc();
        $action = $log['action'];
        $perm = $log['permission'];
        
        // Extrai contexto
        $server = 'GLOBAL'; $world = 'GLOBAL';
        if (preg_match('/\((.*)\/(.*)\)/', $action, $matches)) {
            $server = $matches[1]; $world = $matches[2];
        }

        $success = false;
        if (strpos($action, 'ADICIONOU') !== false) {
            $stmt = $conn->prepare("DELETE FROM rs_ranks_permissions WHERE rank_id=? AND permission=? AND server_scope=? AND world_scope=?");
            $stmt->bind_param("ssss", $selected_rank, $perm, $server, $world);
            if ($stmt->execute()) $success = true;
        } elseif (strpos($action, 'REMOVEU') !== false) {
            $stmt = $conn->prepare("INSERT IGNORE INTO rs_ranks_permissions (rank_id, permission, server_scope, world_scope) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $selected_rank, $perm, $server, $world);
            if ($stmt->execute()) $success = true;
        }

        if ($success) {
            notifyGameServer($selected_rank);
            logAction($conn, $admin_user, "ROLLBACK ($action)", $selected_rank, $perm);
            // Redireciona limpo para evitar reenvio e mostra o Toast
            echo "<script>window.location.href='permissions.php?rank=$selected_rank&msg=rollback_ok';</script>";
            exit;
        }
    }
}

// --- 2. IMPORTAR / ADICIONAR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_rank) {
    
    // Importar JSON
    if (isset($_POST['import_json']) && isset($_FILES['json_file'])) {
        $content = file_get_contents($_FILES['json_file']['tmp_name']);
        $perms = json_decode($content, true);
        if (is_array($perms)) {
            $count = 0;
            if ($_POST['import_mode'] == 'overwrite') $conn->query("DELETE FROM rs_ranks_permissions WHERE rank_id = '$selected_rank'");
            $stmt = $conn->prepare("INSERT IGNORE INTO rs_ranks_permissions (rank_id, permission, server_scope, world_scope, expiration) VALUES (?, ?, ?, ?, ?)");
            foreach ($perms as $p) {
                if(!isset($p['permission'])) continue;
                $srv = $p['server_scope'] ?? 'GLOBAL'; $wld = $p['world_scope'] ?? 'GLOBAL'; $exp = $p['expiration'] ?? null;
                $stmt->bind_param("sssss", $selected_rank, $p['permission'], $srv, $wld, $exp);
                if ($stmt->execute() && $stmt->affected_rows > 0) $count++;
            }
            if ($count > 0) {
                notifyGameServer($selected_rank);
                logAction($conn, $admin_user, "IMPORT ($count)", $selected_rank, "Backup JSON");
                $swal_script = "Toast.fire({ icon: 'success', title: '$count permissões importadas!' });";
            }
        }
    }

    // Bulk Add
    if (isset($_POST['bulk_add'])) {
        $lines = explode("\n", $_POST['bulk_permissions']);
        $server = $_POST['server_scope']; $world = empty($_POST['world_scope']) ? 'GLOBAL' : trim($_POST['world_scope']);
        $exp = ($_POST['duration'] != 'permanent') ? date('Y-m-d H:i:s', strtotime("+".$_POST['duration'])) : null;
        $count = 0;
        $stmt = $conn->prepare("INSERT IGNORE INTO rs_ranks_permissions (rank_id, permission, server_scope, world_scope, expiration) VALUES (?, ?, ?, ?, ?)");
        foreach ($lines as $line) {
            $perm = trim($line); if(empty($perm)) continue;
            $stmt->bind_param("sssss", $selected_rank, $perm, $server, $world, $exp);
            if ($stmt->execute() && $stmt->affected_rows > 0) $count++;
        }
        if ($count > 0) {
            notifyGameServer($selected_rank);
            logAction($conn, $admin_user, "BULK ADD ($count)", $selected_rank, "Massa");
            $swal_script = "Toast.fire({ icon: 'success', title: '$count permissões adicionadas!' });";
        }
    }

    // Single Add
    if (isset($_POST['add_perm'])) {
        $perm = trim($_POST['permission']);
        $server = $_POST['server_scope']; $world = empty($_POST['world_scope']) ? 'GLOBAL' : trim($_POST['world_scope']);
        $exp = ($_POST['duration'] != 'permanent') ? date('Y-m-d H:i:s', strtotime("+".$_POST['duration'])) : null;

        $check = $conn->query("SELECT id FROM rs_ranks_permissions WHERE rank_id='$selected_rank' AND permission='$perm' AND server_scope='$server' AND world_scope='$world'");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO rs_ranks_permissions (rank_id, permission, server_scope, world_scope, expiration) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $selected_rank, $perm, $server, $world, $exp);
            if ($stmt->execute()) {
                notifyGameServer($selected_rank);
                logAction($conn, $admin_user, "ADICIONOU ($server/$world)", $selected_rank, $perm);
                $swal_script = "Toast.fire({ icon: 'success', title: 'Permissão adicionada!' });";
            }
        } else {
            $swal_script = "Toast.fire({ icon: 'error', title: 'Permissão já existe.' });";
        }
    }
}

// --- 3. REMOVER ---
if (isset($_GET['del_perm']) && $selected_rank) {
    $perm = $_GET['del_perm']; $server = $_GET['server']; $world = $_GET['world'];
    $stmt = $conn->prepare("DELETE FROM rs_ranks_permissions WHERE rank_id=? AND permission=? AND server_scope=? AND world_scope=?");
    $stmt->bind_param("ssss", $selected_rank, $perm, $server, $world);
    if ($stmt->execute()) {
        notifyGameServer($selected_rank);
        logAction($conn, $admin_user, "REMOVEU ($server/$world)", $selected_rank, $perm);
        echo "<script>window.location.href='permissions.php?rank=$selected_rank&msg=removed';</script>";
        exit;
    }
}

// Detectar mensagens de URL para exibir Toast
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'removed') $swal_script = "Toast.fire({ icon: 'success', title: 'Permissão removida.' });";
    if ($_GET['msg'] == 'rollback_ok') $swal_script = "Toast.fire({ icon: 'success', title: 'Ação desfeita com sucesso!' });";
}
?>

<style>
    .custom-dropdown { position: absolute; top: 100%; left: 0; right: 0; z-index: 1050; background-color: #2f3542; border-radius: 0 0 8px 8px; max-height: 200px; overflow-y: auto; display: none; }
    .suggestion-item { padding: 8px 15px; cursor: pointer; color: #dfe4ea; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .suggestion-item:hover { background-color: #ffae00; color: #2f3542; }
    
    /* SweetAlert Dark Mode Override */
    div:where(.swal2-container) div:where(.swal2-popup) {
        background: #1f1f1f !important;
        color: #e0e0e0 !important;
        border: 1px solid #333;
    }
    div:where(.swal2-icon).swal2-success { border-color: #28a745 !important; color: #28a745 !important; }
    div:where(.swal2-icon).swal2-error { border-color: #dc3545 !important; color: #dc3545 !important; }
</style>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="list-group shadow-sm">
            <div class="list-group-item list-group-item-dark fw-bold">Selecionar Cargo</div>
            <?php
            $ranks = $conn->query("SELECT rank_id, display_name FROM rs_ranks");
            while ($r = $ranks->fetch_assoc()):
                $active = ($selected_rank == $r['rank_id']) ? 'active' : '';
            ?>
                <a href="?rank=<?= $r['rank_id'] ?>" class="list-group-item list-group-item-action <?= $active ?>">
                    <?= $r['display_name'] ?> <small class="text-muted">(<?= $r['rank_id'] ?>)</small>
                </a>
            <?php endwhile; ?>
        </div>
    </div>

    <div class="col-md-9">
        <?php if ($selected_rank): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
                    <h5 class="mb-0">Editando: <b class="text-primary"><?= htmlspecialchars($selected_rank) ?></b></h5>
                    <div class="mt-2 mt-md-0">
                        <button class="btn btn-sm btn-info text-white me-1" data-bs-toggle="modal" data-bs-target="#simModal"><i class="fa-solid fa-flask"></i> Testar</button>
                        <a href="?rank=<?= $selected_rank ?>&action=export_json" class="btn btn-sm btn-outline-dark me-1" target="_blank"><i class="fa-solid fa-download"></i> JSON</a>
                        <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fa-solid fa-upload"></i> Importar</button>
                        <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#bulkModal"><i class="fa-solid fa-list-check"></i> Em Massa</button>
                    </div>
                </div>
                
                <div class="card-body">
                    <form method="POST" class="mb-4" autocomplete="off">
                        <div class="row g-2">
                            <div class="col-12 position-relative">
                                <div class="input-group">
                                    <span class="input-group-text bg-dark text-white"><i class="fa-solid fa-key"></i></span>
                                    <input type="text" id="permInput" name="permission" class="form-control" placeholder="Permissão (Ex: essentials.fly)" required autocomplete="off">
                                </div>
                                <div id="suggestionBox" class="custom-dropdown"></div>
                            </div>
                            <div class="col-md-4"><select name="server_scope" class="form-select"><option value="GLOBAL">🌐 GLOBAL</option><option value="survival">🌲 Survival</option><option value="skyblock">☁️ SkyBlock</option></select></div>
                            <div class="col-md-3"><input type="text" name="world_scope" class="form-control" placeholder="Mundo (Opcional)"></div>
                            <div class="col-md-3"><select name="duration" class="form-select"><option value="permanent">Eterno</option><option value="1 hour">1h</option><option value="1 day">1d</option><option value="30 days">30d</option></select></div>
                            <div class="col-md-2 d-grid"><button type="submit" name="add_perm" class="btn btn-success fw-bold">SALVAR</button></div>
                        </div>
                    </form>

                    <div class="mb-3 position-relative">
                        <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                        <input type="text" id="filterPerms" class="form-control ps-5 bg-light" placeholder="Filtrar permissões na lista...">
                    </div>

                    <ul class="list-group" id="permList">
                        <?php
                        $stmt = $conn->prepare("SELECT * FROM rs_ranks_permissions WHERE rank_id = ? ORDER BY expiration DESC, permission ASC");
                        $stmt->bind_param("s", $selected_rank);
                        $stmt->execute();
                        $perms = $stmt->get_result();
                        if ($perms->num_rows > 0):
                            while ($p = $perms->fetch_assoc()):
                                $isNeg = (substr($p['permission'], 0, 1) === '-');
                                $permDisp = $isNeg ? '<span class="text-danger fw-bold"><i class="fa-solid fa-ban me-1"></i>'.htmlspecialchars($p['permission']).'</span>' : '<span class="fw-bold font-monospace text-dark">'.htmlspecialchars($p['permission']).'</span>';
                                $bg = $isNeg ? 'bg-danger bg-opacity-10' : '';
                                $srv = ($p['server_scope'] == 'GLOBAL') ? 'bg-secondary' : 'bg-primary';
                                $wld = ($p['world_scope'] == 'GLOBAL') ? '' : '<span class="badge bg-warning text-dark ms-1">'.$p['world_scope'].'</span>';
                                $time = $p['expiration'] ? '<small class="text-danger ms-2" title="Expira: '.$p['expiration'].'"><i class="fa-solid fa-hourglass-half"></i></small>' : '';
                                
                                // Link de delete com SweetAlert
                                $delLink = "?rank=$selected_rank&del_perm=".urlencode($p['permission'])."&server={$p['server_scope']}&world={$p['world_scope']}";
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 <?= $bg ?>">
                                <div><?= $permDisp ?> <span class="badge <?= $srv ?> ms-2 small"><?= strtoupper($p['server_scope']) ?></span> <?= $wld ?> <?= $time ?>
                                <span class="d-none search-data"><?= strtolower($p['permission'].' '.$p['server_scope']) ?></span></div>
                                
                                <button onclick="confirmAction('<?= $delLink ?>', 'Remover esta permissão?')" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </li>
                        <?php endwhile; else: echo "<li class='list-group-item text-center text-muted py-3'>Nenhuma permissão.</li>"; endif; ?>
                    </ul>
                </div>
            </div>
            
            <div class="card shadow-sm border-secondary">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center small">
                    <span>Histórico & Rollback</span>
                    <small>Últimos 10 eventos</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <?php
                        $logs = $conn->query("SELECT * FROM rs_audit_logs WHERE rank_id = '$selected_rank' ORDER BY id DESC LIMIT 10");
                        if($logs):
                        while ($l = $logs->fetch_assoc()):
                            $canRollback = (strpos($l['action'], 'ADICIONOU') !== false || strpos($l['action'], 'REMOVEU') !== false);
                            $badgeClass = 'bg-secondary';
                            if(strpos($l['action'], 'ADICIONOU') !== false) $badgeClass = 'bg-success';
                            if(strpos($l['action'], 'REMOVEU') !== false) $badgeClass = 'bg-danger';
                            if(strpos($l['action'], 'ROLLBACK') !== false) $badgeClass = 'bg-warning text-dark';
                            
                            $rollbackLink = "?rank=$selected_rank&rollback_id={$l['id']}";
                        ?>
                        <tr>
                            <td class="fw-bold"><?= $l['username'] ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $l['action'] ?></span></td>
                            <td><code><?= substr($l['permission'], 0, 30) ?></code></td>
                            <td class="text-end">
                                <span class="text-muted me-2"><?= date('d/m H:i', strtotime($l['date'])) ?></span>
                                <?php if($canRollback): ?>
                                    <button onclick="confirmAction('<?= $rollbackLink ?>', 'Deseja realmente desfazer (Rollback)?')" class="btn btn-xs btn-outline-dark" style="padding: 0 5px;" title="Desfazer">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Selecione um cargo.</div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="simModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-info text-white"><h5 class="modal-title">Simulador</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="simForm"><div class="mb-2"><label class="small fw-bold">Nick</label><input type="text" id="simNick" class="form-control" required></div><div class="mb-2"><label class="small fw-bold">Permissão</label><input type="text" id="simPerm" class="form-control" required></div><div class="row g-2 mb-2"><div class="col-6"><label class="small fw-bold">Servidor</label><select id="simServer" class="form-select"><option value="GLOBAL">Global</option><option value="survival">Survival</option></select></div><div class="col-6"><label class="small fw-bold">Mundo</label><input type="text" id="simWorld" class="form-control" placeholder="Global"></div></div><div class="d-grid"><button type="submit" class="btn btn-info text-white fw-bold">VERIFICAR</button></div></form><div id="simResult" class="mt-3 text-center" style="display:none;"><h1 id="resIcon"></h1><h4 id="resTitle" class="fw-bold"></h4><p id="resReason" class="small text-muted"></p></div></div></div></div></div>
<div class="modal fade" id="importModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" enctype="multipart/form-data"><input type="hidden" name="import_json" value="1"><div class="modal-header"><h5 class="modal-title">Importar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="file" name="json_file" class="form-control mb-3" accept=".json" required><div class="form-check"><input class="form-check-input" type="radio" name="import_mode" value="append" checked><label class="form-check-label">Adicionar</label></div><div class="form-check"><input class="form-check-input" type="radio" name="import_mode" value="overwrite"><label class="form-check-label text-danger">Sobrescrever</label></div></div><div class="modal-footer"><button type="submit" class="btn btn-primary">Enviar</button></div></form></div></div></div>
<div class="modal fade" id="bulkModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="POST"><input type="hidden" name="bulk_add" value="1"><div class="modal-header"><h5 class="modal-title">Massa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-2 mb-2"><div class="col-4"><select name="server_scope" class="form-select form-select-sm"><option value="GLOBAL">Global</option><option value="survival">Survival</option></select></div><div class="col-4"><input name="world_scope" class="form-control form-control-sm" placeholder="Mundo"></div><div class="col-4"><select name="duration" class="form-select form-select-sm"><option value="permanent">Eterno</option></select></div></div><textarea name="bulk_permissions" class="form-control font-monospace" rows="10" placeholder="Uma por linha..." required></textarea></div><div class="modal-footer"><button type="submit" class="btn btn-primary">Salvar</button></div></form></div></div></div>

<script>
// --- TOAST CONFIG (Popups de Canto) ---
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    background: '#1f1f1f',
    color: '#fff',
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

// Executa mensagens vindas do PHP
<?php if($swal_script) echo $swal_script; ?>

// --- CONFIRMAÇÃO ESTILIZADA ---
function confirmAction(url, text) {
    Swal.fire({
        title: 'Tem certeza?',
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sim, confirmar',
        cancelButtonText: 'Cancelar',
        background: '#1f1f1f',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    })
}

// Simulador com SweetAlert Error
document.getElementById('simForm').addEventListener('submit', function(e) {
    e.preventDefault(); const btn = this.querySelector('button'); const originalText = btn.innerHTML; btn.innerHTML = '...'; btn.disabled = true;
    const fd = new FormData(); fd.append('nick', document.getElementById('simNick').value); fd.append('permission', document.getElementById('simPerm').value); fd.append('server', document.getElementById('simServer').value); fd.append('world', document.getElementById('simWorld').value);
    
    fetch('api_perm_simulator.php', { method: 'POST', body: fd })
    .then(r => r.text().then(text => { try { return JSON.parse(text); } catch (err) { throw new Error("Erro de resposta do servidor."); } }))
    .then(data => {
        if(data.error) {
            Toast.fire({ icon: 'error', title: data.error });
        } else {
            document.getElementById('simResult').style.display = 'block';
            document.getElementById('resReason').innerHTML = data.reason;
            if (data.allowed) {
                document.getElementById('resIcon').innerHTML = '<i class="fa-solid fa-circle-check text-success display-4"></i>';
                document.getElementById('resTitle').innerText = 'PERMITIDO';
            } else {
                document.getElementById('resIcon').innerHTML = '<i class="fa-solid fa-circle-xmark text-danger display-4"></i>';
                document.getElementById('resTitle').innerText = 'NEGADO';
            }
        }
        btn.innerHTML = originalText; btn.disabled = false;
    })
    .catch(err => {
        Toast.fire({ icon: 'error', title: 'Falha ao conectar no simulador.' });
        btn.innerHTML = originalText; btn.disabled = false;
    });
});

// Filtro e Sugestão
document.getElementById('filterPerms')?.addEventListener('keyup', function() { let term = this.value.toLowerCase(); document.querySelectorAll('#permList li').forEach(li => { let text = li.querySelector('.search-data') ? li.querySelector('.search-data').innerText : li.innerText.toLowerCase(); li.style.display = text.includes(term) ? '' : 'none'; }); });
let timeout = null; const input = document.getElementById('permInput'); const box = document.getElementById('suggestionBox');
if(input){ input.addEventListener('input', function() { let term = this.value; if (term.length < 2) { box.style.display = 'none'; return; } clearTimeout(timeout); timeout = setTimeout(function() { fetch('api_permissions.php?term=' + term).then(r => r.json()).then(data => { box.innerHTML = ''; if (data.length > 0) { box.style.display = 'block'; data.forEach(perm => { let item = document.createElement('div'); item.className = 'suggestion-item'; item.innerHTML = '<i class="fa-solid fa-bolt me-2"></i>' + perm; item.onclick = function() { input.value = perm; box.style.display = 'none'; input.focus(); }; box.appendChild(item); }); } else { box.style.display = 'none'; } }); }, 200); }); document.addEventListener('click', function(e) { if (e.target !== input && e.target !== box) box.style.display = 'none'; }); }
</script>

<?php include 'includes/footer.php'; ?>