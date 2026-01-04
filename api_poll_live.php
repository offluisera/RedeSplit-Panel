<?php
// api_poll_live.php
header('Content-Type: application/json');

try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->auth('UHAFDjbnakfye@@jouiayhfiqwer903');
    
    // Pega o JSON salvo pelo Java
    $data = $redis->get("poll:live");
    
    if ($data) {
        echo $data; // Retorna ex: {"1": 10, "2": 5}
    } else {
        echo json_encode(["1" => 0, "2" => 0]);
    }
} catch (Exception $e) {
    echo json_encode(["1" => 0, "2" => 0]);
}
?>