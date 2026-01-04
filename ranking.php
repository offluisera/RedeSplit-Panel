<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// --- DETECTAR SERVIDOR ATUAL ---
$current_server = isset($_GET['server']) ? $_GET['server'] : 'geral';

// --- CONFIGURAÇÃO INTELIGENTE DOS RANKINGS ---
// Define o que mostrar no CARD 1 dependendo do servidor escolhido
$rank_config = [
    'geral'    => [
        'table' => 'rs_players',       'col_name' => 'name',        'col_stat' => 'coins',    'label' => 'Magnatas',    'unit' => '$', 'icon' => 'fa-sack-dollar', 'decimal' => 2
    ],
    'skyblock' => [
        'table' => 'rs_stats_skyblock','col_name' => 'player_name', 'col_stat' => 'balance',  'label' => 'Magnatas',    'unit' => '$', 'icon' => 'fa-sack-dollar', 'decimal' => 2
    ],
    'survival' => [
        'table' => 'rs_stats_survival','col_name' => 'player_name', 'col_stat' => 'balance',  'label' => 'Magnatas',    'unit' => '$', 'icon' => 'fa-sack-dollar', 'decimal' => 2
    ],
    'rankup'   => [
        'table' => 'rs_stats_rankup',  'col_name' => 'player_name', 'col_stat' => 'prestige', 'label' => 'Prestígio',   'unit' => 'P', 'icon' => 'fa-gem',         'decimal' => 0
    ],
    'fullpvp'  => [
        'table' => 'rs_stats_fullpvp', 'col_name' => 'player_name', 'col_stat' => 'kills',    'label' => 'Assassinos',  'unit' => '⚔', 'icon' => 'fa-skull',       'decimal' => 0
    ],
    'bedwars'  => [
        'table' => 'rs_stats_bedwars', 'col_name' => 'player_name', 'col_stat' => 'wins',     'label' => 'Vencedores',  'unit' => '🏆', 'icon' => 'fa-trophy',      'decimal' => 0
    ],
    'skywars'  => [
        'table' => 'rs_stats_skywars', 'col_name' => 'player_name', 'col_stat' => 'wins',     'label' => 'Vencedores',  'unit' => '🏆', 'icon' => 'fa-trophy',      'decimal' => 0
    ]
];

// Carrega a configuração ou usa Geral como padrão
$cfg = isset($rank_config[$current_server]) ? $rank_config[$current_server] : $rank_config['geral'];

// Função para formatar tempo
function formatPlaytime($millis) {
    if (!$millis) return "0 min";
    $seconds = floor($millis / 1000);
    $minutes = floor($seconds / 60);
    $hours = floor($minutes / 60);
    $days = floor($hours / 24);
    if ($days > 0) return "$days dias";
    if ($hours > 0) return "$hours h " . ($minutes % 60) . " m";
    return "$minutes min";
}

// Função auxiliar para medalhas
function getMedal($index) {
    if ($index == 1) return '<i class="fa-solid fa-trophy text-warning fa-lg"></i>';
    if ($index == 2) return '<i class="fa-solid fa-medal text-secondary fa-lg"></i>';
    if ($index == 3) return '<i class="fa-solid fa-medal text-danger fa-lg" style="color: #cd7f32 !important;"></i>';
    return '<span class="badge bg-light text-dark">#' . $index . '</span>';
}
?>

