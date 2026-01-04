<?php
include 'includes/db_nlogin.php';

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // Busca o hash no banco usando last_name
    $stmt = $conn_nlogin->prepare("SELECT password FROM nlogin WHERE last_name = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $hashBanco = $row['password'];
        $msg .= "<div class='alert alert-info'><b>Hash encontrado no Banco:</b> <br><small>$hashBanco</small></div>";

        // --- TESTE 1: SHA256 Padrão AuthMe ($SHA$salt$hash) ---
        // Lógica: SHA256(SHA256(pass) + salt)
        $match1 = "Não";
        if (strpos($hashBanco, '$SHA$') === 0) {
            $parts = explode('$', $hashBanco);
            if (count($parts) === 4) {
                $salt = $parts[2];
                $realHash = $parts[3];
                $calc = hash('sha256', hash('sha256', $pass) . $salt);
                if ($calc === $realHash) $match1 = "<b style='color:green'>SIM! (Este é o correto)</b>";
                else $match1 = "Não (Calculado: $calc)";
            }
        }
        $msg .= "Teste 1 (AuthMe SHA256): $match1 <br>";

        // --- TESTE 2: SHA256 Puro ---
        $calc2 = hash('sha256', $pass);
        $match2 = ($calc2 === $hashBanco) ? "<b style='color:green'>SIM!</b>" : "Não";
        $msg .= "Teste 2 (SHA256 Puro): $match2 <br>";

        // --- TESTE 3: MD5 (Antigo) ---
        $calc3 = md5($pass);
        $match3 = ($calc3 === $hashBanco) ? "<b style='color:green'>SIM!</b>" : "Não";
        $msg .= "Teste 3 (MD5): $match3 <br>";

        // --- TESTE 4: BCRYPT ($2a$ ou $2y$) ---
        $match4 = password_verify($pass, $hashBanco) ? "<b style='color:green'>SIM!</b>" : "Não";
        $msg .= "Teste 4 (BCrypt): $match4 <br>";

    } else {
        $msg = "<div class='alert alert-warning'>Usuário não encontrado na tabela 'nlogin' (coluna last_name).</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="p-5 bg-light">
    <div class="card p-4 mx-auto" style="max-width: 600px">
        <h4>Diagnóstico de Senha</h4>
        <form method="POST">
            <input type="text" name="username" class="form-control mb-2" placeholder="Nick" required>
            <input type="text" name="password" class="form-control mb-2" placeholder="Senha Correta" required>
            <button class="btn btn-primary w-100">Testar Hash</button>
        </form>
        <hr>
        <?= $msg ?>
    </div>
</body>
</html>