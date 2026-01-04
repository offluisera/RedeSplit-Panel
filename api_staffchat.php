<?php
include 'includes/session.php';
include 'includes/db.php';

// Segurança básica
$rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : 'membro';
if (!in_array($rank, ['ajudante', 'moderador', 'administrador', 'master'])) exit;

// Pega as últimas 50 mensagens
$sql = "SELECT * FROM (SELECT * FROM rs_staff_chat ORDER BY id DESC LIMIT 50) sub ORDER BY id ASC";
$res = $conn->query($sql);

if ($res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $sourceIcon = ($row['source'] == 'WEB') ? '<i class="fa-solid fa-globe"></i>' : '<i class="fa-solid fa-gamepad"></i>';
        $colorClass = ($row['source'] == 'WEB') ? 'msg-web' : 'msg-game';
        
        echo '<div class="msg-item">';
        echo '<span class="'.$colorClass.' fw-bold">' . $sourceIcon . ' ' . htmlspecialchars($row['username']) . ':</span> ';
        echo '<span class="text-dark">' . htmlspecialchars($row['message']) . '</span>';
        echo '<span class="msg-time">' . date('H:i', strtotime($row['created_at'])) . '</span>';
        echo '</div>';
    }
} else {
    echo '<p class="text-center text-muted">Nenhuma conversa recente.</p>';
}
?>