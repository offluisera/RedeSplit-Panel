<?php
// CONFIGURAÇÃO
// Crie um Webhook no seu Discord (Editar Canal > Integrações > Webhooks) e cole aqui:
define('DISCORD_WEBHOOK', 'https://discord.com/api/webhooks/1455458097723346955/nDfep7OM3W8Fi7GceSgQ4ynNpU4SfzX5h-YRMftLQC0mEqJJPJ6iNx5dcuoqplETYyIW');

function sendDiscordLog($title, $description, $colorHex, $fields = []) {
    if (!defined('DISCORD_WEBHOOK') || empty(DISCORD_WEBHOOK)) return;

    $json_data = json_encode([
        "username" => "RedeSplit - PAINEL ",
        "avatar_url" => "https://i.imgur.com/D6qFXNu.png", // Coloque o logo do seu servidor
        "embeds" => [
            [
                "title" => $title,
                "type" => "rich",
                "description" => $description,
                "timestamp" => date("c", strtotime("now")),
                "color" => hexdec($colorHex),
                "footer" => [
                    "text" => "Painel RedeSplit",
                ],
                "fields" => $fields
            ]
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init(DISCORD_WEBHOOK);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($ch);
    curl_close($ch);
}
?>