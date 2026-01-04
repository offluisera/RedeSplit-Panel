<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$msg = "";
$player_data = null;

// --- PROCESSAR A ALTERAÇÃO DE RANK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rank'])) {
    $target_player = $_POST['player_name'];
    $new_rank = $_POST['new_rank'];
    $admin_nick = $_SESSION['admin_user']; // Pega o admin logado

    // Agora salvamos o operador na tabela
    $stmt = $conn->prepare("INSERT INTO rs_web_commands (player_name, action, value, operator) VALUES (?, 'setrank', ?, ?)");
    $stmt->bind_param("sss", $target_player, $new_rank, $admin_nick);
    
    if ($stmt->execute()) {
        $msg = "<div class='alert alert-success mt-3'>Comando enviado com sucesso por <b>$admin_nick</b>!</div>";
        $search = $target_player; 
    } else {
        $msg = "<div class='alert alert-danger mt-3'>Erro: " . $conn->error . "</div>";
    }
}

// --- BUSCAR DADOS DO JOGADOR ---
if ($search) {
    $stmt = $conn->prepare("SELECT * FROM rs_players WHERE name = ?");
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $result = $stmt->get_result();
    $player_data = $result->fetch_assoc();
}

// --- BUSCAR LISTA DE RANKS (Para o Select) ---
$ranks_list = $conn->query("SELECT * FROM rs_ranks ORDER BY rank_id ASC");
?>

<div class="row">
    <div class="col-md-12">
        <h4><i class="fa-solid fa-user-pen"></i> Atualização de Usuário</h4>
        <p class="text-muted">Pesquise um jogador para alterar o cargo dele.</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto flex-grow-1">
                <input type="text" name="search" class="form-control form-control-lg" placeholder="Digite o nick do jogador..." value="<?= htmlspecialchars($search) ?>" required>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-search"></i> Pesquisar</button>
            </div>
        </form>
        <?= $msg ?>
    </div>
</div>

<?php if ($search && !$player_data): ?>
    <div class="alert alert-warning text-center">
        <i class="fa-solid fa-circle-exclamation fa-2x mb-3"></i><br>
        O jogador <b><?= htmlspecialchars($search) ?></b> não foi encontrado no banco de dados.<br>
        <small>Ele precisa entrar no servidor ao menos uma vez.</small>
    </div>
<?php endif; ?>

<?php if ($player_data): ?>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow border-0">
                <div class="card-header bg-dark text-white text-center py-3">
                    <h5 class="mb-0">Gerenciar: <?= $player_data['name'] ?></h5>
                </div>
                <div class="card-body text-center p-4">
                    <img src="https://minotar.net/avatar/<?= $player_data['name'] ?>/100.png" class="rounded-circle shadow mb-3" alt="Skin">
                    
                    <h4 class="fw-bold"><?= $player_data['name'] ?></h4>
                    <p class="mb-4">
                        Cargo Atual: <span class="badge bg-info text-dark text-uppercase"><?= $player_data['rank_id'] ?></span>
                    </p>

                    <hr>

                    <form method="POST" class="text-start mt-4">
                        <input type="hidden" name="player_name" value="<?= $player_data['name'] ?>">
                        <input type="hidden" name="update_rank" value="true">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Selecionar Novo Cargo:</label>
                            <select name="new_rank" class="form-select form-select-lg">
                                <?php 
                                // Reseta o ponteiro do rank_list para garantir que liste tudo
                                $ranks_list->data_seek(0);
                                while($r = $ranks_list->fetch_assoc()): 
                                    $selected = ($r['rank_id'] == $player_data['rank_id']) ? 'selected' : '';
                                    // Pega a cor para estilizar (remove o &)
                                    $colorCode = str_replace('&', '', $r['prefix']); 
                                ?>
                                    <option value="<?= $r['rank_id'] ?>" <?= $selected ?>>
                                        <?= $r['display_name'] ?> (<?= $r['rank_id'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
                            <i class="fa-solid fa-check"></i> CONFIRMAR ALTERAÇÃO
                        </button>
                    </form>

                </div>
                <div class="card-footer text-center text-muted">
                    <small><i class="fa-solid fa-clock"></i> A alteração pode levar até 5 segundos para refletir no jogo.</small>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>