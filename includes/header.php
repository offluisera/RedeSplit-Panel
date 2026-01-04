<?php
// Define o cargo atual (se não tiver, assume membro)
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
$user_name = isset($_SESSION['admin_user']) ? $_SESSION['admin_user'] : 'Visitante';

// --- DEFINIÇÃO DE GRUPOS DE PERMISSÃO (HERANÇA COMPLETA) ---
$is_staff = in_array($rank, ['ajudante', 'moderador', 'administrador', 'master']);
$is_admin = in_array($rank, ['administrador', 'master']);

$my_user = $_SESSION['admin_user'];

// --- BUSCA DE DADOS EM TEMPO REAL (SPLITCOINS) ---
$sc_query = $conn->query("SELECT splitcoins FROM rs_players WHERE name = '$user_name'");
$player_sc = ($sc_query && $sc_query->num_rows > 0) ? $sc_query->fetch_assoc()['splitcoins'] : 0;

// Busca Notificações
$notif_res = $conn->query("SELECT * FROM rs_notifications WHERE player_name = '$my_user' AND is_read = 0 ORDER BY id DESC LIMIT 5");
$total_unread = $notif_res->num_rows;
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel RedeSplit</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
            --primary-color: #FFD700; /* AMARELO OURO FIXO */
            --dark-bg: #2f3542;
        }

        /* TÍTULO DA SIDEBAR CENTRALIZADO E AMARELO */
        .sidebar-header h4 {
            color: var(--primary-color) !important;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
            width: 100%;
        }

        /* ==========================================================
           CORREÇÕES TOTAIS MODO DARK (CORES, TEXTOS E BORDAS)
           ========================================================== */
        
        [data-bs-theme="dark"] body {
            background-color: #0f1113 !important;
            color: #e9ecef !important;
        }

        /* FORÇAR TEXTOS CLAROS NO DARK (CORRIGE FOTOS COM LETRAS ESCURAS) */
        [data-bs-theme="dark"] h1, [data-bs-theme="dark"] h2, [data-bs-theme="dark"] h3, 
        [data-bs-theme="dark"] h4, [data-bs-theme="dark"] h5, [data-bs-theme="dark"] h6,
        [data-bs-theme="dark"] .text-dark, [data-bs-theme="dark"] b, [data-bs-theme="dark"] strong,
        [data-bs-theme="dark"] label, [data-bs-theme="dark"] .form-label, 
        [data-bs-theme="dark"] .list-group-item span {
            color: #ffffff !important;
        }

        /* PRESERVAR BORDAS VERMELHAS E AMARELAS (RANKING E ALERTAS) */
        [data-bs-theme="dark"] .border-danger, 
        [data-bs-theme="dark"] [style*="border-color: red"],
        [data-bs-theme="dark"] [style*="border: 2px solid red"] {
            border-color: #ff4d4d !important;
            border-width: 2px !important;
        }

        [data-bs-theme="dark"] .border-warning, 
        [data-bs-theme="dark"] [style*="border-color: yellow"],
        [data-bs-theme="dark"] [style*="border: 2px solid yellow"] {
            border-color: #FFD700 !important;
            border-width: 2px !important;
        }

        /* REMOVER FUNDOS BRANCOS DE CARDS, NAVBAR E CHAT */
        [data-bs-theme="dark"] .card, 
        [data-bs-theme="dark"] .top-navbar,
        [data-bs-theme="dark"] .modal-content,
        [data-bs-theme="dark"] .list-group-item,
        [data-bs-theme="dark"] .recent-activity, 
        [data-bs-theme="dark"] .bg-white,
        [data-bs-theme="dark"] .dropdown-menu,
        [data-bs-theme="dark"] #chat-messages,
        [data-bs-theme="dark"] .chat-container,
        [data-bs-theme="dark"] .staff-chat-box,
        [data-bs-theme="dark"] .bg-light {
            background-color: #1a1d21 !important;
            border-color: #2d3238 !important;
            color: #f8f9fa !important;
        }

        /* INPUTS E TABELAS NO DARK */
        [data-bs-theme="dark"] .table { color: #ffffff !important; }
        [data-bs-theme="dark"] .table thead th { background-color: #25292d !important; color: #FFD700 !important; }
        [data-bs-theme="dark"] .form-control, [data-bs-theme="dark"] .form-select {
            background-color: #25292d !important;
            border-color: #383e45 !important;
            color: #ffffff !important;
        }

        /* Minecraft Colors Fix para Converter.php */
        [data-bs-theme="dark"] span[style*="color"] {
            color: inherit !important;
            filter: brightness(1.2);
        }

        /* ESTILOS ESTRUTURAIS */
        body { font-family: 'Inter', sans-serif; background-color: #f1f2f6; transition: all 0.3s ease; }
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; top: 0; left: 0; background: var(--dark-bg); color: white; z-index: 1000; display: flex; flex-direction: column; }
        .sidebar-header { padding: 25px 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-menu a { display: block; padding: 12px 25px; color: #dfe4ea; text-decoration: none; border-left: 4px solid transparent; font-weight: 500; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.05); color: white !important; border-left-color: var(--primary-color); }
        .menu-divider { padding: 10px 25px; font-size: 0.75rem; text-transform: uppercase; color: #747d8c; font-weight: bold; margin-top: 15px; }
        .main-content { margin-left: var(--sidebar-width); padding: 20px; }
        .top-navbar { background: white; padding: 15px 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .theme-switch { cursor: pointer; padding: 8px; border-radius: 50%; color: var(--bs-secondary-color); transition: 0.3s; }
        
        @media (max-width: 768px) {
            .sidebar { left: calc(var(--sidebar-width) * -1); }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h4 class="fw-bold mb-0">REDE SPLIT</h4>
    </div>
    <div class="sidebar-menu">
        <div class="menu-divider">GERAL</div>
        <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>"><i class="fa-solid fa-gauge-high me-2"></i> Dashboard</a>
        <a href="players.php?search=<?= $user_name ?>"><i class="fa-solid fa-user me-2"></i> Meu Perfil</a>

        <?php if ($is_staff): ?>
            <div class="menu-divider">EQUIPE</div>
            <a href="moderation.php" class="<?= basename($_SERVER['PHP_SELF']) == 'moderation.php' ? 'active' : '' ?>" style="color: #f1c40f;"><i class="fa-solid fa-shield-halved me-2"></i> Central de Moderação</a>
        <?php endif; ?>

        <?php if ($is_admin): ?>
            <div class="menu-divider">ADMINISTRAÇÃO</div>
            <a href="administration.php" class="<?= basename($_SERVER['PHP_SELF']) == 'administration.php' ? 'active' : '' ?>" style="color: #e74c3c;"><i class="fa-solid fa-gears me-2"></i> Central Administrativa</a>
            <a href="admin_splitcoins.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin_splitcoins.php' ? 'active' : '' ?>" style="color: #FFD700;"><i class="fa-solid fa-coins me-2"></i> Gerenciar SplitCoins</a>
        <?php endif; ?>
    </div>
</div>

<div class="main-content">
    <div class="top-navbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-secondary d-md-none me-3" onclick="document.getElementById('sidebar').classList.toggle('active')">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h5 class="mb-0">Olá, <span style="color:#FFD700; font-weight:700;"><?= $user_name ?></span></h5>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <div class="theme-switch shadow-sm border px-3 py-1 rounded-pill" id="themeToggle">
                <i class="fa-solid fa-moon fs-5" id="themeIcon"></i>
            </div>

            <div class="d-flex align-items-center p-1 px-2 bg-body-tertiary rounded-pill border shadow-sm">
                <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 22px; height: 22px;">
                    <i class="fa-solid fa-coins text-white" style="font-size: 11px;"></i>
                </div>
                <div class="d-flex flex-column" style="line-height: 1.1;">
                    <span class="fw-bold" style="font-size: 13px;"><?= number_format($player_sc, 0, ',', '.') ?></span>
                    <span class="text-muted fw-bold" style="font-size: 7px;">SPLITCOINS</span>
                </div>
            </div>

            <div class="dropdown">
                <button class="btn btn-link p-0 position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-bell fs-5 text-secondary"></i>
                    <?php if($total_unread > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.5em;"><?= $total_unread ?></span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="width: 280px;">
                    <li class="bg-dark text-white p-2 fw-bold small text-center">NOTIFICAÇÕES</li>
                    <div style="max-height: 250px; overflow-y: auto;">
                        <?php if($total_unread > 0): while($n = $notif_res->fetch_assoc()): ?>
                            <li><a class="dropdown-item p-3 border-bottom small text-wrap" href="#"><?= $n['message'] ?></a></li>
                        <?php endwhile; ?>
                            <li class="p-2 text-center bg-body-tertiary">
                                <a href="clear_all_notif.php" class="text-danger small text-decoration-none fw-bold">Limpar tudo</a>
                            </li>
                        <?php else: ?>
                            <li class="p-4 text-center text-muted small">Sem notificações.</li>
                        <?php endif; ?>
                    </div>
                </ul>
            </div>

            <div class="dropdown">
                <button class="btn btn-link p-0 d-flex align-items-center gap-2 text-decoration-none" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="https://minotar.net/avatar/<?= $user_name ?>/32.png" class="rounded shadow-sm border border-secondary" width="35">
                    <i class="fa-solid fa-chevron-down text-muted small d-none d-md-block"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li class="p-3 bg-body-secondary text-center">
                        <h6 class="fw-bold mb-0"><?= $user_name ?></h6>
                        <span class="badge bg-dark mt-1"><?= strtoupper($rank) ?></span>
                    </li>
                    <li><a class="dropdown-item py-2 small" href="players.php?search=<?= $user_name ?>"><i class="fa-solid fa-user me-2 text-primary"></i> Perfil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger fw-bold small" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Sair</a></li>
                </ul>
            </div>
        </div>
    </div>

<script>
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const html = document.documentElement;

    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-bs-theme', savedTheme);
    updateIcon(savedTheme);

    themeToggle.addEventListener('click', () => {
        const currentTheme = html.getAttribute('data-bs-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        html.setAttribute('data-bs-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateIcon(newTheme);
    });

    function updateIcon(theme) {
        if (theme === 'dark') {
            themeIcon.className = 'fa-solid fa-sun fs-5 text-warning';
        } else {
            themeIcon.className = 'fa-solid fa-moon fs-5 text-secondary';
        }
    }
</script>