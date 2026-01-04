<?php
include 'includes/session.php';
include 'includes/header.php';

// Verifica permissão (apenas Staff)
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['ajudante', 'moderador', 'administrador', 'master'])) {
    echo "<script>window.location='index.php';</script>";
    exit;
}

$msg_feedback = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    $type = $_POST['type']; // AVISO, EVENTO, MANUTENCAO
    $message = $_POST['message'];
    $author = $_SESSION['admin_user'];

    if (!empty($message)) {
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');

            // Formato: BROADCAST;NomeStaff;TIPO|Mensagem
            // Exemplo: BROADCAST;Admin;AVISO|O servidor vai reiniciar!
            $payload = "BROADCAST;$author;$type|$message";
            
            $redis->publish('redesplit:channel', $payload);
            
            $msg_feedback = "<div class='alert alert-success'>📢 Anúncio enviado para o servidor!</div>";
        } catch (Exception $e) {
            $msg_feedback = "<div class='alert alert-danger'>Erro no Redis: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <?= $msg_feedback ?>
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fa-solid fa-bullhorn"></i> Anúncio Global</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="send_broadcast" value="true">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tipo de Anúncio</label>
                        <select name="type" class="form-select">
                            <option value="AVISO">⚠️ Aviso Importante (Amarelo)</option>
                            <option value="EVENTO">🎉 Evento (Azul)</option>
                            <option value="MANUTENCAO">🔧 Manutenção (Vermelho)</option>
                            <option value="INFO">ℹ️ Informação (Verde)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Mensagem</label>
                        <textarea name="message" class="form-control" rows="3" placeholder="Digite a mensagem que aparecerá para todos..." required></textarea>
                        <small class="text-muted">Você pode usar códigos de cor (&e, &c, &l) se quiser.</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        ENVIAR ANÚNCIO
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>