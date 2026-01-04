<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// Apenas Staff
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['administrador', 'master'])) exit;

// INICIAR ENQUETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_poll'])) {
    $question = $conn->real_escape_string($_POST['question']);
    $opt1 = $conn->real_escape_string($_POST['opt1']);
    $opt2 = $conn->real_escape_string($_POST['opt2']);
    
    // Salva no MySQL
    $options_str = "$opt1|$opt2";
    $conn->query("INSERT INTO rs_polls (question, options, status) VALUES ('$question', '$options_str', 'OPEN')");
    $poll_id = $conn->insert_id;

    // Manda pro Redis
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');
        // Formato: POLL_START;ID_SQL;Pergunta|Op1|Op2
        $redis->publish('redesplit:channel', "POLL_START;$poll_id;$question|$opt1|$opt2");
        
        // Zera o contador live
        $redis->set("poll:live", json_encode(["1"=>0, "2"=>0]));
        
        $msg = "<div class='alert alert-success'>Enquete iniciada!</div>";
    } catch (Exception $e) {}
}

// ENCERRAR ENQUETE E SALVAR HISTÓRICO
if (isset($_GET['stop'])) {
    // 1. Conecta no Redis para pegar os votos finais
    $final_votes_json = "{}";
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');
        
        // Pega o JSON atual (Ex: {"1": 15, "2": 8})
        $data = $redis->get("poll:live");
        if ($data) $final_votes_json = $data;

        // Avisa o servidor para parar
        $redis->publish('redesplit:channel', "POLL_STOP;Admin;Stop");
        
        // Limpa o Redis
        $redis->del("poll:live");

    } catch (Exception $e) {
        // Se der erro no Redis, salvamos vazio ou zeros
    }

    // 2. Salva no MySQL (Status CLOSED + Resultados JSON)
    // O $conn->real_escape_string é importante para evitar erros com o JSON
    $safe_results = $conn->real_escape_string($final_votes_json);
    $conn->query("UPDATE rs_polls SET status = 'CLOSED', results = '$safe_results' WHERE status = 'OPEN'");

    echo "<script>window.location='polls.php';</script>";
    exit;
}

// Busca enquete ativa no banco para exibir o título
$active_poll = $conn->query("SELECT * FROM rs_polls WHERE status = 'OPEN' ORDER BY id DESC LIMIT 1")->fetch_assoc();
?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">Nova Enquete</div>
            <div class="card-body">
                <?php if(!$active_poll): ?>
                <form method="POST">
                    <input type="hidden" name="start_poll" value="true">
                    <div class="mb-3">
                        <label>Pergunta</label>
                        <input type="text" name="question" class="form-control" required placeholder="Ex: Qual evento agora?">
                    </div>
                    <div class="mb-3">
                        <label>Opção 1 (/votar 1)</label>
                        <input type="text" name="opt1" class="form-control" required placeholder="Ex: Spleef">
                    </div>
                    <div class="mb-3">
                        <label>Opção 2 (/votar 2)</label>
                        <input type="text" name="opt2" class="form-control" required placeholder="Ex: Corrida">
                    </div>
                    <button class="btn btn-success w-100">INICIAR VOTAÇÃO</button>
                </form>
                <?php else: ?>
                    <div class="alert alert-warning">Já existe uma enquete rodando!</div>
                    <a href="?stop=true" class="btn btn-danger w-100">ENCERRAR ATUAL</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow border-success">
            <div class="card-header bg-success text-white d-flex justify-content-between">
                <h5 class="mb-0">Resultados em Tempo Real</h5>
                <?php if($active_poll): ?><span class="badge bg-danger blink">AO VIVO</span><?php endif; ?>
            </div>
            <div class="card-body text-center">
                <?php if($active_poll): 
                    $opts = explode('|', $active_poll['options']);
                ?>
                    <h3><?= $active_poll['question'] ?></h3>
                    <hr>
                    
                    <div class="row mt-4">
                        <div class="col-6">
                            <h5>Opção 1: <b><?= $opts[0] ?></b></h5>
                            <h1 id="count-1" class="display-4 fw-bold">0</h1>
                            <div class="progress" style="height: 20px;">
                                <div id="bar-1" class="progress-bar bg-primary" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <h5>Opção 2: <b><?= $opts[1] ?></b></h5>
                            <h1 id="count-2" class="display-4 fw-bold">0</h1>
                            <div class="progress" style="height: 20px;">
                                <div id="bar-2" class="progress-bar bg-warning text-dark" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <p class="text-muted">Nenhuma votação ativa no momento.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if($active_poll): ?>
<script>
function updateVotes() {
    // Busca um arquivo PHP simples que lê o Redis
    fetch('api_poll_live.php')
        .then(res => res.json())
        .then(data => {
            // Data vem como { "1": 15, "2": 8 }
            let v1 = parseInt(data[1]) || 0;
            let v2 = parseInt(data[2]) || 0;
            let total = v1 + v2;

            document.getElementById('count-1').innerText = v1;
            document.getElementById('count-2').innerText = v2;

            if (total > 0) {
                let p1 = (v1 / total) * 100;
                let p2 = (v2 / total) * 100;
                document.getElementById('bar-1').style.width = p1 + "%";
                document.getElementById('bar-2').style.width = p2 + "%";
            }
        });
}
setInterval(updateVotes, 1000); // Atualiza a cada 1 segundo
</script>
<?php endif; ?>

<hr class="my-5">

<div class="row">
    <div class="col-12">
        <h4 class="mb-3 text-muted"><i class="fa-solid fa-clock-rotate-left"></i> Histórico Recente</h4>
        
        <?php
        $history = $conn->query("SELECT * FROM rs_polls WHERE status = 'CLOSED' ORDER BY id DESC LIMIT 5");
        
        if ($history->num_rows > 0):
            while ($poll = $history->fetch_assoc()):
                // Decodifica as opções e os resultados
                $opts = explode('|', $poll['options']);
                $res = json_decode($poll['results'], true);
                
                // Garante que não dê erro se estiver vazio
                $v1 = isset($res['1']) ? (int)$res['1'] : 0;
                $v2 = isset($res['2']) ? (int)$res['2'] : 0;
                $total = $v1 + $v2;
                
                // Calcula porcentagens (evita divisão por zero)
                $p1 = ($total > 0) ? round(($v1 / $total) * 100) : 0;
                $p2 = ($total > 0) ? round(($v2 / $total) * 100) : 0;
                
                // Define quem ganhou para destacar
                $winner_idx = ($v1 > $v2) ? 0 : (($v2 > $v1) ? 1 : -1);
        ?>
        
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light d-flex justify-content-between">
                <span class="fw-bold">#<?= $poll['id'] ?> - <?= htmlspecialchars($poll['question']) ?></span>
                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($poll['created_at'])) ?></small>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-5 text-end">
                        <small class="text-muted text-uppercase fw-bold"><?= htmlspecialchars($opts[0]) ?></small>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar bg-primary" style="width: <?= $p1 ?>%">
                                <?= $v1 ?> votos (<?= $p1 ?>%)
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-2 text-center text-muted fw-bold">VS</div>
                    
                    <div class="col-md-5 text-start">
                        <small class="text-muted text-uppercase fw-bold"><?= htmlspecialchars($opts[1]) ?></small>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar bg-warning text-dark" style="width: <?= $p2 ?>%">
                                <?= $v2 ?> votos (<?= $p2 ?>%)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php endwhile; else: ?>
            <p class="text-muted text-center">Nenhuma enquete antiga encontrada.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>