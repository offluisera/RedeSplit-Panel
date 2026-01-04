<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';
include 'includes/discord.php'; // Integração com Discord

$user = $_SESSION['admin_user'];
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
$is_staff = in_array($rank, ['ajudante', 'moderador', 'administrador', 'master']);

$view_ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = "";

// --- 1. CRIAR NOVO TICKET ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $subject = $conn->real_escape_string($_POST['subject']);
    $category = $conn->real_escape_string($_POST['category']);
    $message = $conn->real_escape_string($_POST['message']);

    // Cria o ticket
    $conn->query("INSERT INTO rs_tickets (author, subject, category, updated_at) VALUES ('$user', '$subject', '$category', NOW())");
    $ticket_id = $conn->insert_id;

    // Adiciona a primeira mensagem
    $conn->query("INSERT INTO rs_ticket_replies (ticket_id, user, message, is_staff) VALUES ($ticket_id, '$user', '$message', 0)");

    // >>> REDIS: AVISA A STAFF IN-GAME (INSTANTÂNEO) <<<
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');
        
        // Formato: TICKET_OPEN;NickAutor;Assunto
        $redis->publish('redesplit:channel', "TICKET_OPEN;$user;Ticket #$ticket_id - $subject");
    } catch (Exception $e) {
        // Se o Redis falhar, o ticket é criado mas sem aviso in-game. O log segue no Discord.
    }

    // >>> DISCORD WEBHOOK: AVISA NO CANAL DE SUPORTE <<<
    if (function_exists('sendDiscordLog')) {
        sendDiscordLog(
            "🎫 Novo Ticket #$ticket_id",
            "Um jogador solicitou suporte pelo site.",
            "3498db", // Azul
            [
                ["name" => "Autor", "value" => $user, "inline" => true],
                ["name" => "Categoria", "value" => $category, "inline" => true],
                ["name" => "Assunto", "value" => $subject, "inline" => false]
            ]
        );
    }

    $msg = "<div class='alert alert-success'>Ticket #$ticket_id criado com sucesso! Aguarde a resposta da equipe.</div>";
}

// --- 2. RESPONDER TICKET ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket'])) {
    $t_id = (int)$_POST['ticket_id'];
    $message = $conn->real_escape_string($_POST['message']);
    $staff_reply = $is_staff ? 1 : 0;
    
    // Insere resposta
    $conn->query("INSERT INTO rs_ticket_replies (ticket_id, user, message, is_staff) VALUES ($t_id, '$user', '$message', $staff_reply)");
    
    // Atualiza status e data
    $new_status = $is_staff ? 'RESPONDIDO' : 'ABERTO';
    $conn->query("UPDATE rs_tickets SET status = '$new_status', updated_at = NOW() WHERE id = $t_id");
    
    // >>> REDIS: AVISA O JOGADOR SE FOR STAFF RESPONDENDO <<<
    if ($is_staff) {
        $get_author = $conn->query("SELECT author FROM rs_tickets WHERE id = $t_id");
        if ($get_author->num_rows > 0) {
            $author_data = $get_author->fetch_assoc();
            $target_player = $author_data['author'];
            
            // Envia notificação In-Game via Redis para o dono do ticket
            try {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');
                
                // Formato: TICKET_REPLY;NickAlvo;MsgExtra
                $redis->publish('redesplit:channel', "TICKET_REPLY;$target_player;Ticket #$t_id respondido");
            } catch (Exception $e) {
                // Silêncio em caso de erro no Redis
            }
        }
    }
    
    // Redireciona
    echo "<script>window.location.href='tickets.php?id=$t_id';</script>";
    exit;
}

