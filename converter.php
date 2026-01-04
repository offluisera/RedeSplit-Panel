<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h3><i class="fa-solid fa-money-bill-transfer"></i> Conversor de Moedas</h3>
        <p class="text-muted">Transfira suas economias entre servidores com segurança.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-lg border-0">
            <div class="card-header bg-dark text-white p-3">
                <h5 class="mb-0"><i class="fa-solid fa-right-left text-warning"></i> Realizar Transferência</h5>
            </div>
            <div class="card-body p-4">
                
                <form action="process_converter.php" method="POST" id="convertForm">
                    
                    <div class="row mb-4 p-3 bg-light rounded border">
                        <div class="col-12 mb-2">
                            <h6 class="fw-bold text-danger"><i class="fa-solid fa-circle-arrow-up"></i> ORIGEM (De onde sai)</h6>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-muted">Servidor de Origem</label>
                            <select class="form-select" name="server_from" id="server_from" required>
                                <option value="" selected disabled>Selecione...</option>
                                <option value="geral">Geral (Lobby)</option>
                                <option value="skyblock">SkyBlock</option>
                                <option value="rankup">RankUp</option>
                                <option value="survival">Survival</option>
                                <option value="fullpvp">FullPvP</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-muted">Saldo Disponível</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fa-solid fa-wallet"></i></span>
                                <input type="text" class="form-control" id="current_balance" value="0" readonly>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small text-muted">Tipo</label>
                            <select class="form-select" name="currency_type" id="currency_type">
                                <option value="coins">Coins ($)</option>
                                <option value="cash">Cash (✪)</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label small text-muted">Quantia a Enviar</label>
                            <input type="number" class="form-control" name="amount" id="amount_input" placeholder="0" min="1" required>
                        </div>
                    </div>

                    <div class="text-center mb-4" style="margin-top: -30px; margin-bottom: -10px;">
                        <span class="badge bg-primary rounded-circle p-2 border border-4 border-white shadow">
                            <i class="fa-solid fa-arrow-down fa-lg"></i>
                        </span>
                    </div>

                    <div class="row mb-4 p-3 bg-light rounded border">
                        <div class="col-12 mb-2">
                            <h6 class="fw-bold text-success"><i class="fa-solid fa-circle-arrow-down"></i> DESTINO (Para onde vai)</h6>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-muted">Servidor de Destino</label>
                            <select class="form-select" name="server_to" id="server_to" required>
                                <option value="" selected disabled>Selecione...</option>
                                <option value="geral">Geral (Lobby)</option>
                                <option value="skyblock">SkyBlock</option>
                                <option value="rankup">RankUp</option>
                                <option value="survival">Survival</option>
                                <option value="fullpvp">FullPvP</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label small text-muted">Você Receberá (Taxa 5%)</label>
                            <div class="input-group">
                                <input type="text" class="form-control fw-bold text-success" id="receive_amount" value="0" readonly>
                                <span class="input-group-text bg-success text-white" id="receive_label">Coins</span>
                            </div>
                            <small class="text-muted" style="font-size: 0.75rem;">* Taxa de câmbio aplicada automaticamente.</small>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-dark btn-lg fw-bold" id="btnSubmit">
                            <i class="fa-solid fa-check-circle"></i> CONFIRMAR TRANSAÇÃO
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
    const serverFrom = document.getElementById('server_from');
    const serverTo = document.getElementById('server_to');
    const currencyType = document.getElementById('currency_type');
    const amountInput = document.getElementById('amount_input');
    const currentBalance = document.getElementById('current_balance');
    const receiveAmount = document.getElementById('receive_amount');
    const receiveLabel = document.getElementById('receive_label');

    // 1. Função para buscar saldo via AJAX
    function updateBalance() {
        const srv = serverFrom.value;
        const type = currencyType.value;

        if(srv) {
            currentBalance.value = "Carregando...";
            fetch(`api_balance.php?server=${srv}&type=${type}`)
                .then(response => response.json())
                .then(data => {
                    if(data.status === 'success') {
                        // Formata o número
                        currentBalance.value = parseFloat(data.val).toLocaleString('pt-BR');
                        // Salva o valor puro num atributo data para validação
                        currentBalance.setAttribute('data-val', data.val);
                        calculateTotal();
                    } else {
                        currentBalance.value = "0";
                    }
                });
        }
    }

    // 2. Função para calcular taxa (5%)
    function calculateTotal() {
        const val = parseFloat(amountInput.value) || 0;
        const tax = 0.05; // 5%
        const total = val - (val * tax);
        
        receiveAmount.value = total.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
        
        // Validação simples de saldo
        const max = parseFloat(currentBalance.getAttribute('data-val')) || 0;
        if(val > max) {
            amountInput.classList.add('is-invalid');
        } else {
            amountInput.classList.remove('is-invalid');
        }
    }

    // Event Listeners
    serverFrom.addEventListener('change', updateBalance);
    currencyType.addEventListener('change', () => {
        updateBalance();
        receiveLabel.innerText = currencyType.options[currencyType.selectedIndex].text;
    });
    amountInput.addEventListener('input', calculateTotal);
    
    // Evitar selecionar o mesmo servidor
    serverTo.addEventListener('change', () => {
        if(serverTo.value === serverFrom.value) {
            alert("O servidor de destino não pode ser igual ao de origem!");
            serverTo.value = "";
        }
    });

    // Processar PHP feedback (Toast)
    <?php if(isset($_GET['status'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const status = "<?= $_GET['status'] ?>";
            const msg = "<?= isset($_GET['msg']) ? addslashes($_GET['msg']) : '' ?>";
            
            if(status === 'success') {
                showToast(msg || 'Transação concluída com sucesso!', 'success');
            } else {
                showToast(msg || 'Erro na transação.', 'error');
            }
        });
    <?php endif; ?>

    // Função Toast Customizada (Reutilizando a sua do Header ou criando nova)
    function showToast(message, type) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        let colorClass = type === 'success' ? 'border-success' : 'border-danger';
        let icon = type === 'success' ? '<i class="fa-solid fa-check-circle text-success"></i>' : '<i class="fa-solid fa-xmark-circle text-danger"></i>';
        
        toast.className = `custom-toast ${type === 'success' ? 'success' : 'warning'}`; // Usa classes do seu CSS existente
        toast.innerHTML = `<div class="d-flex align-items-center gap-2">${icon} <span>${message}</span></div>`;
        
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }
</script>

<?php include 'includes/footer.php'; ?>