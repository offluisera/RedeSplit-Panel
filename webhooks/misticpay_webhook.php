<?php
/**
 * MisticPay Webhook Handler
 * Processa notificações de pagamento da MisticPay
 */

// Configurações básicas
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Carrega conexão com banco
require_once '../includes/db.php';

// Função para log
function logWebhook($message, $data = null) {
    $logFile = __DIR__ . '/logs/misticpay_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data) {
        $logMessage .= "\nData: " . json_encode($data, JSON_PRETTY_PRINT);
    }
    
    file_put_contents($logFile, $logMessage . "\n\n", FILE_APPEND);
}

// Função para processar entrega do produto
function processarEntrega($conn, $productId, $playerNick) {
    // Verifica qual tabela usar
    $tableCheck = $conn->query("SHOW TABLES LIKE 'rs_delivery_queue'");
    $useDeliveryQueue = ($tableCheck && $tableCheck->num_rows > 0);
    $queueTable = $useDeliveryQueue ? 'rs_delivery_queue' : 'rs_command_queue';
    
    // Verifica se é um bundle
    $bundleCheck = $conn->query("SELECT * FROM rs_bundle_items WHERE product_id = $productId");
    
    if ($bundleCheck && $bundleCheck->num_rows > 0) {
        // É um bundle - adiciona todos os comandos
        while ($item = $bundleCheck->fetch_assoc()) {
            $cmd = str_replace(['{player}', '%player%'], [$playerNick, $playerNick], $item['command']);
            
            if ($useDeliveryQueue) {
                $conn->query("INSERT INTO rs_delivery_queue (player_name, command, status) 
                             VALUES ('$playerNick', '$cmd', 'PENDING')");
            } else {
                $conn->query("INSERT INTO rs_command_queue (command, status) 
                             VALUES ('$cmd', 'WAITING')");
            }
        }
    } else {
        // Produto simples
        $prod = $conn->query("SELECT command FROM rs_products WHERE id = $productId")->fetch_assoc();
        
        if ($prod && !empty($prod['command'])) {
            $cmd = str_replace(['{player}', '%player%'], [$playerNick, $playerNick], $prod['command']);
            
            if ($useDeliveryQueue) {
                $conn->query("INSERT INTO rs_delivery_queue (player_name, command, status) 
                             VALUES ('$playerNick', '$cmd', 'PENDING')");
            } else {
                $conn->query("INSERT INTO rs_command_queue (command, status) 
                             VALUES ('$cmd', 'WAITING')");
            }
        }
    }
}

// Função para calcular e adicionar cashback
function addCashback($conn, $playerName, $amount) {
    $cashbackAmount = floor($amount); // R$ 1,00 = 1 SplitCoin
    
    if ($cashbackAmount <= 0) return false;
    
    $playerName = $conn->real_escape_string($playerName);
    
    // Busca saldo atual
    $playerQuery = $conn->query("SELECT splitcoins FROM rs_players WHERE name = '$playerName' LIMIT 1");
    
    if ($playerQuery && $playerQuery->num_rows > 0) {
        $player = $playerQuery->fetch_assoc();
        $oldBalance = (int)$player['splitcoins'];
        $newBalance = $oldBalance + $cashbackAmount;
        
        // Atualiza saldo
        $conn->query("UPDATE rs_players SET splitcoins = $newBalance WHERE name = '$playerName'");
        
        // Registra no log
        $reason = $conn->real_escape_string("Cashback: Compra R$ " . number_format($amount, 2));
        $conn->query("INSERT INTO rs_splitcoins_log (player, amount, old_balance, new_balance, type, reason) 
                      VALUES ('$playerName', $cashbackAmount, $oldBalance, $newBalance, 'EARN', '$reason')");
        
        return true;
    }
    
    return false;
}

// Função para processar afiliados
function processarAfiliado($conn, $buyerNick, $salePrice, $saleId) {
    if (isset($_COOKIE['rs_ref']) && !empty($_COOKIE['rs_ref'])) {
        $referrer = $conn->real_escape_string($_COOKIE['rs_ref']);
        
        if (strtolower($referrer) !== strtolower($buyerNick) && $salePrice > 0) {
            $checkRef = $conn->query("SELECT id FROM rs_players WHERE name = '$referrer'");
            
            if ($checkRef && $checkRef->num_rows > 0) {
                $commission = $salePrice * 0.10; // 10% de comissão
                $conn->query("UPDATE rs_players SET splitcoins = splitcoins + $commission WHERE name = '$referrer'");
                
                // Log de afiliados
                $logCheck = $conn->query("SHOW TABLES LIKE 'rs_affiliates_log'");
                if ($logCheck && $logCheck->num_rows > 0) {
                    $conn->query("INSERT INTO rs_affiliates_log (referrer, buyer, sale_id, commission_amount) 
                                  VALUES ('$referrer', '$buyerNick', $saleId, '$commission')");
                }
            }
        }
    }
}

try {
    // Lê o payload do webhook
    $payload = file_get_contents('php://input');
    $event = json_decode($payload, true);
    
    if (!$event) {
        logWebhook('ERROR: Invalid JSON payload', ['raw' => $payload]);
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    logWebhook('Webhook received', $event);
    
    // Busca configurações do gateway
    $gateway = $conn->query("SELECT * FROM rs_payment_gateways WHERE name = 'misticpay' AND active = 1")->fetch_assoc();
    
    if (!$gateway) {
        logWebhook('ERROR: Gateway not configured or inactive');
        http_response_code(400);
        echo json_encode(['error' => 'Gateway not configured']);
        exit;
    }
    
    // Valida assinatura do webhook (se configurado)
    if (!empty($gateway['webhook_secret'])) {
        $signature = $_SERVER['HTTP_X_MISTICPAY_SIGNATURE'] ?? '';
        $expectedSignature = hash_hmac('sha256', $payload, $gateway['webhook_secret']);
        
        if (!hash_equals($expectedSignature, $signature)) {
            logWebhook('ERROR: Invalid webhook signature', [
                'received' => $signature,
                'expected' => $expectedSignature
            ]);
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }
    }
    
    // Processa eventos
    $eventType = $event['type'] ?? '';
    
    switch ($eventType) {
        case 'payment.approved':
        case 'payment.paid':
            // Pagamento aprovado
            $paymentId = $event['data']['id'] ?? null;
            $externalRef = $event['data']['external_reference'] ?? null;
            $amount = $event['data']['amount'] ?? 0;
            $status = $event['data']['status'] ?? '';
            
            if (!$externalRef) {
                logWebhook('ERROR: Missing external_reference');
                break;
            }
            
            // Busca a venda pelo external_reference
            $sale = $conn->query("SELECT * FROM rs_sales WHERE payment_id = '$externalRef' AND status = 'PENDING'")->fetch_assoc();
            
            if (!$sale) {
                logWebhook('WARNING: Sale not found or already processed', ['external_ref' => $externalRef]);
                break;
            }
            
            // Atualiza status da venda
            $saleId = (int)$sale['id'];
            $conn->query("UPDATE rs_sales SET 
                         status = 'APPROVED',
                         approved_at = NOW(),
                         payment_data = '" . $conn->real_escape_string(json_encode($event['data'])) . "'
                         WHERE id = $saleId");
            
            logWebhook('Payment approved', [
                'sale_id' => $saleId,
                'payment_id' => $paymentId,
                'amount' => $amount
            ]);
            
            // Atualiza estoque (se aplicável)
            $productId = (int)$sale['product_id'];
            $conn->query("UPDATE rs_products SET stock_qty = stock_qty - 1 
                         WHERE id = $productId AND stock_qty IS NOT NULL AND stock_qty > 0");
            
            // Processa entrega
            $playerNick = $conn->real_escape_string($sale['player']);
            processarEntrega($conn, $productId, $playerNick);
            
            // Adiciona cashback
            addCashback($conn, $playerNick, $sale['price_paid']);
            
            // Processa afiliado
            processarAfiliado($conn, $playerNick, $sale['price_paid'], $saleId);
            
            logWebhook('Order processed successfully', ['sale_id' => $saleId]);
            break;
            
        case 'payment.cancelled':
        case 'payment.failed':
            // Pagamento cancelado ou falhou
            $externalRef = $event['data']['external_reference'] ?? null;
            
            if ($externalRef) {
                $conn->query("UPDATE rs_sales SET 
                             status = 'FAILED',
                             payment_data = '" . $conn->real_escape_string(json_encode($event['data'])) . "'
                             WHERE payment_id = '$externalRef'");
                
                logWebhook('Payment failed/cancelled', ['external_ref' => $externalRef]);
            }
            break;
            
        case 'payment.pending':
            // Pagamento pendente (aguardando aprovação)
            $externalRef = $event['data']['external_reference'] ?? null;
            
            if ($externalRef) {
                $conn->query("UPDATE rs_sales SET 
                             payment_data = '" . $conn->real_escape_string(json_encode($event['data'])) . "'
                             WHERE payment_id = '$externalRef'");
                
                logWebhook('Payment pending', ['external_ref' => $externalRef]);
            }
            break;
            
        default:
            logWebhook('Unknown event type', ['type' => $eventType]);
            break;
    }
    
    // Responde sucesso
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Webhook processed']);
    
} catch (Exception $e) {
    logWebhook('EXCEPTION: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>