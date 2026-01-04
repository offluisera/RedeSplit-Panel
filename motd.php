<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';

// Apenas Staff Alta
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['administrador', 'master'])) exit;

$msg = "";

// ATUALIZAR MOTD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $l1 = $conn->real_escape_string($_POST['line1']);
    $l2 = $conn->real_escape_string($_POST['line2']);
    
    // 1. Salva no Banco (Persistência)
    $conn->query("UPDATE rs_motd SET line1='$l1', line2='$l2' WHERE id=1");
    
    // 2. Envia pro Redis (Instantâneo)
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');
        // Usamos <br> para separar as linhas no pacote
        $redis->publish('redesplit:channel', "UPDATE_MOTD;Admin;$l1<br>$l2");
        $msg = "<div class='alert alert-success'>✅ MOTD Atualizado com sucesso!</div>";
    } catch (Exception $e) {
        $msg = "<div class='alert alert-warning'>Salvo no banco, mas Redis falhou. Reinicie o server para aplicar.</div>";
    }
}

// Carrega atual
$current = $conn->query("SELECT * FROM rs_motd WHERE id=1")->fetch_assoc();
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <?= $msg ?>
        <div class="card shadow">
            <div class="card-header bg-dark text-white"><i class="fa-solid fa-server"></i> Configurar MOTD</div>
            <div class="card-body">
                
                <div class="bg-dark p-3 mb-3 rounded border border-secondary">
                    <div class="d-flex align-items-center">
                        <img src="https://api.mcsrvstat.us/icon/redesplit.com.br" width="64" height="64" class="me-3 rounded">
                        <div>
                            <div id="preview1" class="fw-bold text-white" style="font-family: 'Minecraft', monospace; font-size: 1.2rem;"></div>
                            <div id="preview2" class="text-white" style="font-family: 'Minecraft', monospace; font-size: 1.2rem;"></div>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <div class="mb-3">
                        <label class="fw-bold">Linha 1</label>
                        <input type="text" id="in1" name="line1" class="form-control font-monospace" value="<?= htmlspecialchars($current['line1']) ?>" oninput="updatePreview()">
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Linha 2</label>
                        <input type="text" id="in2" name="line2" class="form-control font-monospace" value="<?= htmlspecialchars($current['line2']) ?>" oninput="updatePreview()">
                    </div>
                    
                    <button class="btn btn-primary w-100 fw-bold">SALVAR ALTERAÇÕES</button>
                </form>
                
                <div class="mt-3 text-muted small">
                    Dica: Use <b>&</b> para cores (Ex: &eAmarelo, &bAzul, &lNegrito).
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Função simples para simular as cores do Minecraft no Preview
function mcColors(text) {
    // Mapeamento básico de cores para HTML
    const codes = {
        '0': '#000000', '1': '#0000AA', '2': '#00AA00', '3': '#00AAAA',
        '4': '#AA0000', '5': '#AA00AA', '6': '#FFAA00', '7': '#AAAAAA',
        '8': '#555555', '9': '#5555FF', 'a': '#55FF55', 'b': '#55FFFF',
        'c': '#FF5555', 'd': '#FF55FF', 'e': '#FFFF55', 'f': '#FFFFFF'
    };
    
    let html = "";
    let currentColor = "#FFFFFF";
    let isBold = false;
    
    let parts = text.split('&');
    
    parts.forEach((part, index) => {
        if (index === 0 && part === "") return; // Primeira parte vazia se string começar com &
        
        // Se não for o primeiro item, o caractere anterior ao split era um &
        // Então o primeiro char dessa 'part' é o código de cor
        let code = (index === 0 && text.charAt(0) !== '&') ? null : part.charAt(0);
        let content = (index === 0 && text.charAt(0) !== '&') ? part : part.substring(1);
        
        if (codes[code]) {
            currentColor = codes[code];
            isBold = false; // Reset bold on color change
        } else if (code === 'l') {
            isBold = true;
        } else if (code === 'r') {
            currentColor = "#FFFFFF";
            isBold = false;
        }

        let style = `color: ${currentColor}; ${isBold ? 'font-weight: bold;' : ''}`;
        html += `<span style="${style}">${content}</span>`;
    });
    
    if(!text.includes('&')) return text; // Se não tiver cor, retorna puro
    return html;
}

function updatePreview() {
    let l1 = document.getElementById('in1').value;
    let l2 = document.getElementById('in2').value;
    
    document.getElementById('preview1').innerHTML = mcColors(l1);
    document.getElementById('preview2').innerHTML = mcColors(l2);
}

// Roda ao carregar
updatePreview();
</script>

<?php include 'includes/footer.php'; ?>