// --- 3. FECHAR TICKET ---
if (isset($_GET['close']) && $is_staff) {
    $close_id = (int)$_GET['close'];
    $conn->query("UPDATE rs_tickets SET status = 'FECHADO' WHERE id = $close_id");
    echo "<script>window.location.href='tickets.php';</script>";
    exit;
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h3><i class="fa-solid fa-headset text-primary"></i> Suporte & Tickets</h3>
        <p class="text-muted">Central de atendimento ao jogador.</p>
        <?= $msg ?>
    </div>
</div>

<?php if ($view_ticket_id > 0): 
    // --- TELA DE CHAT DO TICKET ---
    $sql_t = "SELECT * FROM rs_tickets WHERE id = $view_ticket_id";
    if (!$is_staff) $sql_t .= " AND author = '$user'";
    
    $ticket_res = $conn->query($sql_t);
    
    if ($ticket_res->num_rows > 0):
        $ticket = $ticket_res->fetch_assoc();
        $status_badges = ['ABERTO' => 'success', 'RESPONDIDO' => 'warning', 'FECHADO' => 'secondary'];
        $badge = isset($status_badges[$ticket['status']]) ? $status_badges[$ticket['status']] : 'secondary';
?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-<?= $badge ?>"><?= $ticket['status'] ?></span>
                        <span class="fw-bold ms-2">#<?= $ticket['id'] ?> - <?= htmlspecialchars($ticket['subject']) ?></span>
                        <br><small class="text-muted">Categoria: <?= $ticket['category'] ?></small>
                    </div>
                    <div>
                        <?php if($is_staff && $ticket['status'] != 'FECHADO'): ?>
                            <a href="tickets.php?close=<?= $ticket['id'] ?>" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Fechar este ticket?')">
                                <i class="fa-solid fa-lock"></i> Fechar
                            </a>
                        <?php endif; ?>
                        <a href="tickets.php" class="btn btn-light btn-sm"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
                    </div>
                </div>
                
                <div class="card-body bg-light" style="height: 500px; overflow-y: auto;">
                    <?php
                    $msgs = $conn->query("SELECT * FROM rs_ticket_replies WHERE ticket_id = $view_ticket_id ORDER BY created_at ASC");
                    while($m = $msgs->fetch_assoc()):
                        $is_me = ($m['user'] == $user);
                        $box_class = $is_me ? 'bg-primary text-white' : ($m['is_staff'] ? 'bg-warning text-dark' : 'bg-white border');
                        $align = $is_me ? 'align-items-end' : 'align-items-start';
                    ?>
                    <div class="d-flex flex-column <?= $align ?> mb-3">
                        <div class="d-flex align-items-center mb-1">
                            <small class="fw-bold text-muted me-2">
                                <?= $m['is_staff'] ? '<i class="fa-solid fa-shield-halved text-warning"></i> ' : '' ?>
                                <?= $m['user'] ?>
                            </small>
                            <small class="text-muted" style="font-size: 0.7em"><?= date('d/m H:i', strtotime($m['created_at'])) ?></small>
                        </div>
                        <div class="p-3 rounded shadow-sm <?= $box_class ?>" style="max-width: 80%; word-wrap: break-word;">
                            <?= nl2br(htmlspecialchars($m['message'])) ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <?php if ($ticket['status'] != 'FECHADO'): ?>
                <div class="card-footer bg-white">
                    <form method="POST">
                        <input type="hidden" name="reply_ticket" value="true">
                        <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                        <div class="input-group">
                            <textarea name="message" class="form-control" rows="2" placeholder="Digite sua resposta..." required></textarea>
                            <button class="btn btn-primary fw-bold"><i class="fa-solid fa-paper-plane"></i> Enviar</button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                    <div class="card-footer text-center text-muted fw-bold">Este ticket foi encerrado.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mt-3 mt-lg-0">
                <div class="card-body text-center">
                    <img src="https://minotar.net/avatar/<?= $ticket['author'] ?>/64.png" class="mb-3 rounded-circle shadow-sm">
                    <h5><?= $ticket['author'] ?></h5>
                    <p class="text-muted small">Criado em: <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-danger">Ticket não encontrado ou acesso negado.</div>
        <a href="tickets.php" class="btn btn-secondary">Voltar</a>
    <?php endif; ?>

<?php else: 
    // --- LISTAGEM DE TICKETS ---
?>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <?= $is_staff ? '<i class="fa-solid fa-list-ul"></i> Todos os Tickets' : '<i class="fa-solid fa-ticket"></i> Meus Tickets' ?>
                </h5>
                <?php if($is_staff): ?><span class="badge bg-warning text-dark">Modo Staff</span><?php endif; ?>
            </div>
            <div class="list-group list-group-flush">
                <?php
                $sql_list = "SELECT * FROM rs_tickets";
                if (!$is_staff) $sql_list .= " WHERE author = '$user'";
                $sql_list .= " ORDER BY updated_at DESC";
                
                $list_res = $conn->query($sql_list);
                
                if ($list_res->num_rows > 0):
                    while ($t = $list_res->fetch_assoc()):
                        $badge = $t['status'] == 'ABERTO' ? 'bg-success' : ($t['status'] == 'FECHADO' ? 'bg-secondary' : 'bg-warning text-dark');
                ?>
                <a href="tickets.php?id=<?= $t['id'] ?>" class="list-group-item list-group-item-action p-3 border-start border-4 border-<?= $t['status'] == 'ABERTO' ? 'success' : 'secondary' ?>">
                    <div class="d-flex w-100 justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 fw-bold text-dark">#<?= $t['id'] ?> - <?= htmlspecialchars($t['subject']) ?></h6>
                            <small class="text-muted">
                                <i class="fa-solid fa-user"></i> <?= $t['author'] ?> &bull; 
                                <i class="fa-solid fa-tag"></i> <?= $t['category'] ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <span class="badge <?= $badge ?> mb-1"><?= $t['status'] ?></span><br>
                            <small class="text-muted" style="font-size: 0.75em"><?= date('d/m H:i', strtotime($t['updated_at'])) ?></small>
                        </div>
                    </div>
                </a>
                <?php endwhile; else: ?>
                    <div class="p-5 text-center text-muted">
                        <i class="fa-regular fa-folder-open fa-3x mb-3 opacity-50"></i>
                        <p>Nenhum ticket encontrado.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fa-solid fa-plus-circle"></i> Novo Ticket</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="create_ticket" value="true">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Assunto</label>
                        <input type="text" name="subject" class="form-control" placeholder="Resumo do problema" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Categoria</label>
                        <select name="category" class="form-select">
                            <option>Dúvida Geral</option>
                            <option>Reportar Bug</option>
                            <option>Denúncia (Privada)</option>
                            <option>Financeiro / Loja</option>
                            <option>Apelação de Banimento</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Mensagem</label>
                        <textarea name="message" class="form-control" rows="5" placeholder="Explique seu problema com detalhes..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
                        <i class="fa-solid fa-paper-plane"></i> ABRIR TICKET
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>