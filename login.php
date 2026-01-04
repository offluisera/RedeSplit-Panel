<?php
session_start();
// Se já estiver logado, redireciona para a dashboard
if (isset($_SESSION['admin_user'])) {
    header("Location: index.php");
    exit;
}

include 'includes/db.php';       // Conexão com o banco do RedeSplitCore
include 'includes/db_nlogin.php'; // Conexão com o banco do nLogin

$error = "";

// --- FUNÇÃO DE VERIFICAÇÃO INTELIGENTE ---
function checkNLoginPassword($senhaDigitada, $hashBanco) {
    // CASO 1: Formato Específico nLogin/AuthMe ($SHA256$hash$salt)
    // Ex: $SHA256$cfb59...$nsGd...
    if (strpos($hashBanco, '$SHA256$') === 0) {
        $parts = explode('$', $hashBanco);
        if (count($parts) === 4) {
            // No seu caso: 
            // parts[2] é o HASH (hexadecimal longo)
            // parts[3] é o SALT (string curta)
            $realHash = $parts[2];
            $salt = $parts[3];

            // Tenta o algoritmo padrão: SHA256(SHA256(senha) + salt)
            $calculado = hash('sha256', hash('sha256', $senhaDigitada) . $salt);
            if ($calculado === $realHash) return true;
        }
    }

    // CASO 2: Formato Padrão AuthMe ($SHA$salt$hash) - Fallback
    if (strpos($hashBanco, '$SHA$') === 0) {
        $parts = explode('$', $hashBanco);
        if (count($parts) === 4) {
            $salt = $parts[2];
            $realHash = $parts[3];
            $calculado = hash('sha256', hash('sha256', $senhaDigitada) . $salt);
            if ($calculado === $realHash) return true;
        }
    }
    
    // CASO 3: SHA256 Puro
    if (strlen($hashBanco) === 64 && ctype_xdigit($hashBanco)) {
        return hash('sha256', $senhaDigitada) === $hashBanco;
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $conn->real_escape_string($_POST['username']);
    $pass = $_POST['password'];

    // 1. BUSCAR JOGADOR (Sem filtrar cargo, aceita todos)
    $stmt = $conn->prepare("SELECT rank_id FROM rs_players WHERE name = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $rank_id = strtolower($row['rank_id']); // Salva o cargo (ex: membro, ajudante)

        // 2. VERIFICAR SENHA (nLogin)
        $sql_nlogin = "SELECT password FROM nlogin WHERE last_name = ?";
        if ($stmt_nl = $conn_nlogin->prepare($sql_nlogin)) {
            $stmt_nl->bind_param("s", $user);
            $stmt_nl->execute();
            $res_nl = $stmt_nl->get_result();

            if ($res_nl->num_rows > 0) {
                $row_nl = $res_nl->fetch_assoc();
                if (checkNLoginPassword($pass, $row_nl['password'])) {
                    
                    // SUCESSO! Salva sessão e cargo
                    $_SESSION['admin_user'] = $user;
                    $_SESSION['user_rank'] = $rank_id; // <--- NOVO: SALVA O CARGO
                    $_SESSION['last_activity'] = time();
                    
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Senha incorreta!";
                }
            } else {
                $error = "Usuário não registrado no nLogin.";
            }
        }
    } else {
        $error = "Usuário não encontrado no banco de dados do servidor.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | RedeSplit Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #2c3e50; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { width: 100%; max-width: 400px; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 10px 20px rgba(0,0,0,0.3); }
        .btn-custom { background: #e74c3c; color: white; border: none; font-weight: bold; }
        .btn-custom:hover { background: #c0392b; color: white; }
    </style>
</head>
<body>

<div class="login-card">
    <h3 class="text-center fw-bold mb-4">Painel <span style="color:#e74c3c">Staff</span></h3>
    
    <?php if($error): ?>
        <div class="alert alert-danger text-center small"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Usuário</label>
            <input type="text" name="username" class="form-control" placeholder="Nick do Minecraft" required>
        </div>
        <div class="mb-4">
            <label class="form-label">Senha</label>
            <input type="password" name="password" class="form-control" placeholder="Senha do /login" required>
        </div>
        <button type="submit" class="btn btn-custom w-100 py-2">ACESSAR</button>
    </form>
</div>

</body>
</html>