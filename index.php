<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// --- CONFIGURAÇÕES ---
$server_ip = "ultra-01.bedhosting.com.br:25725"; 
$my_user = $_SESSION['admin_user'];

// --- DETECTAR SERVIDOR ATUAL ---
$current_server = isset($_GET['server']) ? $_GET['server'] : 'geral';

// --- PERMISSÕES ---
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
$is_admin = in_array($rank, ['administrador', 'master']);

// --- DADOS DO SERVIDOR (GERAL) ---
$query_last = $conn->query("SELECT * FROM rs_server_stats ORDER BY id DESC LIMIT 1");
$server_data = $query_last->fetch_assoc();

$is_online = false;
$online_count = 0;
$last_update = "Desconhecido";

if ($server_data) {
    $db_time = strtotime($server_data['date']);
    $time_diff = time() - $db_time;
    if ($time_diff < 420) { 
        $is_online = true;
        $online_count = $server_data['online_players'];
    }
    $last_update = date('d/m H:i', $db_time);
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow border-0 bg-dark text-white" style="background: linear-gradient(45deg, #2c3e50, #000);">
            <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h5 class="text-white-50 mb-1">Status do Servidor</h5>
                    <h2 class="fw-bold mb-0">
                        <?php if($is_online): ?>
                            <i class="fa-solid fa-signal text-success animate-pulse"></i> ONLINE
                        <?php else: ?>
                            <i class="fa-solid fa-power-off text-danger"></i> OFFLINE
                        <?php endif; ?>
                    </h2>
                    <small class="text-muted">IP: <span class="text-white user-select-all"><?= $server_ip ?></span></small>
                </div>
                <div class="text-end mt-3 mt-md-0">
                    <h1 class="display-4 fw-bold mb-0 text-warning"><?= $online_count ?></h1>
                    <p class="mb-0 text-white-50">Jogadores Conectados</p>
                    <small style="font-size: 0.7em">Atualizado: <?= $last_update ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="server-menu-card shadow-sm">
            <div class="card-header">
                <i class="fa-solid fa-network-wired text-warning"></i> Selecione o Servidor
            </div>
            <div class="server-list">
                <a href="?server=geral" class="server-btn <?= ($current_server == 'geral') ? 'active' : '' ?>">
                    <span class="server-icon">🌍</span> GERAL
                </a>
                <a href="?server=skyblock" class="server-btn <?= ($current_server == 'skyblock') ? 'active' : '' ?>">
                    <span class="server-icon">☁️</span> SkyBlock
                </a>
                <a href="?server=survival" class="server-btn <?= ($current_server == 'survival') ? 'active' : '' ?>">
                    <span class="server-icon">🌲</span> Survival
                </a>
                <a href="?server=fullpvp" class="server-btn <?= ($current_server == 'fullpvp') ? 'active' : '' ?>">
                    <span class="server-icon">⚔️</span> FullPvP
                </a>
                <a href="?server=bedwars" class="server-btn <?= ($current_server == 'bedwars') ? 'active' : '' ?>">
                    <span class="server-icon">🛏️</span> BedWars
                </a>
                <a href="?server=skywars" class="server-btn <?= ($current_server == 'skywars') ? 'active' : '' ?>">
                    <span class="server-icon">🏹</span> SkyWars
                </a>
                <a href="?server=rankup" class="server-btn <?= ($current_server == 'rankup') ? 'active' : '' ?>">
                    <span class="server-icon">⛏️</span> RankUp
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    
    <div class="col-md-6 col-lg-3">
        <a href="players.php?search=<?= $my_user ?>&server=<?= $current_server ?>" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm hover-card">
                <div class="card-body text-center p-4">
                    <div class="icon-box bg-primary-light mb-3">
                        <i class="fa-solid fa-id-card fa-2x text-primary"></i>
                    </div>
                    <h5 class="text-dark fw-bold">Meu Perfil</h5>
                    <p class="text-muted small mb-0">Mural e estatísticas pessoais.</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-3">
        <a href="ranking.php?server=<?= $current_server ?>" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm hover-card">
                <div class="card-body text-center p-4">
                    <div class="icon-box bg-warning-light mb-3">
                        <i class="fa-solid fa-trophy fa-2x text-warning"></i>
                    </div>
                    <h5 class="text-dark fw-bold">Rankings</h5>
                    <p class="text-muted small mb-0">Top players do <strong><?= ucfirst($current_server) ?></strong>.</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-3">
        <a href="tickets.php" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm hover-card">
                <div class="card-body text-center p-4">
                    <div class="icon-box bg-info-light mb-3">
                        <i class="fa-solid fa-headset fa-2x text-info"></i>
                    </div>
                    <h5 class="text-dark fw-bold">Suporte</h5>
                    <p class="text-muted small mb-0">Abra um ticket ou veja chamados.</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-3">
        <a href="appeal.php" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm hover-card">
                <div class="card-body text-center p-4">
                    <div class="icon-box bg-danger-light mb-3">
                        <i class="fa-solid fa-scale-balanced fa-2x text-danger"></i>
                    </div>
                    <h5 class="text-dark fw-bold">Revisões</h5>
                    <p class="text-muted small mb-0">Solicite revisão de punições.</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-3">
        <a href="converter.php" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm hover-card">
                <div class="card-body text-center p-4">
                    <div class="icon-box bg-success-light mb-3">
                        <i class="fa-solid fa-money-bill-transfer fa-2x text-success"></i>
                    </div>
                    <h5 class="text-dark fw-bold">Conversor</h5>
                    <p class="text-muted small mb-0">Troque moedas entre servidores.</p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-3">
        <a href="donate.php" class="text-decoration-none">
            <div class="card h-100 border-0 shadow-sm hover-card">
                <div class="card-body text-center p-4">
                    <div class="icon-box bg-warning-light mb-3">
                        <i class="fa-solid fa-hand-holding-dollar fa-2x text-warning"></i>
                    </div>
                    <h5 class="text-dark fw-bold">Transferir Cash</h5>
                    <p class="text-muted small mb-0">Envie moedas para amigos.</p>
                </div>
            </div>
        </a>
    </div>
    
    <div class="col-md-6 col-lg-3 mb-4">
    <a href="loja.php" class="text-decoration-none">
        <div class="card h-100 border-0 shadow-sm hover-card">
            <div class="card-body text-center p-4">
                <i class="fa-solid fa-cart-shopping fa-2x text-primary mb-3"></i>
                <h5 class="text-dark fw-bold">Loja Virtual</h5>
                <p class="text-muted small">Fazer pedido ou compra.</p>
            </div>
        </div>
    </a>
</div>
    
    </div>
<div class="row mb-5">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary fw-bold"><i class="fa-solid fa-bullhorn"></i> Mural de Novidades</h5>
                <?php if($is_admin): ?>
                    <a href="news_manager.php" class="btn btn-sm btn-primary">Gerenciar Notícias</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php
                    $news_home = $conn->query("SELECT * FROM rs_news ORDER BY id DESC LIMIT 3");
                    if ($news_home && $news_home->num_rows > 0):
                        while ($news = $news_home->fetch_assoc()):
                            $color = "primary"; $icon = "fa-circle-info";
                            if ($news['type'] == 'UPDATE') { $icon = "fa-wand-magic-sparkles"; $color = "info"; }
                            if ($news['type'] == 'EVENTO') { $icon = "fa-calendar-star"; $color = "warning"; }
                            if ($news['type'] == 'AVISO') { $icon = "fa-triangle-exclamation"; $color = "danger"; }
                    ?>
                    <div class="list-group-item p-4">
                        <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                            <h5 class="mb-1 fw-bold text-dark">
                                <span class="badge bg-<?= $color ?> me-2 small"><i class="fa-solid <?= $icon ?>"></i> <?= $news['type'] ?></span>
                                <?= htmlspecialchars($news['title']) ?>
                            </h5>
                            <small class="text-muted"><i class="fa-regular fa-clock"></i> <?= date('d/m/Y', strtotime($news['created_at'])) ?></small>
                        </div>
                        <p class="mb-1 text-secondary" style="white-space: pre-wrap;"><?= htmlspecialchars($news['content']) ?></p>
                    </div>
                    <?php endwhile; else: ?>
                        <div class="text-center py-5 text-muted"><p>Nenhuma novidade recente.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white d-flex justify-content-between">
                <h5 class="mb-0"><i class="fa-solid fa-comments text-warning"></i> Chat Global (Beta)</h5>
                <small class="opacity-50">Tempo Real</small>
            </div>
            <div id="chat-box" class="card-body bg-light" style="height: 300px; overflow-y: auto; display: flex; flex-direction: column;">
            </div>
            <div class="card-footer bg-white">
                <form id="chatForm" class="input-group">
                    <input type="text" id="chatInput" class="form-control" placeholder="Digite sua mensagem..." autocomplete="off" maxlength="150">
                    <button class="btn btn-warning fw-bold" type="submit"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const chatBox = document.getElementById('chat-box');
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');

    function loadChat() {
    fetch('chat_backend.php?fetch=1')
        .then(response => response.json())
        .then(data => {
            chatBox.innerHTML = '';
            data.forEach(msg => {
                const isMe = msg.author === "<?= $_SESSION['admin_user'] ?>";
                chatBox.innerHTML += `
                    <div class="d-flex mb-3 ${isMe ? 'flex-row-reverse' : ''}">
                        <div class="flex-shrink-0">
                            <img src="https://minotar.net/avatar/${msg.author}/32.png" 
                                 class="rounded shadow-sm border border-light" 
                                 title="${msg.author}"
                                 alt="head">
                        </div>
                        
                        <div class="ms-2 me-2 ${isMe ? 'text-end' : ''}" style="max-width: 80%;">
                            <small class="fw-bold d-block mb-1" style="font-size: 0.7rem; color: #ffae00;">
                                ${msg.author}
                            </small>
                            <div class="p-2 rounded shadow-sm ${isMe ? 'bg-warning text-dark' : 'bg-white text-dark'}" 
                                 style="font-size: 0.85rem; word-wrap: break-word; display: inline-block;">
                                ${msg.message}
                                <small class="text-muted ms-2" style="font-size: 0.6rem;">${msg.time}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            chatBox.scrollTop = chatBox.scrollHeight;
        });
    }

    chatForm.onsubmit = (e) => {
        e.preventDefault();
        const msg = chatInput.value.trim();
        if (!msg) return;

        const formData = new FormData();
        formData.append('send_msg', '1');
        formData.append('message', msg);

        fetch('chat_backend.php', { method: 'POST', body: formData })
            .then(() => {
                chatInput.value = '';
                loadChat();
            });
    };

    setInterval(loadChat, 3000);
    loadChat();
</script>

<style>
/* CSS DO MENU DE SERVIDORES */
.server-menu-card {
    background-color: #1f1f1f;
    border: 1px solid #333;
    border-radius: 8px;
    overflow: hidden;
}

.server-menu-card .card-header {
    background-color: #2c2c2c;
    color: #f1f1f1;
    padding: 10px 15px;
    font-weight: bold;
    border-bottom: 1px solid #333;
}

.server-list {
    display: flex;
    flex-wrap: wrap; 
    padding: 10px;
    gap: 10px;
}

.server-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background-color: #2a2a2a;
    color: #aaa;
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 6px;
    border: 1px solid #3a3a3a;
    transition: all 0.3s ease;
    font-weight: 600;
    font-size: 0.95rem;
}

.server-btn:hover {
    background-color: #3a3a3a;
    color: #fff;
    transform: translateY(-2px);
    border-color: #555;
}

.server-btn.active {
    background-color: #d32f2f;
    color: white;
    border-color: #e53935;
    box-shadow: 0 0 10px rgba(211, 47, 47, 0.4);
}

.server-icon { font-size: 1.1em; }

/* Chat Background */
#chat-box {
    background-image: linear-gradient(rgba(255,255,255,0.9), rgba(255,255,255,0.9)), url('https://www.transparenttextures.com/patterns/cubes.png');
    padding: 15px;
    scroll-behavior: smooth;
}

.flex-shrink-0 img { transition: transform 0.2s; }
.flex-shrink-0 img:hover { transform: scale(1.2); z-index: 10; }

/* Ícones Personalizados */
.bg-success-light { background-color: rgba(25, 135, 84, 0.1); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; transition: all 0.3s ease; }
.hover-card:hover .bg-success-light { background-color: rgba(25, 135, 84, 0.2); transform: scale(1.1); }

.bg-warning-light { background-color: rgba(255, 193, 7, 0.1); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; transition: all 0.3s ease; }
.hover-card:hover .bg-warning-light { background-color: rgba(255, 193, 7, 0.25); transform: scale(1.1); }

.bg-primary-light { background-color: rgba(13, 110, 253, 0.1); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; transition: all 0.3s ease; }
.hover-card:hover .bg-primary-light { background-color: rgba(13, 110, 253, 0.2); transform: scale(1.1); }

.bg-info-light { background-color: rgba(13, 202, 240, 0.1); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; transition: all 0.3s ease; }
.hover-card:hover .bg-info-light { background-color: rgba(13, 202, 240, 0.2); transform: scale(1.1); }

.bg-danger-light { background-color: rgba(220, 53, 69, 0.1); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; transition: all 0.3s ease; }
.hover-card:hover .bg-danger-light { background-color: rgba(220, 53, 69, 0.2); transform: scale(1.1); }

.hover-card { transition: transform 0.2s; }
.hover-card:hover { transform: translateY(-5px); }
</style>

<?php include 'includes/footer.php'; ?>