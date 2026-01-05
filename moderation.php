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

<style>
:root {
    --mod-primary: #f1c40f;
    --mod-danger: #e74c3c;
    --mod-success: #27ae60;
    --mod-info: #3498db;
    --mod-dark: #2c3e50;
    --mod-warning: #f39c12;
}

/* Hero Section */
.mod-hero {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    border-radius: 25px;
    padding: 40px 30px;
    margin-bottom: 40px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: fadeInDown 0.6s ease;
}

.mod-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(241, 196, 15, 0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.mod-hero-content {
    position: relative;
    z-index: 1;
    text-align: center;
}

.mod-hero h1 {
    color: var(--mod-primary);
    font-size: 2.5rem;
    font-weight: 900;
    margin-bottom: 10px;
    text-shadow: 0 5px 15px rgba(241, 196, 15, 0.3);
}

.mod-hero-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--mod-primary) 0%, #f39c12 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    box-shadow: 0 10px 30px rgba(241, 196, 15, 0.4);
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.mod-hero-icon i {
    font-size: 2.5rem;
    color: white;
}

.mod-subtitle {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1.1rem;
    margin: 0;
}

.rank-badge {
    display: inline-block;
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    padding: 8px 20px;
    border-radius: 30px;
    font-weight: 700;
    font-size: 0.9rem;
    letter-spacing: 1px;
    margin-top: 15px;
    box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
}

/* Section Headers */
.section-header {
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 3px solid var(--mod-primary);
    position: relative;
    animation: fadeInLeft 0.6s ease;
}

.section-header h3 {
    color: var(--mod-dark);
    font-weight: 800;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

[data-bs-theme="dark"] .section-header h3 {
    color: white;
}

.section-header .section-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--mod-primary) 0%, #f39c12 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
}

/* Mod Cards */
.mod-card {
    background: white;
    border-radius: 20px;
    padding: 0;
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
    height: 100%;
    text-decoration: none;
    display: block;
}

[data-bs-theme="dark"] .mod-card {
    background: #1a1a2e;
}

.mod-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: var(--card-color);
    transform: scaleX(0);
    transition: transform 0.4s ease;
}

.mod-card:hover::before {
    transform: scaleX(1);
}

.mod-card:hover {
    transform: translateY(-12px) scale(1.02);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.mod-card-body {
    padding: 30px 25px;
    text-align: center;
}

.mod-icon-wrapper {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    background: var(--card-gradient);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    transition: all 0.4s ease;
}

.mod-card:hover .mod-icon-wrapper {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
}

.mod-icon-wrapper::after {
    content: '';
    position: absolute;
    inset: -5px;
    border-radius: 20px;
    background: var(--card-gradient);
    z-index: -1;
    opacity: 0;
    transition: opacity 0.4s ease;
}

.mod-card:hover .mod-icon-wrapper::after {
    opacity: 0.3;
    animation: ripple 1.5s infinite;
}

@keyframes ripple {
    0% { transform: scale(1); opacity: 0.3; }
    100% { transform: scale(1.4); opacity: 0; }
}

.mod-icon-wrapper i {
    font-size: 2.2rem;
    color: white;
}

.mod-card-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--mod-dark);
    margin-bottom: 10px;
}

[data-bs-theme="dark"] .mod-card-title {
    color: white;
}

.mod-card-description {
    color: #7f8c8d;
    font-size: 0.9rem;
    line-height: 1.5;
    margin: 0;
}

[data-bs-theme="dark"] .mod-card-description {
    color: rgba(255, 255, 255, 0.6);
}

.mod-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    box-shadow: 0 4px 10px rgba(231, 76, 60, 0.3);
    animation: pulse-badge 2s ease-in-out infinite;
}

