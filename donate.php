<?php
include 'includes/session.php';
include 'includes/db.php';

// 1. Atualiza o saldo do usuário atual para exibir corretamente
$my_user = $_SESSION['admin_user'];
$stmt = $conn->prepare("SELECT cash FROM rs_players WHERE name = ?");
$stmt->bind_param("s", $my_user);
$stmt->execute();
$res = $stmt->get_result();
$user_data = $res->fetch_assoc();
$user_cash = $user_data ? $user_data['cash'] : 0;

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-hand-holding-dollar text-warning"></i> Central de Transferências</h3>
        <p class="text-muted">Envie Cash para amigos ou pague por negociações de forma segura.</p>
    </div>
</div>

<div class="row">
    
    <div class="col-lg-5 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center py-3">
                <span class="fs-5"><i class="fa-solid fa-paper-plane me-2"></i> Realizar Envio</span>
            </div>
            <div class="card-body p-4">
                
                <div class="alert alert-light border d-flex justify-content-between align-items-center mb-4">
                    <span class="text-muted fw-bold">SEU SALDO DISPONÍVEL</span>
                    <span class="badge bg-warning text-dark fs-5"><i class="fa-solid fa-star"></i> <?= number_format($user_cash, 0, ',', '.') ?></span>
                </div>

                <form id="formTransferCash">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">NICK DO DESTINATÁRIO</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user text-muted"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" id="cashTarget" placeholder="Digite o nick exato..." required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-secondary">QUANTIDADE A ENVIAR</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-coins text-warning"></i></span>
                            <input type="number" class="form-control border-start-0 ps-0" id="cashAmount" placeholder="Ex: 500" min="1" required>
                        </div>
                        <div class="form-text text-end">O valor será descontado instantaneamente.</div>
                    </div>

                    <div class="d-grid">
                        <button type="button" class="btn btn-dark btn-lg fw-bold" onclick="confirmTransfer()">
                            CONTINUAR ENVIO <i class="fa-solid fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </form>
                
                <div id="transferResult" class="mt-3"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-7 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold py-3 border-bottom">
                <i class="fa-solid fa-list-ul text-secondary me-2"></i> Últimas Movimentações
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-secondary small">
                            <tr>
                                <th class="ps-4">Tipo</th>
                                <th>Jogador</th>
                                <th>Valor</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Busca os logs onde eu sou REMETENTE (sender) ou DESTINATÁRIO (receiver)
                            $log_sql = "SELECT * FROM rs_cash_logs 
                                        WHERE sender = '$my_user' OR receiver = '$my_user' 
                                        ORDER BY id DESC LIMIT 8";
                            $logs = $conn->query($log_sql);

                            if ($logs && $logs->num_rows > 0):
                                while ($log = $logs->fetch_assoc()):
                                    $is_sent = (strtolower($log['sender']) == strtolower($my_user));
                                    $other_person = $is_sent ? $log['receiver'] : $log['sender'];
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <?php if ($is_sent): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger"><i class="fa-solid fa-arrow-up"></i> Enviou</span>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-10 text-success"><i class="fa-solid fa-arrow-down"></i> Recebeu</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold">
                                    <img src="https://minotar.net/avatar/<?= $other_person ?>/20.png" class="rounded-circle me-1">
                                    <?= $other_person ?>
                                </td>
                                <td class="<?= $is_sent ? 'text-danger' : 'text-success' ?> fw-bold">
                                    <?= $is_sent ? '-' : '+' ?> <?= number_format($log['amount'], 0, ',', '.') ?> Cash
                                </td>
                                <td class="text-muted small">
                                    <?= date('d/m/Y H:i', strtotime($log['date'])) ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-receipt fa-2x mb-3 opacity-50"></i><br>
                                    Nenhuma transação recente encontrada.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-center small text-muted">
                Mostrando as últimas 8 transações
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalConfirmCash" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning border-0">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-shield-halved me-2"></i> Confirmar Transferência</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div class="avatar-transfer mb-3 d-flex justify-content-center align-items-center gap-3">
                    <div class="text-center">
                        <img src="https://minotar.net/avatar/<?= $my_user ?>/64.png" class="rounded-circle border border-3 border-white shadow-sm">
                        <div class="small fw-bold mt-1 text-muted">VOCÊ</div>
                    </div>
                    <i class="fa-solid fa-angles-right text-muted fa-2x"></i>
                    <div class="text-center">
                        <img id="confAvatar" src="" class="rounded-circle border border-3 border-warning shadow-sm" width="64">
                        <div class="small fw-bold mt-1 text-dark" id="confTarget">ALVO</div>
                    </div>
                </div>
                
                <h5 class="text-muted mb-0">Você enviará:</h5>
                <h1 class="fw-bold text-dark display-5 mb-3"><i class="fa-solid fa-star text-warning"></i> <span id="confAmount">0</span></h1>
                
                <div class="alert alert-warning small d-flex align-items-center text-start">
                    <i class="fa-solid fa-triangle-exclamation fa-2x me-3"></i>
                    <div>
                        <b>Atenção:</b> Esta ação é irreversível. Verifique se o nick está correto antes de confirmar.
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-center border-0 pb-4">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-dark px-5 fw-bold" onclick="sendCash()">
                    <i class="fa-solid fa-check"></i> CONFIRMAR E ENVIAR
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function confirmTransfer() {
    const target = document.getElementById('cashTarget').value;
    const amount = document.getElementById('cashAmount').value;

    if(!target || amount <= 0) {
        alert("Preencha o nick e um valor válido maior que zero.");
        return;
    }

    // Preenche o modal
    document.getElementById('confTarget').innerText = target;
    document.getElementById('confAmount').innerText = parseInt(amount).toLocaleString('pt-BR');
    document.getElementById('confAvatar').src = `https://minotar.net/avatar/${target}/64.png`;
    
    // Abre o modal
    new bootstrap.Modal(document.getElementById('modalConfirmCash')).show();
}

function sendCash() {
    const target = document.getElementById('cashTarget').value;
    const amount = document.getElementById('cashAmount').value;
    const resultDiv = document.getElementById('transferResult');
    
    // Fecha modal e mostra loading
    bootstrap.Modal.getInstance(document.getElementById('modalConfirmCash')).hide();
    resultDiv.innerHTML = '<div class="alert alert-info py-2 shadow-sm"><i class="fa-solid fa-spinner fa-spin"></i> Processando transação segura...</div>';

    const formData = new FormData();
    formData.append('target_player', target);
    formData.append('amount', amount);

    fetch('api_transfer_cash.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success py-3 shadow-sm border-0 bg-success text-white">
                    <h5 class="alert-heading fw-bold"><i class="fa-solid fa-circle-check"></i> Transferência Realizada!</h5>
                    <p class="mb-0">Você enviou <b>${amount} Cash</b> para <b>${target}</b>.</p>
                </div>`;
            document.getElementById('formTransferCash').reset();
            
            // Recarrega a página após 2 segundos para atualizar saldo e histórico
            setTimeout(() => location.reload(), 2000); 
        } else {
            resultDiv.innerHTML = `<div class="alert alert-danger py-2 shadow-sm border-0"><i class="fa-solid fa-circle-xmark"></i> ${data.error}</div>`;
        }
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="alert alert-danger py-2">Erro de conexão com o servidor. Tente novamente.</div>';
    });
}
</script>

<?php include 'includes/footer.php'; ?>