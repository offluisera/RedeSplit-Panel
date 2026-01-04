<?php
include 'includes/session.php';
include 'includes/header.php';

// Segurança: Apenas Admin+
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['administrador', 'master'])) {
    echo "<script>window.location='index.php';</script>";
    exit;
}
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h2 class="text-danger fw-bold"><i class="fa-solid fa-gears"></i> Central Administrativa</h2>
            <p class="text-muted" id="page-subtitle">Selecione uma categoria para gerenciar.</p>
        </div>
    </div>

    <div id="main-menu" class="row justify-content-center g-4">
        
        <div class="col-md-6 col-lg-3">
            <div class="card shadow h-100 border-0 hover-effect cursor-pointer" onclick="showSection('section-finance')">
                <div class="card-body text-center p-5">
                    <div class="icon-box bg-success-light mb-3">
                        <i class="fa-solid fa-cart-shopping fa-3x text-success"></i>
                    </div>
                    <h4 class="text-dark fw-bold">Financeiro & Loja</h4>
                    <p class="text-muted small">Vendas, Produtos, Keys e Economia.</p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card shadow h-100 border-0 hover-effect cursor-pointer" onclick="showSection('section-management')">
                <div class="card-body text-center p-5">
                    <div class="icon-box bg-primary-light mb-3">
                        <i class="fa-solid fa-bullhorn fa-3x text-primary"></i>
                    </div>
                    <h4 class="text-dark fw-bold">Gestão & Conteúdo</h4>
                    <p class="text-muted small">Console, Avisos, Notícias e Chat.</p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card shadow h-100 border-0 hover-effect cursor-pointer" onclick="showSection('section-staff')">
                <div class="card-body text-center p-5">
                    <div class="icon-box bg-warning-light mb-3">
                        <i class="fa-solid fa-user-shield fa-3x text-warning"></i>
                    </div>
                    <h4 class="text-dark fw-bold">Equipe & Auditoria</h4>
                    <p class="text-muted small">Cargos, Logs, Monitoramento e Staff.</p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card shadow h-100 border-0 hover-effect cursor-pointer" onclick="showSection('section-tech')">
                <div class="card-body text-center p-5">
                    <div class="icon-box bg-purple-light mb-3">
                        <i class="fa-solid fa-server fa-3x text-purple"></i>
                    </div>
                    <h4 class="text-dark fw-bold">Técnico & Sistema</h4>
                    <p class="text-muted small">Performance, Redis e Reinício.</p>
                </div>
            </div>
        </div>
    </div>

    <div id="section-finance" class="sub-section d-none">
        <button class="btn btn-outline-secondary mb-3" onclick="showMain()"><i class="fa-solid fa-arrow-left"></i> Voltar</button>
        <h4 class="mb-3 text-success fw-bold border-bottom pb-2">Gerenciar Loja e Finanças</h4>
        
        <div class="row g-3">
            <!-- NOVO: Gateways de Pagamento -->
            <div class="col-md-6 col-lg-3">
                <a href="admin_gateways.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-credit-card fa-2x text-primary mb-2"></i>
                            <h6 class="fw-bold text-dark">Gateways de Pagamento</h6>
                            <small class="text-muted">MisticPay, Mercado Pago, etc</small>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <a href="financeiro_real.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-file-invoice-dollar fa-2x text-success mb-2"></i>
                            <h6 class="fw-bold text-dark">Vendas (R$)</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="admin_products.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-tags fa-2x text-primary mb-2"></i>
                            <h6 class="fw-bold text-dark">Produtos</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="keys.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-ticket fa-2x text-warning mb-2"></i>
                            <h6 class="fw-bold text-dark">Keys & Cupons</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="admin_banners.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-images fa-2x text-danger mb-2"></i>
                            <h6 class="fw-bold text-dark">Banners</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="financeiro.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-sack-dollar fa-2x text-success mb-2"></i>
                            <h6 class="fw-bold text-dark">Economia (Jogadores)</h6>
                        </div>
                    </div>
                </a>
            </div>
             <div class="col-md-6 col-lg-3">
                <a href="economy_stats.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-chart-line fa-2x text-success mb-2"></i>
                            <h6 class="fw-bold text-dark">Gráficos Economia</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="loja.php" class="text-decoration-none" target="_blank">
                    <div class="card shadow-sm h-100 border-0 hover-effect bg-light">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-store fa-2x text-muted mb-2"></i>
                            <h6 class="fw-bold text-dark">Visualizar Loja <i class="fa-solid fa-external-link-alt small"></i></h6>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <a href="admin_splitcoins.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-coins fa-2x text-warning mb-2"></i>
                            <h6 class="fw-bold text-dark">SplitCoins</h6>
                        </div>
                    </div>
                 </a>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <a href="admin_bundles.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-gift fa-2x mb-2" style="color: #63E6BE;"></i>
                            <h6 class="fw-bold text-dark">Bundles</h6>
                        </div>
                    </div>
                 </a>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <a href="admin_roulette.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-circle fa-2x mb-2" style="color: #ff0000;"></i>
                            <h6 class="fw-bold text-dark">Roleta Diária</h6>
                        </div>
                    </div>
                 </a>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <a href="admin_abandoned.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                        <i class="fa-solid fa-chart-pie fa-2x text-success mb-2"></i>
                        <h6 class="fw-bold text-dark">Carrinhos Abandonados</h6>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <div id="section-management" class="sub-section d-none">
        <button class="btn btn-outline-secondary mb-3" onclick="showMain()"><i class="fa-solid fa-arrow-left"></i> Voltar</button>
        <h4 class="mb-3 text-primary fw-bold border-bottom pb-2">Gestão de Servidor e Conteúdo</h4>
        
        <div class="row g-3">
            <div class="col-md-6 col-lg-3">
                <a href="console.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-terminal fa-2x text-danger mb-2"></i>
                            <h6 class="fw-bold text-dark">Console Remoto</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="broadcast.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-bullhorn fa-2x text-warning mb-2"></i>
                            <h6 class="fw-bold text-dark">Anúncios Globais</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="motd.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-server fa-2x text-info mb-2"></i>
                            <h6 class="fw-bold text-dark">MOTD & Ícone</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="polls.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-square-poll-vertical fa-2x text-success mb-2"></i>
                            <h6 class="fw-bold text-dark">Enquetes</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="news_manager.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-newspaper fa-2x text-secondary mb-2"></i>
                            <h6 class="fw-bold text-dark">Notícias do Site</h6>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <div id="section-staff" class="sub-section d-none">
        <button class="btn btn-outline-secondary mb-3" onclick="showMain()"><i class="fa-solid fa-arrow-left"></i> Voltar</button>
        <h4 class="mb-3 text-warning fw-bold border-bottom pb-2">Gestão de Equipe e Auditoria</h4>
        
        <div class="row g-3">
            <div class="col-md-6 col-lg-3">
                <a href="ranks.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-id-badge fa-2x text-primary mb-2"></i>
                            <h6 class="fw-bold text-dark">Gerenciar Cargos</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="staff_monitor.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-eye fa-2x text-warning mb-2"></i>
                            <h6 class="fw-bold text-dark">Monitor Staff</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="staff_stats.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-users-gear fa-2x text-info mb-2"></i>
                            <h6 class="fw-bold text-dark">Desempenho Staff</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="staff_audit.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-shield-halved fa-2x text-danger mb-2"></i>
                            <h6 class="fw-bold text-dark">Logs de Auditoria</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="permissions.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <i class="fa-solid fa-clock-rotate-left fa-2x text-dark mb-2"></i>
                            <h6 class="fw-bold text-dark">Logs de Permissões</h6>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <div id="section-tech" class="sub-section d-none">
        <button class="btn btn-outline-secondary mb-3" onclick="showMain()"><i class="fa-solid fa-arrow-left"></i> Voltar</button>
        <h4 class="mb-3 text-purple fw-bold border-bottom pb-2">Ferramentas Técnicas</h4>
        
        <div class="row g-3">
            <div class="col-md-6 col-lg-3">
                <a href="monitor.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <div class="icon-box bg-info-light mx-auto mb-2" style="width:50px; height:50px;">
                                <i class="fa-solid fa-chart-line text-info"></i>
                            </div>
                            <h6 class="fw-bold text-dark">Monitoramento (TPS)</h6>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <a href="redis_debug.php" class="text-decoration-none">
                    <div class="card shadow-sm h-100 border-0 hover-effect">
                        <div class="card-body text-center p-3">
                            <div class="icon-box bg-purple-light mx-auto mb-2" style="width:50px; height:50px;">
                                <i class="fa-solid fa-bug text-purple"></i>
                            </div>
                            <h6 class="fw-bold text-dark">Redis Debug</h6>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="card shadow-sm h-100 border-0 hover-effect cursor-pointer" data-bs-toggle="modal" data-bs-target="#restartModal">
                    <div class="card-body text-center p-3">
                        <div class="icon-box bg-danger-light mx-auto mb-2" style="width:50px; height:50px;">
                            <i class="fa-solid fa-power-off text-danger"></i>
                        </div>
                        <h6 class="fw-bold text-dark">Reiniciar Servidor</h6>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
    .hover-effect:hover { transform: translateY(-3px); transition: 0.3s; background-color: #f8f9fa; }
    .cursor-pointer { cursor: pointer; }
    
    .bg-purple-light { background-color: rgba(111, 66, 193, 0.1); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items-center; justify-content: center; margin: 0 auto; }
    .bg-success-light { background-color: rgba(25, 135, 84, 0.1); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items-center; justify-content: center; margin: 0 auto; }
    .bg-primary-light { background-color: rgba(13, 110, 253, 0.1); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items-center; justify-content: center; margin: 0 auto; }
    .bg-warning-light { background-color: rgba(255, 193, 7, 0.1); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items-center; justify-content: center; margin: 0 auto; }
    .bg-danger-light { background-color: rgba(220, 53, 69, 0.1); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items-center; justify-content: center; margin: 0 auto; }
    .bg-info-light { background-color: rgba(13, 202, 240, 0.1); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items-center; justify-content: center; margin: 0 auto; }

    .text-purple { color: #6f42c1 !important; }
</style>

<script>
    function showSection(sectionId) {
        document.getElementById('main-menu').classList.add('d-none');
        document.getElementById('page-subtitle').classList.add('d-none');
        
        document.querySelectorAll('.sub-section').forEach(el => el.classList.add('d-none'));
        
        const section = document.getElementById(sectionId);
        section.classList.remove('d-none');
        section.classList.add('fade-in');
    }

    function showMain() {
        document.querySelectorAll('.sub-section').forEach(el => el.classList.add('d-none'));
        
        document.getElementById('main-menu').classList.remove('d-none');
        document.getElementById('page-subtitle').classList.remove('d-none');
    }
</script>

<div class="modal fade" id="restartModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-skull me-2"></i> Agendar Reinício</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4">
                    <i class="fa-solid fa-lock fa-2x me-3 text-warning"></i>
                    <small>Ao confirmar, o servidor selecionado bloqueará novas entradas e iniciará uma contagem regressiva de <b>5 minutos</b>.</small>
                </div>
                <form id="restartForm">
                    <label class="form-label fw-bold text-secondary">Qual servidor reiniciar?</label>
                    <select class="form-select form-select-lg mb-3" id="serverSelect" required>
                        <option value="" selected disabled>Selecione na lista...</option>
                        <option value="ALL">⚠ REDE INTEIRA (TODOS)</option>
                        <option value="lobby">Hub / Lobby</option>
                        <option value="survival">Survival</option>
                        <option value="skyblock">Skyblock</option>
                        <option value="rankup">Rankup</option>
                        <option value="fullpvp">FullPvP</option>
                        <option value="bedwars">Bedwars</option>
                    </select>
                </form>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger fw-bold px-4" onclick="sendRestart()">
                    <i class="fa-solid fa-check"></i> CONFIRMAR REINÍCIO
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function sendRestart() {
    const select = document.getElementById('serverSelect');
    const server = select.value;
    const serverName = select.options[select.selectedIndex].text;

    if (!server) { alert("Por favor, selecione um servidor na lista."); return; }
    if (!confirm(`ATENÇÃO!\n\nVocê tem certeza absoluta que deseja iniciar o processo de reinício para:\n\n👉 ${serverName}?\n\nIsso vai kikar os jogadores em 5 minutos.`)) { return; }

    const formData = new FormData();
    formData.append('server', server);

    fetch('api_restart.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert("✅ SUCESSO!\n\n" + data.msg + "\n\nVerifique o chat do jogo ou o Redis Debug.");
            var modalEl = document.getElementById('restartModal');
            var modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();
        } else {
            alert("❌ ERRO: " + (data.error || "Erro desconhecido"));
        }
    })
    .catch(e => {
        alert("Erro de conexão com o servidor.");
        console.error(e);
    });
}
</script>

<?php include 'includes/footer.php'; ?>