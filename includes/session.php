<?php
session_start();
// Usa include_once para evitar o erro de repetição
include_once 'db.php'; 

// 1. Verifica se está logado
if (!isset($_SESSION['admin_user'])) {
    header('Location: login.php');
    exit();
}

$user_check = $_SESSION['admin_user'];

// 2. VERIFICAÇÃO DE BANIMENTO (CORRIGIDA)
// Colunas atualizadas conforme seu print: player_name, expires, active
$ban_query = "SELECT id FROM rs_punishments 
              WHERE player_name = '$user_check' 
              AND type = 'BAN' 
              AND active = 1 
              AND (expires > NOW() OR expires IS NULL OR expires = '0000-00-00 00:00:00')";

$check_ban = $conn->query($ban_query);

if ($check_ban && $check_ban->num_rows > 0) {
    // O usuário ESTÁ banido!
    
    $current_page = basename($_SERVER['PHP_SELF']);
    // Páginas permitidas para banidos (Apelação e Sair)
    $allowed_pages = ['appeal.php', 'logout.php']; 

    // Se ele tentar acessar qualquer outra coisa...
    if (!in_array($current_page, $allowed_pages)) {
        header("Location: appeal.php");
        exit();
    }
}
?>