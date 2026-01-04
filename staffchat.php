<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// Permissão
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['ajudante', 'moderador', 'administrador', 'master'])) {
    echo "<script>window.location='index.php';</script>";
    exit;
}

$user = $_SESSION['admin_user'];

// --- ENVIO DE MENSAGEM (AJAX HANDLE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_msg'])) {
    $msg = $conn->real_escape_string($_POST['ajax_msg']);
    
    if (!empty($msg)) {
        // 1. Salva no Banco
        $conn->query("INSERT INTO rs_staff_chat (username, message, source) VALUES ('$user', '$msg', 'WEB')");
        
        // 2. Envia pro Redis
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');
            $redis->publish('redesplit:channel', "STAFF_CHAT;$user [Web];$msg");
        } catch (Exception $e) {}
    }
    exit; // Para aqui se for AJAX
}
?>

<style>
    .chat-box { height: 400px; overflow-y: auto; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px; padding: 15px; }
    .msg-item { margin-bottom: 10px; }
    .msg-game { color: #2c3e50; }
    .msg-web { color: #e67e22; }
    .msg-time { font-size: 0.75em; color: #999; margin-left: 5px; }
</style>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-warning">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fa-solid fa-user-secret"></i> Staff Chat</h5>
                <small class="badge bg-dark text-white">Ao Vivo</small>
            </div>
            <div class="card-body">
                
                <div id="chat-content" class="chat-box mb-3">
                    <p class="text-center text-muted mt-5"><i class="fa-solid fa-circle-notch fa-spin"></i> Carregando mensagens...</p>
                </div>

                <form id="chat-form" onsubmit="return sendMessage();">
                    <div class="input-group">
                        <input type="text" id="message-input" class="form-control" placeholder="Digite sua mensagem para a Staff..." autocomplete="off">
                        <button class="btn btn-dark fw-bold" type="submit">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
// Função para carregar mensagens
function loadMessages() {
    fetch('api_staffchat.php') // Vamos criar esse arquivinho auxiliar
        .then(response => response.text())
        .then(data => {
            document.getElementById('chat-content').innerHTML = data;
            // Scroll para baixo apenas se não estiver lendo histórico antigo (opcional, aqui forçamos)
            // var chatBox = document.getElementById('chat-content');
            // chatBox.scrollTop = chatBox.scrollHeight;
        });
}

// Função para enviar
function sendMessage() {
    var input = document.getElementById('message-input');
    var msg = input.value;
    
    if (msg.trim() === "") return false;

    var formData = new FormData();
    formData.append('ajax_msg', msg);

    fetch('staffchat.php', { method: 'POST', body: formData })
        .then(() => {
            input.value = "";
            loadMessages(); // Atualiza na hora
            // Auto scroll
            setTimeout(() => {
                 var chatBox = document.getElementById('chat-content');
                 chatBox.scrollTop = chatBox.scrollHeight;
            }, 200);
        });

    return false; // Previne refresh
}

// Atualiza a cada 3 segundos
setInterval(loadMessages, 3000);
loadMessages();
</script>

<?php include 'includes/footer.php'; ?>