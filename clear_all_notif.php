<?php
include 'includes/session.php';
include 'includes/db.php';

$my_user = $_SESSION['admin_user'];

// Marca todas as notificações do usuário logado como lidas
$sql = "UPDATE rs_notifications SET is_read = 1 WHERE player_name = '$my_user' AND is_read = 0";

if ($conn->query($sql)) {
    // Redireciona para a página anterior ou para a index caso não exista
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header("Location: $referer");
    exit;
}
?>