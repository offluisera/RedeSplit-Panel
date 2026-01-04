<?php
include 'includes/session.php';
include 'includes/header.php';

// --- SEGURANÇA MÁXIMA ---
// Apenas Master e Administrador podem acessar. Moderadores NÃO entram aqui.
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['administrador', 'master'])) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>⛔ Acesso Negado. Apenas Administradores.</div></div>";
    include 'includes/footer.php';
    exit;
}

$msg_feedback = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_cmd'])) {
    $command = trim($_POST['command']);
    $admin = $_SESSION['admin_user'];

    if (!empty($command)) {
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');

            // Formato: CONSOLE;AdminName;COMANDO
            // Exemplo: CONSOLE;OffLuisera;say Olá servidor
            $redis->publish('redesplit:channel', "CONSOLE;$admin;$command");
            
            $msg_feedback = "<div class='alert alert-success'>✅ Comando enviado: <b>/$command</b></div>";
            
            // Opcional: Salvar log de auditoria no MySQL se quiser saber quem fez o que
            
        } catch (Exception $e) {
            $msg_feedback = "<div class='alert alert-danger'>Erro Redis: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <?= $msg_feedback ?>
        
        <div class="card shadow border-dark">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-terminal"></i> Console Remoto</h5>
                <span class="badge bg-danger">Acesso Restrito</span>
            </div>
            <div class="card-body bg-light">
                <p class="text-muted small"><i class="fa-solid fa-triangle-exclamation"></i> Cuidado: Os comandos são executados com permissão máxima (OP).</p>
                
                <form method="POST">
                    <input type="hidden" name="send_cmd" value="true">
                    
                    <div class="input-group input-group-lg mb-3">
                        <span class="input-group-text bg-dark text-white font-monospace">/</span>
                        <input type="text" name="command" class="form-control font-monospace" placeholder="gamemode 1 offluisera" required autocomplete="off" autofocus>
                        <button class="btn btn-danger fw-bold" type="submit">
                            EXECUTAR <i class="fa-solid fa-bolt"></i>
                        </button>
                    </div>
                </form>

                <hr>
                <h6>Comandos Rápidos:</h6>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm" onclick="setCmd('stop')">Stop</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="setCmd('whitelist on')">Whitelist ON</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="setCmd('whitelist off')">Whitelist OFF</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="setCmd('lagg clear')">Limpar Chão</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="setCmd('save-all')">Salvar Mundo</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setCmd(cmd) {
    document.querySelector('input[name="command"]').value = cmd;
}
</script>

<?php include 'includes/footer.php'; ?>