<?php
$nl_host = 'localhost';
$nl_db   = 'redesplit_nlogin';
$nl_user = 'redesplit_user'; 
$nl_pass = 'ypHSDKjWkW3dALeL';

$conn_nlogin = new mysqli($nl_host, $nl_user, $nl_pass, $nl_db);

if ($conn_nlogin->connect_error) {
    die("Erro ao conectar no nLogin: " . $conn_nlogin->connect_error);
}
?>