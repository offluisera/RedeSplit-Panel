<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// Busca os últimos 7 dias
$sql = "SELECT * FROM rs_economy_history ORDER BY date ASC LIMIT 7";
$result = $conn->query($sql);

$labels = [];
$coinsData = [];
$cashData = [];

while($row = $result->fetch_assoc()) {
    $labels[] = date('d/m', strtotime($row['date']));
    $coinsData[] = $row['total_coins'];
    $cashData[] = $row['total_cash'];
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h3><i class="fa-solid fa-chart-line text-success"></i> Monitor de Inflação</h3>
        <p class="text-muted">Acompanhe a massa monetária total do servidor.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm p-4">
            <canvas id="economyChart" style="width: 100%; height: 400px;"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('economyChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Total Coins',
            data: <?= json_encode($coinsData) ?>,
            borderColor: '#ffae00',
            backgroundColor: 'rgba(255, 174, 0, 0.1)',
            fill: true,
            tension: 0.3
        }, {
            label: 'Total Cash',
            data: <?= json_encode($cashData) ?>,
            borderColor: '#00d2d3',
            backgroundColor: 'rgba(0, 210, 211, 0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top', labels: { color: '#fff' } }
        },
        scales: {
            y: { ticks: { color: '#fff' }, grid: { color: 'rgba(255,255,255,0.1)' } },
            x: { ticks: { color: '#fff' }, grid: { display: false } }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>