<div class="row mb-4">
    <div class="col-12 text-center">
        <h2 class="fw-bold"><i class="fa-solid fa-trophy text-warning"></i> Rankings do Servidor</h2>
        <p class="text-muted">Os melhores jogadores do modo <span class="badge bg-danger text-uppercase"><?= $current_server ?></span></p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-center flex-wrap gap-2">
            <a href="?server=geral" class="btn btn-<?= $current_server == 'geral' ? 'primary' : 'dark' ?> btn-sm">
                <i class="fa-solid fa-earth-americas"></i> Geral
            </a>
            <a href="?server=skyblock" class="btn btn-<?= $current_server == 'skyblock' ? 'primary' : 'dark' ?> btn-sm">
                <i class="fa-solid fa-cloud"></i> SkyBlock
            </a>
            <a href="?server=survival" class="btn btn-<?= $current_server == 'survival' ? 'primary' : 'dark' ?> btn-sm">
                <i class="fa-solid fa-tree"></i> Survival
            </a>
            <a href="?server=fullpvp" class="btn btn-<?= $current_server == 'fullpvp' ? 'primary' : 'dark' ?> btn-sm">
                <i class="fa-solid fa-shield-halved"></i> FullPvP
            </a>
            <a href="?server=rankup" class="btn btn-<?= $current_server == 'rankup' ? 'primary' : 'dark' ?> btn-sm">
                <i class="fa-solid fa-hammer"></i> RankUp
            </a>
            <a href="?server=bedwars" class="btn btn-<?= $current_server == 'bedwars' ? 'primary' : 'dark' ?> btn-sm">
                <i class="fa-solid fa-bed"></i> BedWars
            </a>
            <a href="?server=skywars" class="btn btn-<?= $current_server == 'skywars' ? 'primary' : 'dark' ?> btn-sm">
                <i class="fa-solid fa-bow-arrow"></i> SkyWars
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    
    <div class="col-lg-4">
        <div class="card shadow border-success h-100">
            <div class="card-header bg-success text-white text-center py-3">
                <h5 class="mb-0 fw-bold"><i class="fa-solid <?= $cfg['icon'] ?>"></i> TOP <?= strtoupper($cfg['label']) ?></h5>
                <small>Os destaques do <?= ucfirst($current_server) ?></small>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    // Verifica se a tabela existe para evitar erros
                    $table = $cfg['table'];
                    $check = $conn->query("SHOW TABLES LIKE '$table'");

                    if ($check && $check->num_rows > 0) {
                        $col_name = $cfg['col_name'];
                        $col_stat = $cfg['col_stat'];
                        
                        $sql = "SELECT $col_name as nick, $col_stat as valor FROM $table ORDER BY $col_stat DESC LIMIT 10";
                        $res = $conn->query($sql);
                        $pos = 1;
                        
                        if ($res && $res->num_rows > 0):
                            while ($row = $res->fetch_assoc()):
                                $bg = $pos <= 3 ? 'bg-light' : '';
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center p-3 <?= $bg ?>">
                        <div class="d-flex align-items-center">
                            <div class="me-3 text-center" style="width: 30px;"><?= getMedal($pos) ?></div>
                            <img src="https://minotar.net/avatar/<?= $row['nick'] ?>/32.png" class="rounded-circle me-2 shadow-sm">
                            <a href="players.php?search=<?= $row['nick'] ?>&server=<?= $current_server ?>" class="fw-bold text-decoration-none text-dark"><?= $row['nick'] ?></a>
                        </div>
                        <span class="badge bg-success rounded-pill">
                            <?php 
                                // Formatação inteligente (R$ com virgula, Kills sem virgula)
                                if ($cfg['unit'] == '$') echo '$ ' . number_format($row['valor'], 2, ',', '.');
                                else echo $cfg['unit'] . ' ' . number_format($row['valor'], 0, ',', '.');
                            ?>
                        </span>
                    </li>
                    <?php $pos++; endwhile; else: ?>
                        <li class="list-group-item text-center p-4 text-muted">Nenhum dado encontrado.</li>
                    <?php endif; 
                    } else { ?>
                        <li class="list-group-item text-center p-4 text-danger">Tabela não encontrada.</li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow border-info h-100">
            <div class="card-header bg-info text-white text-center py-3">
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-clock"></i> TOP VICIADOS</h5>
                <small>Tempo jogado no <?= ucfirst($current_server) ?></small>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    // Reutiliza a verificação de tabela
                    if ($check && $check->num_rows > 0) {
                        $col_name = $cfg['col_name'];
                        // A coluna de tempo é sempre 'playtime' em todas as nossas tabelas
                        $sql = "SELECT $col_name as nick, playtime FROM $table ORDER BY playtime DESC LIMIT 10";
                        $res = $conn->query($sql);
                        $pos = 1;
                        
                        if ($res && $res->num_rows > 0):
                            while ($row = $res->fetch_assoc()):
                                $bg = $pos <= 3 ? 'bg-light' : '';
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center p-3 <?= $bg ?>">
                        <div class="d-flex align-items-center">
                            <div class="me-3 text-center" style="width: 30px;"><?= getMedal($pos) ?></div>
                            <img src="https://minotar.net/avatar/<?= $row['nick'] ?>/32.png" class="rounded-circle me-2 shadow-sm">
                             <a href="players.php?search=<?= $row['nick'] ?>&server=<?= $current_server ?>" class="fw-bold text-decoration-none text-dark"><?= $row['nick'] ?></a>
                        </div>
                        <span class="text-info fw-bold small"><?= formatPlaytime($row['playtime']) ?></span>
                    </li>
                    <?php $pos++; endwhile; else: ?>
                        <li class="list-group-item text-center p-4 text-muted">Nenhum viciado ainda.</li>
                    <?php endif; 
                    } else { ?>
                         <li class="list-group-item text-center p-4 text-muted">-</li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow border-warning h-100">
            <div class="card-header bg-warning text-dark text-center py-3">
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-gem"></i> TOP CASH</h5>
                <small>Ranking Global (Toda a Rede)</small>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    // Cash é sempre buscado na rs_players (Global)
                    $sql = "SELECT name, cash FROM rs_players ORDER BY cash DESC LIMIT 10";
                    $res = $conn->query($sql);
                    $pos = 1;
                    
                    if ($res && $res->num_rows > 0):
                        while ($row = $res->fetch_assoc()):
                            $bg = $pos <= 3 ? 'bg-light' : '';
                    ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center p-3 <?= $bg ?>">
                        <div class="d-flex align-items-center">
                            <div class="me-3 text-center" style="width: 30px;"><?= getMedal($pos) ?></div>
                            <img src="https://minotar.net/avatar/<?= $row['name'] ?>/32.png" class="rounded-circle me-2 shadow-sm">
                             <a href="players.php?search=<?= $row['name'] ?>&server=<?= $current_server ?>" class="fw-bold text-decoration-none text-dark"><?= $row['name'] ?></a>
                        </div>
                        <span class="badge bg-warning text-dark rounded-pill">
                            ✪ <?= number_format($row['cash'], 0, ',', '.') ?>
                        </span>
                    </li>
                    <?php $pos++; endwhile; else: ?>
                        <li class="list-group-item text-center p-4 text-muted">Nenhum dado ainda.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>