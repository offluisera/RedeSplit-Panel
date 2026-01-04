<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// Apenas Staff
if (!in_array($_SESSION['user_rank'], ['moderador', 'administrador', 'master'])) {
    echo "<script>window.location='index.php';</script>"; exit;
}

$staff = $_SESSION['admin_user'];
$msg = "";

// --- LÓGICA DE DECISÃO ---
if (isset($_GET['id']) && isset($_GET['action'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action']; // 'ACEITO' ou 'NEGADO'
    
    // Pega nome do jogador para desbanir
    $info = $conn->query("SELECT player_name FROM rs_appeals WHERE id=$id")->fetch_assoc();
    $target = $info['player_name'];

    // Atualiza status no banco
    $conn->query("UPDATE rs_appeals SET status='$action', staff_handler='$staff', handled_at=NOW() WHERE id=$id");

// SE ACEITOU -> DOIS PASSOS: 1. ATUALIZA BANCO, 2. AVISA REDIS (FORÇA BRUTA)
    if ($action == 'ACEITO') {
        
        // 1. Remove o banimento no MySQL (Banco de Dados)
        $conn->query("UPDATE rs_punishments SET active = 0 WHERE player_name = '$target' AND type = 'BAN' AND active = 1");
        
        // 2. Manda COMANDOS para o Servidor via Redis
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');
            
            // Manda VÁRIOS comandos para garantir que um funcione
            // O Java vai receber e executar um por um
            $redis->publish('redesplit:channel', "EXECUTE_CONSOLE;pardon $target"); // Bukkit Padrão
            $redis->publish('redesplit:channel', "EXECUTE_CONSOLE;unban $target");  // Essentials/Litebans
            $redis->publish('redesplit:channel', "EXECUTE_CONSOLE;kick $target §aSeu banimento foi revogado! Relogue."); // Kicka para atualizar
            
            $msg = "<div class='alert alert-success'>✅ Revisão ACEITA! Comandos de desbanimento enviados para o console.</div>";
        } catch (Exception $e) {
            $msg = "<div class='alert alert-warning'>⚠️ Salvo no Banco, mas erro no Redis: " . $e->getMessage() . "</div>";
        }
    } else {
        $msg = "<div class='alert alert-secondary'>❌ Revisão NEGADA.</div>";
    }
}

// Busca pendentes
$appeals = $conn->query("SELECT * FROM rs_appeals WHERE status='PENDENTE' ORDER BY created_at ASC");
?>

<div class="container mt-4">
    <h3><i class="fa-solid fa-gavel text-danger"></i> Gerenciar Revisões</h3>
    <?= $msg ?>

    <div class="row g-3">
        <?php if ($appeals->num_rows > 0): ?>
            <?php while($row = $appeals->fetch_assoc()): ?>
            <div class="col-md-6">
                <div class="card shadow-sm border-warning">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between">
                        <b>Revisão #<?= $row['id'] ?></b>
                        <span><?= date('d/m H:i', strtotime($row['created_at'])) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <img src="https://minotar.net/avatar/<?= $row['player_name'] ?>/40" class="rounded me-2">
                            <h5 class="mb-0"><?= $row['player_name'] ?></h5>
                        </div>
                        
                        <div class="bg-light p-3 rounded mb-3 border">
                            <em>"<?= htmlspecialchars($row['reason']) ?>"</em>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="admin_appeals.php?id=<?= $row['id'] ?>&action=ACEITO" class="btn btn-success flex-grow-1" onclick="return confirm('Tem certeza? Isso vai DESBANIR o jogador.')">
                                <i class="fa-solid fa-check"></i> ACEITAR (Desbanir)
                            </a>
                            <a href="admin_appeals.php?id=<?= $row['id'] ?>&action=NEGADO" class="btn btn-danger flex-grow-1" onclick="return confirm('Negar revisão?')">
                                <i class="fa-solid fa-xmark"></i> NEGAR
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="fa-solid fa-folder-open fa-3x mb-3"></i>
                <p>Nenhum pedido de revisão pendente.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>