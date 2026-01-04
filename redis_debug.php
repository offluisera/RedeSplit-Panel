<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// Apenas Staff Administrativa
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['administrador', 'master'])) {
    echo "<div class='alert alert-danger'>Acesso Negado.</div>";
    include 'includes/footer.php';
    exit;
}
?>

<style>
    .terminal-window {
        background-color: #1e1e1e;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        border: 1px solid #333;
        font-family: 'Consolas', 'Monaco', monospace;
        height: 500px;
        display: flex;
        flex-direction: column;
    }
    .terminal-header {
        background-color: #2d2d2d;
        padding: 10px 15px;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #333;
    }
    .terminal-body {
        flex: 1;
        overflow-y: auto;
        padding: 15px;
        color: #d4d4d4;
        font-size: 0.9rem;
    }
    /* Cores dos Pacotes */
    .log-entry { margin-bottom: 5px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 2px; }
    .log-time { color: #569cd6; margin-right: 10px; }
    
    .type-BANK { color: #4ec9b0; font-weight: bold; }   /* Ciano */
    .type-CMD { color: #ce9178; font-weight: bold; }    /* Laranja/Vermelho */
    .type-PERM { color: #c586c0; font-weight: bold; }   /* Roxo */
    .type-INFO { color: #6a9955; font-style: italic; }  /* Verde */
    .type-ERROR { color: #f44747; font-weight: bold; }  /* Vermelho Forte */

    /* Scrollbar */
    .terminal-body::-webkit-scrollbar { width: 8px; }
    .terminal-body::-webkit-scrollbar-thumb { background: #444; border-radius: 4px; }
    .terminal-body::-webkit-scrollbar-track { background: #1e1e1e; }
</style>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h3><i class="fa-solid fa-bug text-danger"></i> Redis Live Debug</h3>
            <p class="text-muted">Monitoramento em tempo real do canal <code>redesplit:channel</code></p>
        </div>
        <div>
            <button id="btnToggle" class="btn btn-success fw-bold" onclick="toggleStream()">
                <i class="fa-solid fa-play"></i> INICIAR
            </button>
            <button class="btn btn-outline-secondary" onclick="clearLog()">
                <i class="fa-solid fa-trash"></i> Limpar
            </button>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="terminal-window">
            <div class="terminal-header">
                <span class="text-white-50"><i class="fa-solid fa-terminal"></i> console output</span>
                <span id="statusBadge" class="badge bg-secondary">DESCONECTADO</span>
            </div>
            <div id="terminalLog" class="terminal-body">
                <div class="text-muted text-center mt-5">Clique em "INICIAR" para conectar ao Redis...</div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title">Simular Pacote</h5>
                <div class="input-group">
                    <select class="form-select" id="simType" style="max-width: 100px;">
                        <option value="CMD">CMD</option>
                        <option value="TEST">TEST</option>
                    </select>
                    <input type="text" id="simContent" class="form-control" placeholder="Conteúdo da mensagem...">
                    <button class="btn btn-primary" onclick="simulatePacket()">Enviar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let eventSource = null;
    const terminal = document.getElementById('terminalLog');
    const statusBadge = document.getElementById('statusBadge');
    const btnToggle = document.getElementById('btnToggle');

    function toggleStream() {
        if (eventSource) {
            stopStream();
        } else {
            startStream();
        }
    }

    function startStream() {
        terminal.innerHTML = ''; // Limpa msg inicial
        addLog('INFO', 'Tentando conectar ao stream...');
        
        // Inicia a conexão SSE com o PHP
        eventSource = new EventSource('redis_stream.php');

        eventSource.onopen = function() {
            statusBadge.className = 'badge bg-success animate-pulse';
            statusBadge.innerText = 'AO VIVO';
            btnToggle.className = 'btn btn-danger fw-bold';
            btnToggle.innerHTML = '<i class="fa-solid fa-stop"></i> PARAR';
        };

        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            addLog(data.type, data.content, data.time);
        };

        eventSource.onerror = function() {
            addLog('ERROR', 'Conexão perdida. Tentando reconectar...');
            statusBadge.className = 'badge bg-warning text-dark';
            statusBadge.innerText = 'RECONECTANDO';
        };
    }

    function stopStream() {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
        statusBadge.className = 'badge bg-secondary';
        statusBadge.innerText = 'DESCONECTADO';
        btnToggle.className = 'btn btn-success fw-bold';
        btnToggle.innerHTML = '<i class="fa-solid fa-play"></i> INICIAR';
        addLog('INFO', 'Monitoramento encerrado.');
    }

    function addLog(type, msg, time = null) {
        if (!time) {
            const d = new Date();
            time = d.toLocaleTimeString();
        }

        // Cria a linha do log
        const div = document.createElement('div');
        div.className = 'log-entry';
        
        // Formata o HTML
        div.innerHTML = `
            <span class="log-time">[${time}]</span>
            <span class="type-${type}">${type}:</span> 
            <span class="text-white">${msg}</span>
        `;

        terminal.appendChild(div);
        
        // Auto-scroll para o final
        terminal.scrollTop = terminal.scrollHeight;
    }

    function clearLog() {
        terminal.innerHTML = '';
    }

    // Função para enviar um pacote de teste (AJAX simples)
    function simulatePacket() {
        const type = document.getElementById('simType').value;
        const content = document.getElementById('simContent').value;
        if(!content) return;

        // Vamos usar um script inline php fake aqui ou criar um arquivo separado. 
        // Para simplificar, vou assumir que você tem um arquivo para enviar msg, 
        // mas como é debug, vamos apenas logar localmente se não tiver backend de envio.
        // O ideal é criar um 'send_debug.php' simples.
        
        const formData = new FormData();
        formData.append('msg', `${type};ALL;${content}`);
        
        fetch('test_redis_send.php', { // Crie esse arquivo se quiser testar o envio real
            method: 'POST',
            body: formData
        }).then(() => {
            console.log("Enviado");
        });
    }
</script>

<?php include 'includes/footer.php'; ?>