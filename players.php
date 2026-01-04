<?php
include 'includes/session.php';
include 'includes/db.php';

// --- CONFIGURAÇÃO DO SERVIDOR ATUAL ---
$current_server = isset($_GET['server']) ? $_GET['server'] : 'geral';

// --- FUNÇÕES AUXILIARES (INTACTAS) ---
function getPlayerBadges($conn, $player_data) {
    $badges = [];
    $name = $player_data['name'];
    $playtime_hours = ($player_data['playtime'] / 1000) / 3600;

    if ($playtime_hours >= 5) {
        $badges[] = ['icon' => 'fa-clock', 'color' => '#3498db', 'title' => 'Viciado I', 'desc' => '5h+ de jogo'];
    }

    $stmt = $conn->prepare("SELECT badge_id FROM rs_player_badges WHERE player_name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        if ($row['badge_id'] == 'primordio') {
            $badges[] = ['icon' => 'fa-seedling', 'color' => '#2ecc71', 'title' => 'Primórdios', 'desc' => 'Jogador da fase Beta'];
        }
    }
    return $badges;
}

function formatPlaytime($millis) {
    if (!$millis) return "0 min";
    $seconds = floor($millis / 1000);
    $minutes = floor($seconds / 60);
    $hours = floor($minutes / 60);
    $days = floor($hours / 24);
    if ($days > 0) return "$days dias, " . ($hours % 24) . " horas";
    if ($hours > 0) return "$hours horas, " . ($minutes % 60) . " min";
    return "$minutes minutos";
}

// --- PROCESSAMENTO DE FORMULÁRIOS (POST - INTACTO) ---
if (isset($_POST['save_signature'])) {
    $new_sig = $conn->real_escape_string(strip_tags($_POST['chat_signature']));
    $new_color = $conn->real_escape_string($_POST['join_color']);
    $target_player = $_POST['player_name'];
    
    if (mb_strlen($new_sig) <= 60) {
        $conn->query("UPDATE rs_players SET join_message = '$new_sig', join_color = '$new_color' WHERE name = '$target_player'");
        echo "<script>window.location.href='players.php?search=$target_player&server=$current_server&success=1';</script>";
        exit;
    }
}