@keyframes pulse-badge {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* Special Card - Moderator+ */
.mod-card-premium {
    background: linear-gradient(135deg, #c0392b 0%, #8e44ad 100%);
    box-shadow: 0 15px 40px rgba(192, 57, 43, 0.3);
}

.mod-card-premium .mod-card-title,
.mod-card-premium .mod-card-description {
    color: white !important;
}

.mod-card-premium .mod-icon-wrapper {
    background: white;
}

.mod-card-premium .mod-icon-wrapper i {
    color: #c0392b;
}

.mod-card-premium:hover {
    box-shadow: 0 25px 60px rgba(192, 57, 43, 0.4);
}

/* Stats Bar */
.stats-bar {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    animation: fadeInUp 0.6s ease;
}

[data-bs-theme="dark"] .stats-bar {
    background: #1a1a2e;
}

.stat-item {
    text-align: center;
    padding: 15px;
    position: relative;
}

.stat-item:not(:last-child)::after {
    content: '';
    position: absolute;
    right: 0;
    top: 20%;
    bottom: 20%;
    width: 1px;
    background: rgba(0, 0, 0, 0.1);
}

[data-bs-theme="dark"] .stat-item:not(:last-child)::after {
    background: rgba(255, 255, 255, 0.1);
}

.stat-value {
    font-size: 2rem;
    font-weight: 900;
    color: var(--mod-primary);
    display: block;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.85rem;
    color: #7f8c8d;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

[data-bs-theme="dark"] .stat-label {
    color: rgba(255, 255, 255, 0.6);
}

/* Animations */
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInLeft {
    from {
        opacity: 0;
        transform: translateX(-30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.fade-in-stagger {
    animation: fadeInUp 0.6s ease both;
}

.stagger-1 { animation-delay: 0.1s; }
.stagger-2 { animation-delay: 0.2s; }
.stagger-3 { animation-delay: 0.3s; }
.stagger-4 { animation-delay: 0.4s; }
.stagger-5 { animation-delay: 0.5s; }
.stagger-6 { animation-delay: 0.6s; }
.stagger-7 { animation-delay: 0.7s; }
.stagger-8 { animation-delay: 0.8s; }

/* Responsive */
@media (max-width: 768px) {
    .mod-hero h1 {
        font-size: 2rem;
    }
    
    .mod-icon-wrapper {
        width: 70px;
        height: 70px;
    }
    
    .mod-icon-wrapper i {
        font-size: 1.8rem;
    }
    
    .stat-item:not(:last-child)::after {
        display: none;
    }
}
</style>

<!-- Hero Section -->
<div class="mod-hero">
    <div class="mod-hero-content">
        <div class="mod-hero-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h1>Central de Moderação</h1>
        <p class="mod-subtitle">Ferramentas avançadas para gerenciar e proteger a comunidade</p>
        <span class="rank-badge">
            <i class="fa-solid fa-crown me-2"></i><?= strtoupper($rank) ?>
        </span>
    </div>
</div>

<!-- Stats Bar -->
<div class="stats-bar">
    <div class="row">
        <div class="col-6 col-md-3">
            <div class="stat-item">
                <span class="stat-value">
                    <i class="fa-solid fa-users"></i>
                    <?php
                    // Exemplo: buscar total de jogadores online
                    $online_count = 0;
                    echo $online_count;
                    ?>
                </span>
                <span class="stat-label">Online</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-item">
                <span class="stat-value">
                    <i class="fa-solid fa-ticket"></i>
                    <?php
                    // Exemplo: buscar tickets abertos
                    $tickets_open = 0;
                    echo $tickets_open;
                    ?>
                </span>
                <span class="stat-label">Tickets</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-item">
                <span class="stat-value">
                    <i class="fa-solid fa-gavel"></i>
                    <?php
                    // Exemplo: buscar punições ativas
                    $active_punishments = 0;
                    echo $active_punishments;
                    ?>
                </span>
                <span class="stat-label">Punições</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-item">
                <span class="stat-value">
                    <i class="fa-solid fa-flag"></i>
                    <?php
                    // Exemplo: buscar reports pendentes
                    $pending_reports = 0;
                    echo $pending_reports;
                    ?>
                </span>
                <span class="stat-label">Denúncias</span>
            </div>
        </div>
    </div>
</div>

<!-- Ferramentas Básicas -->
<div class="section-header">
    <h3>
        <div class="section-icon">
            <i class="fa-solid fa-toolbox"></i>
        </div>
        Ferramentas Essenciais
    </h3>
</div>

<div class="row g-4 mb-5">
    <div class="col-md-6 col-lg-4 col-xl-3 fade-in-stagger stagger-1">
        <a href="tickets.php" class="mod-card" style="--card-color: #3498db; --card-gradient: linear-gradient(135deg, #3498db 0%, #2980b9 100%)">
            <div class="mod-card-body">
                <div class="mod-icon-wrapper">
                    <i class="fa-solid fa-headset"></i>
                </div>
                <h5 class="mod-card-title">Tickets</h5>
                <p class="mod-card-description">Sistema de atendimento e suporte ao jogador</p>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-4 col-xl-3 fade-in-stagger stagger-2">
        <a href="staffchat.php" class="mod-card" style="--card-color: #2c3e50; --card-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%)">
            <div class="mod-card-body">
                <div class="mod-icon-wrapper">
                    <i class="fa-solid fa-user-secret"></i>
                </div>
                <h5 class="mod-card-title">Staff Chat</h5>
                <p class="mod-card-description">Comunicação interna da equipe</p>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-4 col-xl-3 fade-in-stagger stagger-3">
        <a href="chat_monitor.php" class="mod-card" style="--card-color: #16a085; --card-gradient: linear-gradient(135deg, #16a085 0%, #1abc9c 100%)">
            <div class="mod-card-body">
                <div class="mod-icon-wrapper">
                    <i class="fa-solid fa-eye"></i>
                </div>
                <h5 class="mod-card-title">Monitor de Chat</h5>
                <p class="mod-card-description">Visualizar conversas em tempo real</p>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-4 col-xl-3 fade-in-stagger stagger-4">
        <a href="manage_filter.php" class="mod-card" style="--card-color: #7f8c8d; --card-gradient: linear-gradient(135deg, #7f8c8d 0%, #95a5a6 100%)">
            <div class="mod-card-body">
                <div class="mod-icon-wrapper">
                    <i class="fa-solid fa-filter"></i>
                </div>
                <h5 class="mod-card-title">Filtro de Chat</h5>
                <p class="mod-card-description">Gerenciar palavras bloqueadas</p>
            </div>
        </a>
    </div>
</div>

<!-- Ações de Moderação -->
<div class="section-header">
    <h3>
        <div class="section-icon">
            <i class="fa-solid fa-gavel"></i>
        </div>
        Ações de Moderação
    </h3>
</div>

<div class="row g-4 mb-5">
    <div class="col-md-6 col-lg-4 col-xl-3 fade-in-stagger stagger-1">
        <a href="punish.php" class="mod-card" style="--card-color: #e74c3c; --card-gradient: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)">
            <span class="mod-badge">CRÍTICO</span>
            <div class="mod-card-body">
                <div class="mod-icon-wrapper">
                    <i class="fa-solid fa-gavel"></i>
                </div>
                <h5 class="mod-card-title">Aplicar Punições</h5>
                <p class="mod-card-description">Bans, mutes e advertências</p>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-4 col-xl-3 fade-in-stagger stagger-2">
        <a href="top_infratores.php" class="mod-card" style="--card-color: #8e44ad; --card-gradient: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%)">
            <div class="mod-card-body">
                <div class="mod-icon-wrapper">
                    <i class="fa-solid fa-skull-crossbones"></i>
                </div>
                <h5 class="mod-card-title">Top Infratores</h5>
                <p class="mod-card-description">Jogadores com mais punições</p>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-4 col-xl-3 fade-in-stagger stagger-3">
        <a href="players.php" class="mod-card" style="--card-color: #27ae60; --card-gradient: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%)">
            <div class="mod-card-body">
                <div class="mod-icon-wrapper">
                    <i class="fa-solid fa-comments"></i>
                </div>
                <h5 class="mod-card-title">Histórico de Chat</h5>
                <p class="mod-card-description">Logs completos de mensagens</p>
            </div>
        </a>
    </div>
</div>

<?php if ($is_mod_plus): ?>
<!-- Ferramentas Avançadas (Moderador+) -->
<div class="section-header">
    <h3>
        <div class="section-icon">
            <i class="fa-solid fa-crown"></i>
        </div>
        Ferramentas Avançadas
        <span class="badge bg-danger ms-2">Moderador+</span>
    </h3>
</div>

<div class="row g-4 mb-5">
    <div class="col-md-6 col-lg-4 col-xl-3 fade-in-stagger stagger-1">
        <a href="reports.php" class="mod-card-premium">
            <span class="mod-badge">NOVO</span>
            <div class="mod-card-body">
                <div class="mod-icon-wrapper">
                    <i class="fa-solid fa-flag"></i>
                </div>
                <h5 class="mod-card-title">Denúncias</h5>
                <p class="mod-card-description">Gerenciar reports de jogadores</p>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-lg-4 col-xl-3 fade-in-stagger stagger-2">
        <a href="admin_appeals.php" class="mod-card-premium">
            <div class="mod-card-body">
                <div class="mod-icon-wrapper">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <h5 class="mod-card-title">Revisões</h5>
                <p class="mod-card-description">Análise de pedidos de desban</p>
            </div>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="stats-bar">
            <h5 class="mb-4 fw-bold">
                <i class="fa-solid fa-bolt text-warning me-2"></i>
                Ações Rápidas
            </h5>
            <div class="d-flex flex-wrap gap-3">
                <a href="punish.php" class="btn btn-danger">
                    <i class="fa-solid fa-ban me-2"></i>Banir Jogador
                </a>
                <a href="tickets.php?status=open" class="btn btn-primary">
                    <i class="fa-solid fa-ticket me-2"></i>Ver Tickets Abertos
                </a>
                <a href="chat_monitor.php" class="btn btn-info">
                    <i class="fa-solid fa-eye me-2"></i>Monitorar Chat
                </a>
                <?php if($is_mod_plus): ?>
                <a href="reports.php?status=pending" class="btn btn-warning">
                    <i class="fa-solid fa-flag me-2"></i>Denúncias Pendentes
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Adicionar tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
})

// Animar contadores
function animateValue(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        element.textContent = Math.floor(progress * (end - start) + start);
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// Animar stats ao carregar
document.addEventListener('DOMContentLoaded', function() {
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach((stat, index) => {
        const icon = stat.querySelector('i');
        const value = parseInt(stat.textContent.replace(/\D/g, '')) || 0;
        stat.innerHTML = icon.outerHTML;
        setTimeout(() => {
            animateValue(stat, 0, value, 1000);
        }, index * 100);
    });
});
</script>

<?php include 'includes/footer.php'; ?>
