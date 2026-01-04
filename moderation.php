<?php
include 'includes/session.php';
include 'includes/header.php';

// Segurança Básica: Apenas Staff (Ajudante+)
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['ajudante', 'moderador', 'administrador', 'master'])) {
    echo "<script>window.location='index.php';</script>";
    exit;
}

// Permissões Específicas
$is_mod_plus = in_array($rank, ['moderador', 'administrador', 'master']);
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h2 class="text-warning"><i class="fa-solid fa-shield-halved"></i> Central de Moderação</h2>
            <p class="text-muted">Ferramentas de suporte, chat e punições.</p>
        </div>
    </div>

    <div class="row justify-content-center g-4">
        
        <div class="col-md-6 col-lg-3">
            <a href="tickets.php" class="text-decoration-none">
                <div class="card shadow-sm h-100 border-0 hover-effect">
                    <div class="card-body text-center p-4">
                        <i class="fa-solid fa-headset fa-3x text-primary mb-3"></i>
                        <h5 class="text-dark fw-bold">Tickets</h5>
                        <p class="text-muted small">Atendimento ao jogador.</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="staffchat.php" class="text-decoration-none">
                <div class="card shadow-sm h-100 border-0 hover-effect">
                    <div class="card-body text-center p-4">
                        <i class="fa-solid fa-user-secret fa-3x text-dark mb-3"></i>
                        <h5 class="text-dark fw-bold">Staff Chat</h5>
                        <p class="text-muted small">Chat interno da equipe.</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="chat_monitor.php" class="text-decoration-none">
                <div class="card shadow-sm h-100 border-0 hover-effect">
                    <div class="card-body text-center p-4">
                        <i class="fa-solid fa-comment-slash fa-3x text-info mb-3"></i>
                        <h5 class="text-dark fw-bold">Monitor de Chat</h5>
                        <p class="text-muted small">Visualizar conversas em tempo real.</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="manage_filter.php" class="text-decoration-none">
                <div class="card shadow-sm h-100 border-0 hover-effect">
                    <div class="card-body text-center p-4">
                        <i class="fa-solid fa-cog fa-3x text-secondary mb-3"></i>
                        <h5 class="text-dark fw-bold">Filtro de Chat</h5>
                        <p class="text-muted small">Bloquear palavras proibidas.</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="punish.php" class="text-decoration-none">
                <div class="card shadow-sm h-100 border-0 hover-effect">
                    <div class="card-body text-center p-4">
                        <i class="fa-solid fa-gavel fa-3x text-danger mb-3"></i>
                        <h5 class="text-dark fw-bold">Punições</h5>
                        <p class="text-muted small">Aplicar e revogar bans/mutes.</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="top_infratores.php" class="text-decoration-none">
                <div class="card shadow-sm h-100 border-0 hover-effect">
                    <div class="card-body text-center p-4">
                        <i class="fa-solid fa-skull fa-3x text-dark mb-3"></i>
                        <h5 class="text-dark fw-bold">Top Infratores</h5>
                        <p class="text-muted small">Jogadores com mais punições.</p>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <a href="players.php" class="text-decoration-none">
                <div class="card shadow-sm h-100 border-0 hover-effect">
                    <div class="card-body text-center p-4">
                        <i class="fa-solid fa-comments fa-3x text-success mb-3"></i>
                        <h5 class="text-dark fw-bold">Logs de Chat</h5>
                        <p class="text-muted small">Histórico de mensagens.</p>
                    </div>
                </div>
            </a>
        </div>

        <?php if ($is_mod_plus): ?>
            
        <div class="col-md-6 col-lg-3">
            <a href="reports.php" class="text-decoration-none">
                <div class="card shadow border-0 hover-effect">
                    <div class="card-body text-center p-4">
                        <i class="fa-solid fa-triangle-exclamation fa-3x text-warning mb-3"></i>
                        <h5 class="text-dark fw-bold">Denúncias</h5>
                        <p class="text-muted small">Gerenciar reports de jogadores.</p>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="admin_appeals.php" class="text-decoration-none">
                <div class="card shadow border-0 hover-effect">
                    <div class="card-body text-center p-4">
                        <i class="fa-solid fa-scale-balanced fa-3x text-success mb-3"></i>
                        <h5 class="text-dark fw-bold">Revisões (Appeals)</h5>
                        <p class="text-muted small">Aceitar ou negar desbanimentos.</p>
                    </div>
                </div>
            </a>
        </div>

        <?php endif; ?>

    </div>
</div>

<style>
    .hover-effect:hover { transform: translateY(-5px); transition: 0.3s; background-color: #f8f9fa; }
</style>

<?php include 'includes/footer.php'; ?>