if (isset($_GET['delete_mural'])) {
    $comment_id = (int)$_GET['delete_mural'];
    $my_nick = $_SESSION['admin_user'];
    $rank = $_SESSION['user_rank'];
    
    $res = $conn->query("SELECT author, profile_owner FROM rs_profile_comments WHERE id = $comment_id");
    if ($res && $res->num_rows > 0) {
        $comment = $res->fetch_assoc();
        $is_staff = in_array($rank, ['moderador', 'administrador', 'master']);
        
        if ($my_nick == $comment['profile_owner'] || $my_nick == $comment['author'] || $is_staff) {
            $conn->query("DELETE FROM rs_profile_comments WHERE id = $comment_id");
            header("Location: players.php?search=" . $comment['profile_owner'] . "&server=$current_server&msg=deletado");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'post_mural') {
    $author = $_SESSION['admin_user'];
    $profile_owner = $conn->real_escape_string($_POST['profile_owner']);
    $content = trim(strip_tags($_POST['mural_content']));
    
    if (mb_strlen($content) > 200) {
        header("Location: players.php?search=$profile_owner&server=$current_server&error=muito_longo"); exit;
    }
    
    $check_spam = $conn->query("SELECT created_at FROM rs_profile_comments WHERE author = '$author' ORDER BY id DESC LIMIT 1");
    if ($check_spam && $row = $check_spam->fetch_assoc()) {
        if ((time() - strtotime($row['created_at'])) < 60) {
            header("Location: players.php?search=$profile_owner&server=$current_server&error=spam"); exit;
        }
    }

    if (!empty($content)) {
        $stmt = $conn->prepare("INSERT INTO rs_profile_comments (profile_owner, author, comment) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $profile_owner, $author, $content);
        if ($stmt->execute()) {
            $notif_msg = "<b>$author</b> deixou um novo recado no seu mural.";
            $conn->query("INSERT INTO rs_notifications (player_name, type, message) VALUES ('$profile_owner', 'MURAL', '$notif_msg')");
            header("Location: players.php?search=$profile_owner&server=$current_server&mural=success");
            exit;
        }
    }
}

include 'includes/header.php';

// --- LÓGICA DE BUSCA PRINCIPAL (INTACTA) ---
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
$is_staff = in_array($rank, ['ajudante', 'moderador', 'administrador', 'master']);
$can_edit_rank = in_array($rank, ['administrador', 'master']);
$can_view_chat = in_array($rank, ['administrador', 'master']);

$search_result = null;
$error_msg = "";
$is_searching = isset($_GET['search']) && !empty($_GET['search']);

if ($is_searching) {
    $search = $conn->real_escape_string($_GET['search']);

    $db_map = [
        'geral'    => ['table' => 'rs_players',       'col_coins' => 'coins',   'col_time' => 'playtime'],
        'skyblock' => ['table' => 'rs_stats_skyblock','col_coins' => 'balance', 'col_time' => 'playtime'],
        'survival' => ['table' => 'rs_stats_survival','col_coins' => 'balance', 'col_time' => 'playtime'],
        'fullpvp'  => ['table' => 'rs_stats_fullpvp', 'col_coins' => 'balance', 'col_time' => 'playtime'],
        'bedwars'  => ['table' => 'rs_stats_bedwars', 'col_coins' => 'coins',   'col_time' => 'playtime'],
        'skywars'  => ['table' => 'rs_stats_skywars', 'col_coins' => 'coins',   'col_time' => 'playtime'],
        'rankup'   => ['table' => 'rs_stats_rankup',  'col_coins' => 'balance', 'col_time' => 'playtime'],
    ];

    $cfg = isset($db_map[$current_server]) ? $db_map[$current_server] : $db_map['geral'];
    
    $table_check = $conn->query("SHOW TABLES LIKE '{$cfg['table']}'");
    $table_exists = ($table_check && $table_check->num_rows > 0);

    if (!$table_exists && $current_server != 'geral') {
        $cfg = $db_map['geral']; 
    }

    if ($cfg['table'] == 'rs_players') {
        $sql = "SELECT p.*, 
                       p.coins as show_coins, 
                       p.playtime as show_playtime,
                       t.rank_id as vip_rank, t.expires as vip_expires 
                FROM rs_players p 
                LEFT JOIN rs_temp_ranks t ON p.uuid = t.uuid 
                WHERE p.name = '$search'";
    } else {
        $sql = "SELECT p.*, 
                       COALESCE(s.{$cfg['col_coins']}, 0) as show_coins, 
                       COALESCE(s.{$cfg['col_time']}, 0) as show_playtime,
                       t.rank_id as vip_rank, t.expires as vip_expires 
                FROM rs_players p 
                LEFT JOIN {$cfg['table']} s ON p.uuid = s.uuid 
                LEFT JOIN rs_temp_ranks t ON p.uuid = t.uuid 
                WHERE p.name = '$search'";
    }

    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $search_result = $result->fetch_assoc();
        $search_result['coins'] = $search_result['show_coins'];
        $search_result['playtime'] = $search_result['show_playtime'];
    } else {
        $error_msg = "Jogador não encontrado.";
    }
}
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h3><i class="fa-solid fa-users-viewfinder"></i> Jogadores & Perfil</h3>
        <?php if($current_server != 'geral'): ?>
            <span class="badge bg-danger fs-6 border border-light">
                <i class="fa-solid fa-server"></i> <?= ucfirst($current_server) ?>
            </span>
        <?php else: ?>
            <span class="badge bg-secondary fs-6">Geral</span>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="server" value="<?= htmlspecialchars($current_server) ?>">
            
            <input type="text" name="search" class="form-control form-control-lg" placeholder="Digite o nick do jogador para ver o perfil completo..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" required>
            <button class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-search"></i> BUSCAR</button>
            <?php if($is_searching): ?>
                <a href="players.php" class="btn btn-secondary px-3" data-bs-toggle="tooltip" title="Voltar para lista online"><i class="fa-solid fa-times"></i></a>
            <?php endif; ?>
        </form>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger mt-3 mb-0"><?= $error_msg ?></div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$is_searching): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0 text-muted"><i class="fa-solid fa-circle text-success animate-pulse"></i> Online na Rede agora</h5>
        <span class="badge bg-white text-dark shadow-sm border" id="total-network-count">0 Jogadores</span>
    </div>

    <div id="playersGrid" class="row g-3">
        <div class="col-12 text-center py-5 text-muted" id="loading-msg">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="mt-2">Carregando jogadores de todos os servidores...</div>
        </div>
    </div>
    
    <div id="empty-state" class="text-center py-5 d-none">
        <i class="fa-solid fa-ghost fa-3x text-muted mb-3 opacity-50"></i>
        <h5 class="text-muted">Ninguém online no momento.</h5>
    </div>

    <script>
        let globalPlayerMap = {}; // { "Nick": "survival" }
        const grid = document.getElementById('playersGrid');
        const totalCountEl = document.getElementById('total-network-count');
        const loadingMsg = document.getElementById('loading-msg');
        const emptyState = document.getElementById('empty-state');

        // Conecta ao Redis Stream
        const evtSource = new EventSource('api_performance.php');

        evtSource.onmessage = function(e) {
            try {
                const packet = JSON.parse(e.data);
                if (packet.error) return;

                const serverName = packet.server;
                // A lista vem do JSON que configuramos no Java (stats.list)
                const playerList = packet.stats.list || []; 

                updatePlayerList(serverName, playerList);

            } catch (err) {
                console.error("Erro processando pacote:", err);
            }
        };

        function updatePlayerList(serverSource, currentPlayersOnServer) {
            if(loadingMsg) loadingMsg.classList.add('d-none');

            // 1. Remove quem saiu desse servidor
            for (const [nick, server] of Object.entries(globalPlayerMap)) {
                if (server === serverSource) {
                    if (!currentPlayersOnServer.includes(nick)) {
                        delete globalPlayerMap[nick];
                    }
                }
            }

            // 2. Adiciona quem entrou
            currentPlayersOnServer.forEach(nick => {
                globalPlayerMap[nick] = serverSource;
            });

            renderGrid();
        }

        function renderGrid() {
            grid.innerHTML = '';
            const allPlayers = Object.keys(globalPlayerMap).sort();
            
            if(totalCountEl) totalCountEl.innerText = allPlayers.length + " Jogadores";

            if (allPlayers.length === 0) {
                if(emptyState) emptyState.classList.remove('d-none');
                return;
            } else {
                if(emptyState) emptyState.classList.add('d-none');
            }

            allPlayers.forEach(nick => {
                const server = globalPlayerMap[nick];
                const serverDisplay = server.charAt(0).toUpperCase() + server.slice(1);
                const serverColor = getServerColor(server);

                // Cria o Card
                const col = document.createElement('div');
                col.className = 'col-6 col-md-4 col-lg-2 fade-in'; // Grid responsivo
                
                // Link para buscar o perfil ao clicar
                col.innerHTML = `
                    <a href="players.php?search=${nick}&server=${server}" class="text-decoration-none">
                        <div class="card shadow-sm border-0 h-100 player-card hover-lift">
                            <div class="card-body p-2 d-flex flex-column align-items-center text-center">
                                <img src="https://minotar.net/helm/${nick}/64.png" class="rounded mb-2 shadow-sm" width="48" height="48">
                                <h6 class="mb-1 fw-bold text-dark text-truncate w-100 small">${nick}</h6>
                                <span class="badge ${serverColor} w-100 text-truncate" style="font-size: 0.65rem;">
                                    ${serverDisplay}
                                </span>
                            </div>
                        </div>
                    </a>
                `;
                grid.appendChild(col);
            });
        }

        function getServerColor(id) {
            id = id.toLowerCase();
            if (id.includes('surv')) return 'bg-success';
            if (id.includes('sky')) return 'bg-info text-dark';
            if (id.includes('rank')) return 'bg-warning text-dark';
            if (id.includes('pvp')) return 'bg-danger';
            if (id.includes('lobby') || id.includes('hub')) return 'bg-secondary';
            if (id.includes('bed')) return 'bg-danger';
            return 'bg-primary';
        }
    </script>
    
    <style>
        .hover-lift { transition: transform 0.2s, box-shadow 0.2s; }
        .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-pulse { animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
    </style>
<?php endif; ?>


<?php if ($search_result): ?>
<div class="row fade-in">
    <div class="col-md-4">
        <div class="card shadow h-100 text-center p-3">
            <div class="card-body">
                <img src="https://minotar.net/armor/body/<?= $search_result['name'] ?>/150.png" class="mb-3 drop-shadow">
                <h2 class="fw-bold mb-0"><?= $search_result['name'] ?></h2>
                <span class="badge bg-secondary mb-3 mt-1 fs-6"><?= strtoupper($search_result['rank_id']) ?></span>
                
                <div class="medal-hub mt-2">
                    <?php 
                    $player_badges = getPlayerBadges($conn, $search_result);
                    foreach ($player_badges as $badge): 
                    ?>
                        <div class="medal-item" 
                             style="color: <?= $badge['color'] ?>;" 
                             data-bs-toggle="tooltip" title="<?= $badge['title'] ?>: <?= $badge['desc'] ?>">
                            <i class="fa-solid <?= $badge['icon'] ?>"></i>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($player_badges)): ?>
                        <small class="text-muted opacity-50" style="font-size: 0.8rem;">Sem medalhas</small>
                    <?php endif; ?>
                </div>

                <p class="text-muted small mt-3">
                    Último IP: <b><?= $is_staff ? ($search_result['last_ip'] ?? 'Desconhecido') : 'Oculto' ?></b><br>
                    Último Login: <b><?= $search_result['last_login'] ? date('d/m/Y H:i', strtotime($search_result['last_login'])) : 'Nunca' ?></b>
                </p>

                <?php if ($is_staff): ?>
                    <hr>
                    <div class="d-grid gap-2">
                        <a href="punish.php?player=<?= $search_result['name'] ?>" class="btn btn-outline-danger fw-bold"><i class="fa-solid fa-gavel"></i> PUNIR</a>
                        <?php if ($can_edit_rank): ?>
                            <button class="btn btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalRank"><i class="fa-solid fa-id-badge"></i> CARGO</button>
                        <?php endif; ?>
                        <?php if ($can_view_chat): ?>
                            <a href="chat_history.php?player=<?= $search_result['name'] ?>" class="btn btn-outline-secondary fw-bold"><i class="fa-solid fa-comments"></i> CHAT</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card border-info shadow-sm h-100">
                    <div class="card-body text-center">
                        <h6 class="text-info mb-1"><i class="fa-regular fa-clock"></i> Tempo Online</h6>
                        <h4 class="fw-bold"><?= formatPlaytime($search_result['playtime']) ?></h4>
                        <small class="text-muted">No servidor <?= ucfirst($current_server) ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-success shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-success mb-2"><i class="fa-solid fa-wallet"></i> Economia</h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Coins: <b class="text-success">$ <?= number_format($search_result['coins'], 2, ',', '.') ?></b></span>
                            <span class="badge bg-light text-dark border"><?= ucfirst($current_server) ?></span>
                        </div>
                        <div class="mt-2 text-end">
                            <span class="small">Cash: <b class="text-warning">✪ <?= number_format($search_result['cash'], 0, ',', '.') ?></b></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($is_staff): ?>
            <div class="col-12">
                <div class="card shadow border-danger">
                    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fa-solid fa-user-secret"></i> Contas Vinculadas (Mesmo IP)</h6>
                        <span class="badge bg-white text-danger">Detector de Fakes</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php
                            $uuid = $search_result['uuid'];
                            $sql_fakes = "SELECT DISTINCT p.name, p.rank_id, p.last_login 
                                          FROM rs_ip_history h1 
                                          JOIN rs_ip_history h2 ON h1.ip = h2.ip 
                                          JOIN rs_players p ON h2.uuid = p.uuid 
                                          WHERE h1.uuid = '$uuid' AND h2.uuid != '$uuid' LIMIT 5";
                            $fakes = $conn->query($sql_fakes);
                            
                            if ($fakes && $fakes->num_rows > 0):
                                while($f = $fakes->fetch_assoc()): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <img src="https://minotar.net/avatar/<?= $f['name'] ?>/16.png" class="rounded-circle me-1">
                                        <a href="players.php?search=<?= $f['name'] ?>&server=<?= $current_server ?>" class="fw-bold text-decoration-none text-dark"><?= $f['name'] ?></a>
                                        <span class="badge bg-secondary ms-1" style="font-size: 0.7em"><?= $f['rank_id'] ?></span>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">Visto: <?= date('d/m', strtotime($f['last_login'])) ?></small>
                                        <a href="punish.php?player=<?= $f['name'] ?>" class="btn btn-xs btn-outline-danger ms-2"><i class="fa-solid fa-gavel"></i></a>
                                    </div>
                                </div>
                            <?php endwhile; else: ?>
                                <div class="p-3 text-center text-muted">Nenhuma conta vinculada encontrada.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="col-12">
                <div class="card shadow-sm border-warning">
                    <div class="card-header bg-warning text-dark"><h6 class="mb-0"><i class="fa-solid fa-crown"></i> Status VIP</h6></div>
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <?php if ($search_result['vip_rank']): ?>
                            <span class="fw-bold text-success">ATIVO: <?= strtoupper($search_result['vip_rank']) ?></span>
                            <small>Expira: <?= date('d/m/Y H:i', strtotime($search_result['vip_expires'])) ?></small>
                        <?php else: ?>
                            <span class="text-muted">Nenhum VIP temporário ativo.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($_SESSION['admin_user'] == $search_result['name'] || $is_staff): ?>
            <?php 
            $colors = ['§b' => '#33ccff', '§a' => '#55ff55', '§e' => '#ffff55', '§6' => '#ffaa00', '§d' => '#ff55ff', '§f' => '#ffffff'];
            ?>
            <div class="col-12">
                <div class="card shadow-sm border-dark">
                    <div class="card-header bg-dark text-white fw-bold"><i class="fa-solid fa-palette"></i> Personalizar Entrada VIP</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="player_name" value="<?= $search_result['name'] ?>">
                            <input type="hidden" name="save_signature" value="1">
                            
                            <label class="small fw-bold text-muted mb-2">COR DA MENSAGEM:</label>
                            <div class="d-flex gap-2 mb-3">
                                <?php foreach ($colors as $code => $hex): ?>
                                    <label class="color-option">
                                        <input type="radio" name="join_color" value="<?= $code ?>" <?= ($search_result['join_color'] == $code) ? 'checked' : '' ?> style="display:none;">
                                        <span class="color-dot" style="background-color: <?= $hex ?>;"></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <label class="small fw-bold text-muted mb-1">SUA FRASE:</label>
                            <div class="input-group">
                                <input type="text" name="chat_signature" class="form-control" placeholder="Ex: Cheguei para dominar!" maxlength="60" value="<?= htmlspecialchars($search_result['join_message'] ?? '') ?>">
                                <button type="submit" class="btn btn-outline-dark">Salvar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="col-12">
                <div class="card shadow-sm border-primary">
                    <div class="card-header bg-primary text-white"><h6 class="mb-0"><i class="fa-solid fa-comments"></i> Mural de Amizades</h6></div>
                    <div class="card-body">
                        <form method="POST" class="mb-4" id="muralForm">
                            <input type="hidden" name="action" value="post_mural">
                            <input type="hidden" name="profile_owner" value="<?= $search_result['name'] ?>">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><img src="https://minotar.net/avatar/<?= $_SESSION['admin_user'] ?>/20.png" class="rounded"></span>
                                <textarea name="mural_content" id="mural_text" class="form-control" rows="1" maxlength="200" placeholder="Deixe um recado..." required></textarea>
                                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i></button>
                            </div>
                            <div class="text-end mt-1"><small id="charCount" class="text-muted" style="font-size: 0.75rem;">0 / 200</small></div>
                        </form>

                        <div class="mural-list" style="max-height: 400px; overflow-y: auto;">
                            <?php
                            $target = $search_result['name'];
                            $mural_res = $conn->query("SELECT * FROM rs_profile_comments WHERE profile_owner = '$target' ORDER BY id DESC LIMIT 15");
                            if ($mural_res && $mural_res->num_rows > 0):
                                while($m = $mural_res->fetch_assoc()): ?>
                                    <div class="d-flex mb-3 border-bottom pb-2">
                                        <div class="flex-shrink-0"><img src="https://minotar.net/avatar/<?= $m['author'] ?>/32.png" class="rounded shadow-sm"></div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <a href="players.php?search=<?= $m['author'] ?>&server=<?= $current_server ?>" class="fw-bold text-decoration-none text-primary"><?= $m['author'] ?></a>
                                                    <small class="text-muted ms-2"><?= date('d/m H:i', strtotime($m['created_at'])) ?></small>
                                                </div>
                                                <?php if ($_SESSION['admin_user'] == $m['profile_owner'] || $_SESSION['admin_user'] == $m['author'] || $is_staff): ?>
                                                    <a href="players.php?delete_mural=<?= $m['id'] ?>" class="text-danger" onclick="return confirm('Apagar?')"><i class="fa-solid fa-trash-can"></i></a>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-dark small mt-1"><?= htmlspecialchars($m['comment']) ?></div>
                                        </div>
                                    </div>
                            <?php endwhile; else: ?>
                                <div class="text-center py-4 text-muted"><p>Nenhuma mensagem ainda.</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if($can_edit_rank && $search_result): ?>
<div class="modal fade" id="modalRank" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form action="update_user.php" method="POST">
            <div class="modal-header"><h5 class="modal-title">Alterar Cargo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="player_name" value="<?= $search_result['name'] ?>">
                <input type="hidden" name="update_rank" value="true">
                <label>Novo Cargo:</label>
                <select name="new_rank" class="form-select">
                    <?php $rks = $conn->query("SELECT rank_id FROM rs_ranks"); while($r = $rks->fetch_assoc()): ?>
                        <option value="<?= $r['rank_id'] ?>" <?= $r['rank_id'] == $search_result['rank_id'] ? 'selected' : '' ?>><?= strtoupper($r['rank_id']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Salvar</button></div>
        </form>
    </div></div>
</div>
<?php endif; ?>

<script>
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl) })

    const textarea = document.getElementById('mural_text');
    const charCount = document.getElementById('charCount');
    if(textarea) {
        textarea.addEventListener('input', () => {
            const length = textarea.value.length;
            charCount.textContent = `${length} / 200`;
            charCount.className = length >= 180 ? 'text-danger fw-bold' : 'text-muted';
        });
    }
</script>

<style>
.medal-hub { display: flex; justify-content: center; align-items: center; gap: 12px; margin: 10px auto; min-height: 30px; padding: 5px 15px; background: rgba(0, 0, 0, 0.03); border-radius: 50px; width: fit-content; }
.medal-item { font-size: 1.2rem; transition: all 0.2s ease; cursor: pointer; }
.medal-item:hover { transform: translateY(-3px) scale(1.1); }
.color-option input:checked + .color-dot { border-color: #000 !important; transform: scale(1.2); box-shadow: 0 0 5px rgba(0,0,0,0.3); }
.color-dot { width: 30px; height: 30px; display: inline-block; border-radius: 50%; cursor: pointer; border: 2px solid transparent; }
</style>

<?php include 'includes/footer.php'; ?>