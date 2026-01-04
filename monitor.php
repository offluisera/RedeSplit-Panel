<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['administrador', 'master'])) {
    echo "<div class='alert alert-danger'>Acesso Negado.</div>";
    include 'includes/footer.php';
    exit;
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="row mb-4">
    <div class="col-12">
        <h3 class="mb-0"><i class="fa-solid fa-network-wired text-primary"></i> Monitoramento de Rede</h3>
        <p class="text-muted">Acompanhamento em tempo real de TPS, RAM e Jogadores.</p>
    </div>
</div>

<div id="serverGrid" class="row g-4">
    </div>

<script>
    // LISTA DE SERVIDORES
    const myServers = ['geral', 'skyblock', 'rankup', 'fullpvp', 'bedwars', 'survival'];

    const charts = {};
    const lastUpdate = {}; 
    const grid = document.getElementById('serverGrid');

    document.addEventListener("DOMContentLoaded", function() {
        myServers.forEach(server => {
            createServerCard(server);
            lastUpdate[server] = 0;
        });
        
        setInterval(checkOfflineStatus, 2000); 
    });

    const evtSource = new EventSource('api_performance.php');

    evtSource.onmessage = function(e) {
        try {
            const packet = JSON.parse(e.data);
            if (packet.error) return;

            const serverId = packet.server.toLowerCase(); 
            
            if (!charts[serverId]) {
                createServerCard(serverId);
            }

            updateServerChart(serverId, packet.stats);
            
            lastUpdate[serverId] = Date.now();
            setOnlineStatus(serverId, true);

        } catch (err) {
            console.error(err);
        }
    };

    function createServerCard(name) {
        const displayName = name.charAt(0).toUpperCase() + name.slice(1);
        
        const col = document.createElement('div');
        col.className = 'col-md-6 col-lg-4 fade-in';
        col.id = `card-col-${name}`;
        
        // HTML ATUALIZADO COM NOVOS CAMPOS
        col.innerHTML = `
            <div class="card shadow-sm h-100 border-0" id="card-${name}">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold text-secondary">
                        <i class="fa-solid fa-server me-2"></i> ${displayName}
                    </h5>
                    <span class="badge bg-secondary" id="badge-tps-${name}">OFFLINE</span>
                </div>
                <div class="card-body">
                    <div style="height: 150px; position: relative;" class="mb-3">
                        <div id="overlay-${name}" class="d-flex align-items-center justify-content-center bg-white" 
                             style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:10; opacity: 0.9;">
                            <h6 class="text-muted"><i class="fa-solid fa-power-off"></i> Sem conexão</h6>
                        </div>
                        <canvas id="chart-${name}"></canvas>
                    </div>
                    
                    <div class="row g-2 text-muted small">
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <i class="fa-solid fa-memory text-primary"></i> RAM<br>
                                <span class="fw-bold text-dark" id="ram-${name}">-- / -- MB</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <i class="fa-solid fa-users text-info"></i> Players<br>
                                <span class="fw-bold text-dark" id="players-${name}">-- / --</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-2 text-end">
                         <span id="status-dot-${name}" class="text-muted small">● Offline</span>
                    </div>
                </div>
            </div>
        `;

        grid.appendChild(col);

        const ctx = document.getElementById(`chart-${name}`).getContext('2d');
        charts[name] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'TPS',
                        data: [],
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false },
                    y: { min: 0, max: 22, display: false }
                }
            }
        });
    }

    function updateServerChart(name, stats) {
        const chart = charts[name];
        
        // Elementos
        const tpsBadge = document.getElementById(`badge-tps-${name}`);
        const ramText = document.getElementById(`ram-${name}`);
        const playerText = document.getElementById(`players-${name}`);
        
        // Atualiza Valores
        tpsBadge.innerText = "TPS: " + stats.tps.toFixed(2);
        
        // RAM: Usada / Máxima
        ramText.innerText = `${stats.ram_used} / ${stats.ram_max} MB`;
        
        // Players: Online / Max (Se não vier no JSON ainda, usa 0)
        const online = stats.online || 0;
        const maxPl = stats.max_players || 0;
        playerText.innerText = `${online} / ${maxPl}`;

        // Cores TPS
        if (stats.tps >= 19.0) tpsBadge.className = 'badge bg-success';
        else if (stats.tps >= 15.0) tpsBadge.className = 'badge bg-warning text-dark';
        else tpsBadge.className = 'badge bg-danger animate-pulse';

        // Gráfico
        chart.data.labels.push("");
        chart.data.datasets[0].data.push(stats.tps);

        if (chart.data.labels.length > 30) {
            chart.data.labels.shift();
            chart.data.datasets[0].data.shift();
        }
        chart.update();
    }

    function setOnlineStatus(name, isOnline) {
        const overlay = document.getElementById(`overlay-${name}`);
        const statusDot = document.getElementById(`status-dot-${name}`);
        const badge = document.getElementById(`badge-tps-${name}`);
        const cardHeader = document.querySelector(`#card-${name} .card-header`);

        if (isOnline) {
            // ESCONDE O OVERLAY (Correção do bug)
            if(overlay) overlay.style.setProperty('display', 'none', 'important');
            
            statusDot.innerHTML = '<span class="text-success">● Online</span>';
            cardHeader.classList.remove('bg-light');
            cardHeader.classList.add('bg-white');
        } else {
            // MOSTRA O OVERLAY
            if(overlay) overlay.style.display = 'flex';
            
            statusDot.innerHTML = '<span class="text-muted">● Offline</span>';
            badge.className = 'badge bg-secondary';
            badge.innerText = 'OFFLINE';
            cardHeader.classList.remove('bg-white');
            cardHeader.classList.add('bg-light');
        }
    }

    function checkOfflineStatus() {
        const now = Date.now();
        myServers.forEach(server => {
            if (lastUpdate[server] > 0 && (now - lastUpdate[server]) > 5000) {
                setOnlineStatus(server, false);
            }
        });
    }
</script>

<style>
    .animate-pulse { animation: pulse 1s infinite; }
    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
    .fade-in { animation: fadeIn 0.5s ease-in; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<?php include 'includes/footer.php'; ?>