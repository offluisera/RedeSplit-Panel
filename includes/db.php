<?php
$host = 'localhost';
$user = 'splitcore'; 
$pass = 'RLbKhFS7wswR8EwM';
$name = 'core';

// Conexão com o banco
$conn = new mysqli($host, $user, $pass, $name);

if ($conn->connect_error) {
    die("Erro DB: " . $conn->connect_error);
}

date_default_timezone_set('America/Sao_Paulo');
$conn->query("SET time_zone = '-03:00'");

// --- FUNÇÕES DO SISTEMA (COM PROTEÇÃO INDIVIDUAL) ---

// 1. Função sendWebCommand
if (!function_exists('sendWebCommand')) {
    function sendWebCommand($conn, $player, $action, $value) {
        $operator = isset($_SESSION['admin_user']) ? $_SESSION['admin_user'] : 'Sistema';
        $stmt = $conn->prepare("INSERT INTO rs_web_commands (player_name, action, value, operator) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $player, $action, $value, $operator);
        $stmt->execute();
        $stmt->close();
    }
}

// 2. Função logStaffAction
if (!function_exists('logStaffAction')) {
    function logStaffAction($conn, $operator, $action, $details) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $conn->prepare("INSERT INTO rs_staff_actions_logs (operator, action_type, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $operator, $action, $details, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// 3. Função getPlayerToxicity
if (!function_exists('getPlayerToxicity')) {
    function getPlayerToxicity($conn, $player_name) {
        $sql = "SELECT COUNT(*) as total FROM rs_filter_alerts 
                WHERE player_name = '$player_name' 
                AND date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $res = $conn->query($sql);
        $data = $res->fetch_assoc();
        $count = $data['total'];

        if ($count <= 2) return ['level' => 'Baixo', 'class' => 'bg-success', 'percent' => 20];
        if ($count <= 5) return ['level' => 'Médio', 'class' => 'bg-warning text-dark', 'percent' => 50];
        if ($count <= 10) return ['level' => 'Alto', 'class' => 'bg-orange', 'percent' => 80];
        return ['level' => 'Crítico', 'class' => 'bg-danger', 'percent' => 100];
    }
}

// 4. Função getAccountAge
if (!function_exists('getAccountAge')) {
    function getAccountAge($conn, $player_name) {
        $player_name = $conn->real_escape_string($player_name);
        $sql = "SELECT first_login FROM rs_players WHERE LOWER(`name`) = LOWER('$player_name') LIMIT 1";
        $res = $conn->query($sql);
        
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (!$row['first_login']) return "Sem data";
            
            try {
                $first = new DateTime($row['first_login']);
                $diff = $first->diff(new DateTime());
                
                if ($diff->y > 0) return $diff->y . " ano(s)";
                if ($diff->m > 0) return $diff->m . " mês(es)";
                if ($diff->d > 0) return $diff->d . " dia(s)";
                if ($diff->h > 0) return $diff->h . " hora(s)";
                return $diff->i . " min";
            } catch (Exception $e) {
                return "Data Inválida";
            }
        }
        return "N/A"; 
    }
}
?>