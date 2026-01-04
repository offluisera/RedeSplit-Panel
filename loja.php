<?php
include 'includes/session.php';
include 'includes/db.php';

// VERIFICA SE É ADMIN (Para liberar o Modo Teste)
$isAdmin = isset($_SESSION['user_rank']) && in_array($_SESSION['user_rank'], ['administrador', 'master']);
// Get logged user
$loggedUser = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : (isset($_SESSION['admin_user']) ? $_SESSION['admin_user'] : null);

// --- SISTEMA DE AFILIADOS: CAPTURA COOKIE ---
if (isset($_GET['ref'])) {
    $refName = trim($conn->real_escape_string($_GET['ref']));
    setcookie('rs_ref', $refName, time() + (86400 * 30), "/");
}

// --- 0. API AJAX ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 0.1 LOG DE CARRINHO ABANDONADO
    if (isset($_POST['log_abandoned'])) {
        $player = trim($conn->real_escape_string($_POST['player']));
        $product = trim($conn->real_escape_string($_POST['product']));
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if (!empty($player) && !empty($product)) {
            $check = $conn->query("SELECT id FROM rs_cart_abandoned WHERE player='$player' AND product_name='$product' AND created_at > NOW() - INTERVAL 1 HOUR");
            if (!$check || $check->num_rows == 0) {
                $conn->query("INSERT INTO rs_cart_abandoned (player, product_name, ip_address) VALUES ('$player', '$product', '$ip')");
            }
        }
        exit;
    }

    // 0.2 VERIFICAR CUPOM
    if (isset($_POST['check_coupon'])) {
        header('Content-Type: application/json');
        $code = strtoupper(trim($conn->real_escape_string($_POST['code'])));
        
        // Verifica se a coluna 'active' existe, senão usa apenas discount_percent
        $activeCheck = $conn->query("SHOW COLUMNS FROM rs_keys LIKE 'active'");
        $hasActiveColumn = ($activeCheck && $activeCheck->num_rows > 0);
        
        $whereClauses = "code = '$code' AND discount_percent > 0";
        if ($hasActiveColumn) {
            $whereClauses .= " AND active = 1";
        }
        
        $check = $conn->query("SELECT * FROM rs_keys WHERE $whereClauses");
        
        if ($check && $check->num_rows > 0) {
            $k = $check->fetch_assoc();
            if ($k['uses'] < $k['max_uses']) {
                echo json_encode(['valid' => true, 'percent' => (int)$k['discount_percent'], 'code' => $k['code']]);
            } else {
                echo json_encode(['valid' => false, 'msg' => 'Cupom esgotado.']);
            }
        } else {
            echo json_encode(['valid' => false, 'msg' => 'Cupom inválido.']);
        }
        exit;
    }

    // 0.3 NOTIFICAÇÕES DE COMPRAS RECENTES (AJAX)
    if (isset($_POST['get_recent_sales'])) {
        header('Content-Type: application/json');
        
        $lastId = isset($_POST['last_id']) ? (int)$_POST['last_id'] : 0;
        
        // Busca vendas aprovadas mais recentes que o último ID
        $query = $conn->query("SELECT id, player, product_name, created_at 
                               FROM rs_sales 
                               WHERE status='APPROVED' AND id > $lastId 
                               ORDER BY id DESC 
                               LIMIT 5");
        
        $sales = [];
        if ($query) {
            while($row = $query->fetch_assoc()) {
                $sales[] = [
                    'id' => (int)$row['id'],
                    'player' => $row['player'],
                    'product' => $row['product_name'],
                    'time' => $row['created_at']
                ];
            }
        }
        
        echo json_encode(['sales' => array_reverse($sales)]); // Reverte para mostrar do mais antigo ao mais novo
        exit;
    }
    
    // 0.4 ENVIAR AVALIAÇÃO
    if (isset($_POST['submit_review'])) {
        header('Content-Type: application/json');
        
        if (!$loggedUser) {
            echo json_encode(['success' => false, 'msg' => 'Você precisa estar logado para avaliar!']);
            exit;
        }
        
        $productId = (int)$_POST['product_id'];
        $rating = max(1, min(5, (int)$_POST['rating'])); // Entre 1 e 5
        $comment = trim($conn->real_escape_string($_POST['comment']));
        $playerName = $conn->real_escape_string($loggedUser);
        
        // Verifica se já comprou este produto
        $hasPurchased = $conn->query("SELECT id FROM rs_sales WHERE player='$playerName' AND product_id=$productId AND status='APPROVED' LIMIT 1");
        
        if (!$hasPurchased || $hasPurchased->num_rows == 0) {
            echo json_encode(['success' => false, 'msg' => 'Você precisa ter comprado este produto para avaliar!']);
            exit;
        }
        
        // Verifica se já avaliou
        $hasReviewed = $conn->query("SELECT id FROM rs_reviews WHERE player='$playerName' AND product_id=$productId LIMIT 1");
        
        if ($hasReviewed && $hasReviewed->num_rows > 0) {
            // Atualiza avaliação existente
            $conn->query("UPDATE rs_reviews SET rating=$rating, comment='$comment', updated_at=NOW() WHERE player='$playerName' AND product_id=$productId");
            echo json_encode(['success' => true, 'msg' => 'Avaliação atualizada com sucesso!']);
        } else {
            // Cria nova avaliação
            $conn->query("INSERT INTO rs_reviews (product_id, player, rating, comment) VALUES ($productId, '$playerName', $rating, '$comment')");
            echo json_encode(['success' => true, 'msg' => 'Avaliação enviada com sucesso!']);
        }
        exit;
    }
    
    // 0.5 BUSCAR AVALIAÇÕES
    if (isset($_POST['get_reviews'])) {
        header('Content-Type: application/json');
        
        $productId = (int)$_POST['product_id'];
        $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
        $limit = 5;
        $offset = ($page - 1) * $limit;
        
        // Total de avaliações
        $totalQuery = $conn->query("SELECT COUNT(*) as total FROM rs_reviews WHERE product_id=$productId");
        $total = $totalQuery ? $totalQuery->fetch_assoc()['total'] : 0;
        
        // Estatísticas
        $statsQuery = $conn->query("SELECT 
            AVG(rating) as avg_rating,
            COUNT(*) as total_reviews,
            SUM(CASE WHEN rating=5 THEN 1 ELSE 0 END) as star_5,
            SUM(CASE WHEN rating=4 THEN 1 ELSE 0 END) as star_4,
            SUM(CASE WHEN rating=3 THEN 1 ELSE 0 END) as star_3,
            SUM(CASE WHEN rating=2 THEN 1 ELSE 0 END) as star_2,
            SUM(CASE WHEN rating=1 THEN 1 ELSE 0 END) as star_1
            FROM rs_reviews WHERE product_id=$productId");
        
        $stats = $statsQuery ? $statsQuery->fetch_assoc() : [
            'avg_rating' => 0, 'total_reviews' => 0,
            'star_5' => 0, 'star_4' => 0, 'star_3' => 0, 'star_2' => 0, 'star_1' => 0
        ];
        
        // Avaliações
        $reviewsQuery = $conn->query("SELECT * FROM rs_reviews WHERE product_id=$productId ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
        
        $reviews = [];
        if ($reviewsQuery) {
            while($row = $reviewsQuery->fetch_assoc()) {
                $reviews[] = [
                    'id' => (int)$row['id'],
                    'player' => $row['player'],
                    'rating' => (int)$row['rating'],
                    'comment' => $row['comment'],
                    'created_at' => $row['created_at'],
                    'helpful_count' => (int)$row['helpful_count']
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'reviews' => $reviews,
            'stats' => $stats,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]);
        exit;
    }
    
    // 0.6 MARCAR AVALIAÇÃO COMO ÚTIL
    if (isset($_POST['mark_helpful'])) {
        header('Content-Type: application/json');
        
        $reviewId = (int)$_POST['review_id'];
        $conn->query("UPDATE rs_reviews SET helpful_count = helpful_count + 1 WHERE id=$reviewId");
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    // 0.7 VERIFICAR UPGRADE DISPONÍVEL
    if (isset($_POST['check_upgrade'])) {
        header('Content-Type: application/json');
        
        $playerName = trim($conn->real_escape_string($_POST['player_name']));
        $productId = (int)$_POST['product_id'];
        
        if (!isValidMinecraftNick($playerName)) {
            echo json_encode(['has_upgrade' => false, 'msg' => 'Nick inválido']);
            exit;
        }
        
        // Busca VIP atual do jogador
        $currentVipProduct = getPlayerVipProduct($conn, $playerName);
        
        // Busca produto alvo
        $targetProduct = $conn->query("SELECT * FROM rs_products WHERE id = $productId")->fetch_assoc();
        
        if (!$targetProduct) {
            echo json_encode(['has_upgrade' => false, 'msg' => 'Produto não encontrado']);
            exit;
        }
        
        // Calcula upgrade
        $upgradeInfo = calculateUpgrade($conn, $currentVipProduct, $targetProduct);
        
        echo json_encode([
            'has_upgrade' => $upgradeInfo['is_upgrade'],
            'upgrade_info' => $upgradeInfo
        ]);
        exit;
    }
    
    // 0.8 GIRAR ROLETA
    if (isset($_POST['spin_roulette'])) {
        header('Content-Type: application/json');
        
        if (!$loggedUser) { 
            echo json_encode(['status' => 'error', 'msg' => 'Você precisa estar logado!']); 
            exit; 
        }

        $today = date('Y-m-d');
        $checkLog = $conn->query("SELECT * FROM rs_roulette_log WHERE player = '$loggedUser' AND DATE(spun_at) = '$today'");
        
        if ($checkLog && $checkLog->num_rows > 0) { 
            echo json_encode(['status' => 'error', 'msg' => 'Você já girou hoje! Volte amanhã.']); 
            exit; 
        }

        $items = []; 
        $totalWeight = 0;
        $q = $conn->query("SELECT * FROM rs_roulette_items");
        if ($q) { 
            while($row = $q->fetch_assoc()) { 
                $items[] = $row; 
                $totalWeight += $row['chance']; 
            } 
        }

        if (empty($items)) { 
            echo json_encode(['status' => 'error', 'msg' => 'Nenhum prêmio configurado.']); 
            exit; 
        }

        $rand = mt_rand(1, $totalWeight);
        $currentWeight = 0; 
        $winner = null; 
        $winnerIndex = 0;

        foreach ($items as $index => $item) {
            $currentWeight += $item['chance'];
            if ($rand <= $currentWeight) { 
                $winner = $item; 
                $winnerIndex = $index; 
                break; 
            }
        }

        $prizeMsg = "Que pena! Tente amanhã."; 
        $couponCode = null;

        if ($winner['type'] == 'COUPON') {
            $couponCode = 'GIFT-' . strtoupper(substr(md5(uniqid()), 0, 6));
            
            // Verifica se existe coluna active
            $activeCheck = $conn->query("SHOW COLUMNS FROM rs_keys LIKE 'active'");
            $hasActiveColumn = ($activeCheck && $activeCheck->num_rows > 0);
            
            if ($hasActiveColumn) {
                $conn->query("INSERT INTO rs_keys (code, discount_percent, max_uses, active) VALUES ('$couponCode', {$winner['value']}, 1, 1)");
            } else {
                $conn->query("INSERT INTO rs_keys (code, discount_percent, max_uses) VALUES ('$couponCode', {$winner['value']}, 1)");
            }
            
            $prizeMsg = "Parabéns! Você ganhou um cupom de {$winner['value']}% OFF!";
        }

        $conn->query("INSERT INTO rs_roulette_log (player, prize_label) VALUES ('$loggedUser', '{$winner['label']}')");

        echo json_encode([
            'status' => 'success', 
            'winner_index' => $winnerIndex, 
            'items' => $items, 
            'message' => $prizeMsg, 
            'coupon' => $couponCode
        ]);
        exit;
    }
}

include 'includes/header.php';

// FUNÇÕES AUXILIARES
function isValidMinecraftNick($nick) { 
    return preg_match('/^[a-zA-Z0-9_]{3,16}$/', $nick); 
}

// HIERARQUIA DE VIPs (do menor para o maior)
function getVipHierarchy() {
    return ['VIP', 'VIP+', 'MVP', 'MVP+', 'ELITE'];
}

// Verifica o VIP atual do jogador
function getPlayerCurrentVip($conn, $playerName) {
    $playerName = $conn->real_escape_string($playerName);
    
    // Busca o rank atual do jogador
    $query = $conn->query("SELECT rank_id FROM rs_ranks WHERE display_name = '$playerName' LIMIT 1");
    
    if ($query && $query->num_rows > 0) {
        $rank = strtoupper(trim($query->fetch_assoc()['rank_id']));
        
        // Verifica se é um VIP válido
        $hierarchy = getVipHierarchy();
        if (in_array($rank, $hierarchy)) {
            return $rank;
        }
    }
    
    return null; // Não tem VIP
}

// Retorna informações do produto VIP do jogador
function getPlayerVipProduct($conn, $playerName) {
    $currentVip = getPlayerCurrentVip($conn, $playerName);
    
    if (!$currentVip) {
        return null;
    }
    
    // Busca o produto correspondente ao VIP atual
    // Assume que o nome do produto contém o nome do VIP
    $query = $conn->query("SELECT * FROM rs_products WHERE UPPER(name) LIKE '%{$currentVip}%' ORDER BY price DESC LIMIT 1");
    
    if ($query && $query->num_rows > 0) {
        $product = $query->fetch_assoc();
        $product['vip_rank'] = $currentVip;
        return $product;
    }
    
    return null;
}

// Calcula upgrade entre dois VIPs
function calculateUpgrade($conn, $currentVipProduct, $targetProduct) {
    if (!$currentVipProduct) {
        return [
            'is_upgrade' => false,
            'original_price' => (float)$targetProduct['price'],
            'upgrade_price' => (float)$targetProduct['price'],
            'discount' => 0,
            'current_vip' => null
        ];
    }
    
    $hierarchy = getVipHierarchy();
    $currentVip = $currentVipProduct['vip_rank'];
    
    // Identifica o VIP do produto alvo
    $targetVip = null;
    foreach ($hierarchy as $vip) {
        if (stripos($targetProduct['name'], $vip) !== false) {
            $targetVip = $vip;
            break;
        }
    }
    
    if (!$targetVip) {
        // Produto não é VIP, sem upgrade
        return [
            'is_upgrade' => false,
            'original_price' => (float)$targetProduct['price'],
            'upgrade_price' => (float)$targetProduct['price'],
            'discount' => 0,
            'current_vip' => $currentVip
        ];
    }
    
    // Verifica se é realmente um upgrade (VIP maior)
    $currentIndex = array_search($currentVip, $hierarchy);
    $targetIndex = array_search($targetVip, $hierarchy);
    
    if ($targetIndex > $currentIndex) {
        // É um upgrade válido!
        $originalPrice = (float)$targetProduct['price'];
        $currentPrice = (float)$currentVipProduct['price'];
        $upgradePrice = max(0, $originalPrice - $currentPrice);
        
        return [
            'is_upgrade' => true,
            'original_price' => $originalPrice,
            'upgrade_price' => $upgradePrice,
            'discount' => $currentPrice,
            'current_vip' => $currentVip,
            'target_vip' => $targetVip,
            'savings_percent' => $originalPrice > 0 ? round(($currentPrice / $originalPrice) * 100) : 0
        ];
    }
    
    // Não é upgrade (downgrade ou mesmo nível)
    return [
        'is_upgrade' => false,
        'is_downgrade' => ($targetIndex < $currentIndex),
        'is_same' => ($targetIndex === $currentIndex),
        'original_price' => (float)$targetProduct['price'],
        'upgrade_price' => (float)$targetProduct['price'],
        'discount' => 0,
        'current_vip' => $currentVip,
        'target_vip' => $targetVip
    ];
}

function processarEntrega($conn, $productId, $playerNick) {
    // Verifica se existe tabela rs_delivery_queue ou rs_command_queue
    $tableCheck = $conn->query("SHOW TABLES LIKE 'rs_delivery_queue'");
    $useDeliveryQueue = ($tableCheck && $tableCheck->num_rows > 0);
    $queueTable = $useDeliveryQueue ? 'rs_delivery_queue' : 'rs_command_queue';
    
    $bundleCheck = $conn->query("SELECT * FROM rs_bundle_items WHERE product_id = $productId");
    if ($bundleCheck && $bundleCheck->num_rows > 0) {
        while ($item = $bundleCheck->fetch_assoc()) {
            $cmd = str_replace(['{player}', '%player%'], [$playerNick, $playerNick], $item['command']);
            
            if ($useDeliveryQueue) {
                $conn->query("INSERT INTO rs_delivery_queue (player_name, command, status) VALUES ('$playerNick', '$cmd', 'PENDING')");
            } else {
                $conn->query("INSERT INTO rs_command_queue (command, status) VALUES ('$cmd', 'WAITING')");
            }
        }
    } else {
        $prod = $conn->query("SELECT command FROM rs_products WHERE id = $productId")->fetch_assoc();
        if ($prod && !empty($prod['command'])) {
            $cmd = str_replace(['{player}', '%player%'], [$playerNick, $playerNick], $prod['command']);
            
            if ($useDeliveryQueue) {
                $conn->query("INSERT INTO rs_delivery_queue (player_name, command, status) VALUES ('$playerNick', '$cmd', 'PENDING')");
            } else {
                $conn->query("INSERT INTO rs_command_queue (command, status) VALUES ('$cmd', 'WAITING')");
            }
        }
    }
}

function processarAfiliado($conn, $buyerNick, $salePrice, $saleId) {
    if (isset($_COOKIE['rs_ref']) && !empty($_COOKIE['rs_ref'])) {
        $referrer = $conn->real_escape_string($_COOKIE['rs_ref']);
        
        if (strtolower($referrer) !== strtolower($buyerNick) && $salePrice > 0) {
            $checkRef = $conn->query("SELECT id FROM rs_players WHERE name = '$referrer'");
            if ($checkRef && $checkRef->num_rows > 0) {
                $commission = $salePrice * 0.10;
                $conn->query("UPDATE rs_players SET splitcoins = splitcoins + $commission WHERE name = '$referrer'");
                
                // Verifica se tabela de log existe
                $logCheck = $conn->query("SHOW TABLES LIKE 'rs_affiliates_log'");
                if ($logCheck && $logCheck->num_rows > 0) {
                    $conn->query("INSERT INTO rs_affiliates_log (referrer, buyer, sale_id, commission_amount) VALUES ('$referrer', '$buyerNick', $saleId, '$commission')");
                }
            }
        }
    }
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime; 
    $ago = new DateTime($datetime); 
    $diff = $now->diff($ago);
    $string = ['y'=>'ano','m'=>'mês','d'=>'dia','h'=>'hora','i'=>'minuto','s'=>'segundo'];
    foreach ($string as $k => &$v) { 
        if ($diff->$k) { 
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : ''); 
        } else { 
            unset($string[$k]); 
        } 
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'há ' . implode(', ', $string) : 'agora';
}

// --- 1. PROCESSAR PEDIDO (COMPRA ÚNICA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_product'])) {
    $nick = trim($conn->real_escape_string($_POST['nick']));
    $pid = (int)$_POST['product_id'];
    
    if (!isValidMinecraftNick($nick)) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Nick Inválido!', 'Use apenas letras, números e underline.', 'error'); });</script>";
    } else {
        $isTestMode = ($isAdmin && isset($_POST['admin_test_mode']) && $_POST['admin_test_mode'] == '1');
        
        $giftFrom = null;
        if (isset($_POST['is_gift']) && !empty($_POST['gifter_nick'])) {
            $giftFrom = trim($conn->real_escape_string($_POST['gifter_nick']));
            if (!isValidMinecraftNick($giftFrom)) $giftFrom = null;
        }
        
        $mainProd = $conn->query("SELECT * FROM rs_products WHERE id = $pid")->fetch_assoc();
        
        if ($mainProd) {
            if (!$isTestMode && $mainProd['stock_qty'] !== null && $mainProd['stock_qty'] <= 0) {
                echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Esgotado!', 'Este produto não possui mais estoque.', 'error'); });</script>";
            } else {
                $price = (float)$mainProd['price'];
                $finalPrice = $price;
                $couponNote = "";
                $couponCode = "";
                $discountPercent = 0;
                $upgradeNote = "";
                $isUpgrade = false;
                
                // VERIFICA UPGRADE DE VIP
                $currentVipProduct = getPlayerVipProduct($conn, $nick);
                $upgradeInfo = calculateUpgrade($conn, $currentVipProduct, $mainProd);
                
                if ($upgradeInfo['is_upgrade'] && !$isTestMode) {
                    $finalPrice = $upgradeInfo['upgrade_price'];
                    $upgradeNote = " [UPGRADE de {$upgradeInfo['current_vip']}]";
                    $isUpgrade = true;
                } elseif (isset($upgradeInfo['is_same']) && $upgradeInfo['is_same']) {
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Atenção!', 'Você já possui este VIP ativo!', 'warning'); });</script>";
                    // Continua para permitir renovação, se desejar
                } elseif (isset($upgradeInfo['is_downgrade']) && $upgradeInfo['is_downgrade']) {
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Atenção!', 'Você já possui um VIP superior ({$upgradeInfo['current_vip']}). Este seria um downgrade.', 'info'); });</script>";
                    // Permite comprar mesmo assim (pode querer para presentear, etc)
                }
                
                if ($isTestMode) {
                    $finalPrice = 0.00;
                    $couponNote = " [TESTE ADMIN]";
                } else {
                    // Cupom só aplica no valor JÁ com desconto de upgrade
                    $couponCode = isset($_POST['coupon']) ? strtoupper(trim($conn->real_escape_string($_POST['coupon']))) : '';
                    if (!empty($couponCode)) {
                        $activeCheck = $conn->query("SHOW COLUMNS FROM rs_keys LIKE 'active'");
                        $hasActiveColumn = ($activeCheck && $activeCheck->num_rows > 0);
                        
                        $whereClauses = "code = '$couponCode' AND discount_percent > 0";
                        if ($hasActiveColumn) {
                            $whereClauses .= " AND active = 1";
                        }
                        
                        $checkKey = $conn->query("SELECT * FROM rs_keys WHERE $whereClauses");
                        if ($checkKey && $checkKey->num_rows > 0) {
                            $k = $checkKey->fetch_assoc();
                            if ($k['uses'] < $k['max_uses']) {
                                $percent = (int)$k['discount_percent'];
                                $discountVal = ($finalPrice * $percent) / 100; // Aplica no valor JÁ com upgrade
                                $finalPrice = max(0, $finalPrice - $discountVal);
                                $discountPercent = $percent;
                                $conn->query("UPDATE rs_keys SET uses = uses + 1 WHERE id = " . $k['id']);
                                $couponNote = " (Cupom -{$percent}%)";
                            }
                        }
                    }
                }
                
                $status = $isTestMode ? 'APPROVED' : 'PENDING';
                $approvedAt = $isTestMode ? "NOW()" : "NULL";
                $giftSQL = $giftFrom ? "'$giftFrom'" : "NULL";
                $pName = $conn->real_escape_string($mainProd['name'] . $upgradeNote . $couponNote);
                
                // Verifica se colunas coupon_used e discount_percent existem
                $couponUsedCheck = $conn->query("SHOW COLUMNS FROM rs_sales LIKE 'coupon_used'");
                $hasCouponUsed = ($couponUsedCheck && $couponUsedCheck->num_rows > 0);
                
                if ($hasCouponUsed) {
                    $conn->query("INSERT INTO rs_sales (player, gift_from, product_id, product_name, price_paid, status, approved_at, coupon_used, discount_percent) 
                                  VALUES ('$nick', $giftSQL, $pid, '$pName', $finalPrice, '$status', $approvedAt, '$couponCode', $discountPercent)");
                } else {
                    $conn->query("INSERT INTO rs_sales (player, gift_from, product_id, product_name, price_paid, status, approved_at) 
                                  VALUES ('$nick', $giftSQL, $pid, '$pName', $finalPrice, '$status', $approvedAt)");
                }
                
                $saleId = $conn->insert_id;

                if (!$isTestMode && $mainProd['stock_qty'] !== null) {
                    $conn->query("UPDATE rs_products SET stock_qty = stock_qty - 1 WHERE id = $pid");
                }

                if ($isTestMode) {
                    processarEntrega($conn, $pid, $nick);
                }

                if ($status === 'APPROVED' && !$isTestMode) {
                    processarAfiliado($conn, $nick, $finalPrice, $saleId);
                }

                $totalMsg = "R$ " . number_format($finalPrice, 2, ',', '.');
                
                if (isset($_POST['add_upsell']) && $_POST['add_upsell'] == '1' && !empty($mainProd['upsell_product_id'])) {
                    $upsellId = (int)$mainProd['upsell_product_id'];
                    $upsellProd = $conn->query("SELECT * FROM rs_products WHERE id = $upsellId")->fetch_assoc();
                    
                    if ($upsellProd) {
                        if (!$isTestMode && $upsellProd['stock_qty'] !== null && $upsellProd['stock_qty'] <= 0) {
                            // sem estoque no upsell
                        } else {
                            $uPrice = $isTestMode ? 0.00 : (float)$upsellProd['price'];
                            $uName = $conn->real_escape_string("[OFERTA] " . $upsellProd['name']);
                            
                            if ($hasCouponUsed) {
                                $conn->query("INSERT INTO rs_sales (player, gift_from, product_id, product_name, price_paid, status, approved_at, coupon_used, discount_percent) 
                                              VALUES ('$nick', $giftSQL, $upsellId, '$uName', $uPrice, '$status', $approvedAt, '', 0)");
                            } else {
                                $conn->query("INSERT INTO rs_sales (player, gift_from, product_id, product_name, price_paid, status, approved_at) 
                                              VALUES ('$nick', $giftSQL, $upsellId, '$uName', $uPrice, '$status', $approvedAt)");
                            }
                            
                            $upsellSaleId = $conn->insert_id;
                            
                            if (!$isTestMode && $upsellProd['stock_qty'] !== null) {
                                $conn->query("UPDATE rs_products SET stock_qty = stock_qty - 1 WHERE id = $upsellId");
                            }
                            
                            if ($isTestMode) {
                                processarEntrega($conn, $upsellId, $nick);
                            }

                            if ($status === 'APPROVED' && !$isTestMode) {
                                processarAfiliado($conn, $nick, $uPrice, $upsellSaleId);
                            }
                            
                            $totalMsg = "R$ " . number_format($finalPrice + $uPrice, 2, ',', '.');
                        }
                    }
                }

                if ($isTestMode) {
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ title: 'Teste Realizado!', text: 'Venda aprovada e item enviado para $nick.', icon: 'warning' }); });</script>";
                } else {
                    $extraText = $giftFrom ? "🎁 Presente de $giftFrom para $nick." : "";
                    echo "<script>document.addEventListener('DOMContentLoaded', function() { 
                        Swal.fire({ 
                            title: 'Pedido Gerado!', 
                            html: '$extraText Total: $totalMsg.', 
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonText: 'Ver Meus Pedidos',
                            cancelButtonText: 'Continuar Comprando'
                        }).then((result) => { 
                            if (result.isConfirmed) { 
                                window.location.href = 'minhas_compras.php'; 
                            } 
                        }); 
                    });</script>";
                }
            }
        }
    }
}

// --- 1.1 PROCESSAR PEDIDO (CARRINHO EM LOTE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_checkout'])) {
    $nick = trim($conn->real_escape_string($_POST['nick']));
    $cartItems = json_decode($_POST['cart_data'], true);
    
    if (!isValidMinecraftNick($nick)) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Nick Inválido!', 'Use apenas letras e números.', 'error'); });</script>";
    } elseif (empty($cartItems)) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Carrinho Vazio!', 'Adicione itens.', 'warning'); });</script>";
    } else {
        $isTestMode = ($isAdmin && isset($_POST['admin_test_mode']) && $_POST['admin_test_mode'] == '1');
        $giftFrom = isset($_POST['is_gift']) && !empty($_POST['gifter_nick']) ? trim($conn->real_escape_string($_POST['gifter_nick'])) : null;
        $giftSQL = $giftFrom ? "'$giftFrom'" : "NULL";

        $totalProcessed = 0;
        $successCount = 0;
        
        $couponCode = isset($_POST['coupon']) ? strtoupper(trim($conn->real_escape_string($_POST['coupon']))) : '';
        $globalDiscountPercent = 0;

        if (!empty($couponCode) && !$isTestMode) {
            $activeCheck = $conn->query("SHOW COLUMNS FROM rs_keys LIKE 'active'");
            $hasActiveColumn = ($activeCheck && $activeCheck->num_rows > 0);
            
            $whereClauses = "code = '$couponCode' AND discount_percent > 0";
            if ($hasActiveColumn) {
                $whereClauses .= " AND active = 1";
            }
            
            $checkKey = $conn->query("SELECT * FROM rs_keys WHERE $whereClauses");
            if ($checkKey && $checkKey->num_rows > 0) {
                $k = $checkKey->fetch_assoc();
                if ($k['uses'] < $k['max_uses']) {
                    $globalDiscountPercent = (int)$k['discount_percent'];
                }
            }
        }
        
        $couponUsedCheck = $conn->query("SHOW COLUMNS FROM rs_sales LIKE 'coupon_used'");
        $hasCouponUsed = ($couponUsedCheck && $couponUsedCheck->num_rows > 0);
        
        foreach ($cartItems as $itemData) {
            $pid = (int)$itemData['id'];
            $prod = $conn->query("SELECT * FROM rs_products WHERE id = $pid")->fetch_assoc();
            
            if ($prod) {
                if (!$isTestMode && $prod['stock_qty'] !== null && $prod['stock_qty'] <= 0) continue;

                $price = (float)$prod['price'];
                $finalPrice = $isTestMode ? 0.00 : $price;
                
                if ($globalDiscountPercent > 0 && !$isTestMode) {
                    $discountVal = ($price * $globalDiscountPercent) / 100;
                    $finalPrice = max(0, $price - $discountVal);
                }

                $status = $isTestMode ? 'APPROVED' : 'PENDING';
                $approvedAt = $isTestMode ? "NOW()" : "NULL";
                $pName = $conn->real_escape_string($prod['name'] . ($globalDiscountPercent > 0 ? " (-{$globalDiscountPercent}%)" : ""));
                
                if ($hasCouponUsed) {
                    $conn->query("INSERT INTO rs_sales (player, gift_from, product_id, product_name, price_paid, status, approved_at, coupon_used, discount_percent) 
                                  VALUES ('$nick', $giftSQL, $pid, '$pName', $finalPrice, '$status', $approvedAt, '$couponCode', $globalDiscountPercent)");
                } else {
                    $conn->query("INSERT INTO rs_sales (player, gift_from, product_id, product_name, price_paid, status, approved_at) 
                                  VALUES ('$nick', $giftSQL, $pid, '$pName', $finalPrice, '$status', $approvedAt)");
                }
                
                $saleId = $conn->insert_id;
                
                if (!$isTestMode && $prod['stock_qty'] !== null) {
                    $conn->query("UPDATE rs_products SET stock_qty = stock_qty - 1 WHERE id = $pid");
                }

                if ($isTestMode) {
                    processarEntrega($conn, $pid, $nick);
                }

                if ($status === 'APPROVED' && !$isTestMode) {
                    processarAfiliado($conn, $nick, $finalPrice, $saleId);
                }
                
                $totalProcessed += $finalPrice;
                $successCount++;
            }
        }

        if (!empty($couponCode) && $successCount > 0 && !$isTestMode && $globalDiscountPercent > 0) {
            $conn->query("UPDATE rs_keys SET uses = uses + 1 WHERE code = '$couponCode'");
        }

        if ($successCount > 0) {
            echo "<script>
                localStorage.removeItem('rs_cart');
                document.addEventListener('DOMContentLoaded', function() { 
                    Swal.fire({ 
                        title: 'Pedido Realizado!', 
                        html: 'Itens: $successCount<br>Total: R$ " . number_format($totalProcessed, 2, ',', '.') . "', 
                        icon: 'success',
                        confirmButtonText: 'Ver Meus Pedidos'
                    }).then((result) => { 
                        if (result.isConfirmed) {
                            window.location.href = 'minhas_compras.php'; 
                        }
                    }); 
                });
            </script>";
        } else {
            echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire('Erro', 'Não foi possível processar os itens.', 'error'); });</script>";
        }
    }
}

// --- 2. DADOS DOS WIDGETS ---
$metaValor = 500.00; 
$queryMeta = $conn->query("SELECT SUM(price_paid) as total FROM rs_sales WHERE status='APPROVED' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$arrecadado = $queryMeta->fetch_assoc()['total'] ?? 0;
$porcentagemMeta = ($metaValor > 0) ? round(($arrecadado / $metaValor) * 100) : 0;
$larguraBarra = min(100, $porcentagemMeta);

$queryTop = $conn->query("SELECT player, SUM(price_paid) as total FROM rs_sales WHERE status='APPROVED' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) GROUP BY player ORDER BY total DESC LIMIT 1");
$topMensal = $queryTop->fetch_assoc();

$banners_query = $conn->query("SELECT * FROM rs_banners WHERE active = 1 ORDER BY display_order ASC, id DESC");
$hasBanners = ($banners_query->num_rows > 0);

// --- BUNDLES E PRODUTOS ---
$bundleMap = [];
$bQuery = $conn->query("SELECT * FROM rs_bundle_items");
if ($bQuery) {
    while($bItem = $bQuery->fetch_assoc()) { 
        $bundleMap[$bItem['product_id']][] = $bItem['item_name']; 
    }
}

$result = $conn->query("SELECT * FROM rs_products ORDER BY price ASC");
$products = []; 
$servers = []; 
$allProductsMap = [];

while($row = $result->fetch_assoc()) {
    $allProductsMap[$row['id']] = $row;
    
    // Busca estatísticas de avaliações
    $reviewStats = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM rs_reviews WHERE product_id=" . $row['id']);
    if ($reviewStats && $reviewStats->num_rows > 0) {
        $stats = $reviewStats->fetch_assoc();
        $row['avg_rating'] = $stats['avg_rating'] ? round($stats['avg_rating'], 1) : 0;
        $row['total_reviews'] = $stats['total_reviews'];
    } else {
        $row['avg_rating'] = 0;
        $row['total_reviews'] = 0;
    }
    
    $srv = $row['server'] ?: 'Global';
    $cat = $row['category'] ?: 'OUTROS';
    $products[$srv][$cat][] = $row;
    if(!in_array($srv, $servers)) $servers[] = $srv;
}
$activeServer = isset($_GET['server']) ? $_GET['server'] : ($servers[0] ?? 'Global');

$rouletteItems = [];
$rQ = $conn->query("SELECT * FROM rs_roulette_items");
if ($rQ) { 
    while($r = $rQ->fetch_assoc()) { 
        $rouletteItems[] = $r; 
    } 
}
?>

<style>
    .order-bump-box { background-color: #fff3cd; border: 2px dashed #ffc107; border-radius: 8px; transition: all 0.3s; }
    .gift-box { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; }
    .admin-test-box { background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; color: #721c24; }
    .store-carousel-img { height: 380px; object-fit: cover; filter: brightness(0.7); }
    .carousel-caption { background: rgba(0,0,0,0.5); padding: 20px; border-radius: 15px; bottom: 20%; }
    .hover-effect { transition: transform 0.3s; }
    .hover-effect:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
    .sold-out-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; border-radius: 0.375rem; z-index: 1; }
    .sold-out-text { color: white; font-weight: bold; font-size: 1.5rem; transform: rotate(-15deg); }
    .category-tab { cursor: pointer; transition: all 0.3s; }
    .category-tab.active { background-color: #0d6efd !important; color: white !important; border-color: #0d6efd !important; }
    .product-card { position: relative; }
    
    .flash-sale-badge { background: linear-gradient(45deg, #dc3545, #c82333); color: white; font-weight: bold; padding: 5px 15px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(220, 53, 69, 0.3); font-size: 0.85rem; display: inline-block; animation: pulse-red 2s infinite; position: absolute; top: 0; right: 15px; z-index: 10; }
    @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }

    .floating-container { position: fixed; bottom: 30px; right: 30px; z-index: 1050; display: flex; flex-direction: column; gap: 15px; align-items: center; }
    .floating-btn { width: 60px; height: 60px; border-radius: 50%; color: #fff; text-align: center; line-height: 60px; box-shadow: 0 4px 15px rgba(0,0,0,0.3); cursor: pointer; transition: transform 0.3s; position: relative; }
    .floating-btn:hover { transform: scale(1.1); }
    
    .btn-cart { background-color: #ffae00; }
    .btn-roulette { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); animation: spin-slow 10s linear infinite; }
    .btn-roulette i { animation: spin-counter 10s linear infinite; }
    .btn-affiliate { background: linear-gradient(45deg, #11998e, #38ef7d); }
    
    @keyframes spin-slow { 100% { transform: rotate(360deg); } } 
    @keyframes spin-counter { 100% { transform: rotate(-360deg); } }
    .cart-counter { position: absolute; top: -5px; right: -5px; background-color: #dc3545; color: white; border-radius: 50%; width: 24px; height: 24px; line-height: 24px; font-size: 12px; font-weight: bold; }

    /* NOTIFICAÇÕES TOAST */
    #toastContainer { position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 350px; }
    .sale-toast { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; margin-bottom: 10px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 12px; animation: slideInRight 0.4s ease-out, fadeOut 0.4s ease-in 4.6s; opacity: 1; transform: translateX(0); }
    .sale-toast.hiding { animation: slideOutRight 0.4s ease-in forwards; }
    .sale-toast img { border-radius: 8px; border: 2px solid rgba(255,255,255,0.3); }
    .sale-toast-content { flex: 1; }
    .sale-toast-player { font-weight: bold; font-size: 0.95rem; margin-bottom: 2px; }
    .sale-toast-product { font-size: 0.85rem; opacity: 0.9; }
    .sale-toast-time { font-size: 0.75rem; opacity: 0.7; margin-top: 2px; }
    
    @keyframes slideInRight { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideOutRight { from { transform: translateX(0); opacity: 1; } to { transform: translateX(400px); opacity: 0; } }
    @keyframes fadeOut { 0% { opacity: 1; } 90% { opacity: 1; } 100% { opacity: 0; } }
    
    @media (max-width: 768px) {
        #toastContainer { right: 10px; left: 10px; max-width: 100%; top: 10px; }
        .sale-toast { padding: 12px 15px; }
    }

    /* FAQ STYLES */
    .accordion-button:not(.collapsed) { background-color: #f8f9fa; color: #0d6efd; box-shadow: none; }
    .accordion-button:focus { box-shadow: none; border-color: rgba(0,0,0,.125); }
    .accordion-button { padding: 1.25rem 1.5rem; }
    .accordion-button::after { margin-left: auto; }
    .accordion-item { transition: all 0.3s ease; }
    .accordion-item:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(0,0,0,0.1) !important; }
    .text-purple { color: #6f42c1; }
    
    @media (max-width: 768px) {
        .accordion-button { font-size: 0.9rem; padding: 1rem; }
        .accordion-body { font-size: 0.85rem; }
    }

    /* SISTEMA DE AVALIAÇÕES */
    .star-rating { display: inline-flex; gap: 5px; cursor: pointer; }
    .star-rating i { font-size: 1.5rem; color: #ddd; transition: all 0.2s; }
    .star-rating i.active, .star-rating i:hover { color: #ffc107; }
    .star-rating.readonly { cursor: default; pointer-events: none; }
    .star-rating-small i { font-size: 1rem; }
    
    .review-card { background: #f8f9fa; border-left: 3px solid #ffc107; transition: all 0.3s; }
    .review-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); transform: translateY(-2px); }
    .review-avatar { width: 48px; height: 48px; border-radius: 50%; border: 2px solid #ffc107; }
    
    .review-stats-bar { background: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden; }
    .review-stats-fill { background: linear-gradient(90deg, #ffc107, #ff9800); height: 100%; transition: width 0.5s; }
    
    .rating-badge { background: linear-gradient(135deg, #ffc107, #ff9800); color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; box-shadow: 0 2px 8px rgba(255,193,7,0.3); }
    
    @media (max-width: 768px) {
        .review-card { font-size: 0.9rem; }
        .star-rating i { font-size: 1.2rem; }
    }

    /* SISTEMA DE UPGRADE */
    .upgrade-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; padding: 20px; box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3); position: relative; overflow: hidden; }
    .upgrade-box::before { content: ''; position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: rgba(255,255,255,0.1); transform: rotate(45deg); }
    .upgrade-badge { background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 20px; display: inline-block; font-weight: bold; margin-bottom: 10px; }
    .price-comparison { display: flex; align-items: center; gap: 15px; justify-content: center; margin: 15px 0; }
    .price-old { text-decoration: line-through; opacity: 0.7; font-size: 1.2rem; }
    .price-new { font-size: 2rem; font-weight: bold; color: #ffd700; text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
    .savings-tag { background: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.85rem; font-weight: bold; }
    
    @media (max-width: 768px) {
        .upgrade-box { padding: 15px; }
        .price-new { font-size: 1.5rem; }
    }

    #canvasContainer { position: relative; width: 300px; height: 300px; margin: 0 auto; }
    #rouletteCanvas { width: 100%; height: 100%; transition: transform 4s cubic-bezier(0.25, 0.1, 0.25, 1); }
    .roulette-arrow { position: absolute; top: -10px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 15px solid transparent; border-right: 15px solid transparent; border-top: 25px solid #333; z-index: 10; }
    
    #cartModal { padding-right: 0 !important; }
    #cartModal .modal-dialog { display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0 auto; max-width: 850px; }
    #cartModal .modal-content { border-radius: 15px; box-shadow: 0 15px 50px rgba(0,0,0,0.5); border: none; }

    @media (max-width: 768px) { 
        .store-carousel-img { height: 250px; } 
        .carousel-caption { bottom: 10%; padding: 10px; }
        .floating-container { bottom: 15px; right: 15px; gap: 10px; }
        .floating-btn { width: 50px; height: 50px; line-height: 50px; }
    }
</style>

<!-- CONTAINER DE NOTIFICAÇÕES TOAST -->
<div id="toastContainer"></div>

<div class="floating-container">
    <div class="floating-btn btn-affiliate" onclick="new bootstrap.Modal(document.getElementById('affiliateModal')).show()" title="Indique e Ganhe">
        <i class="fa-solid fa-hand-holding-dollar fa-lg"></i>
    </div>
    <div class="floating-btn btn-roulette" onclick="openRoulette()" title="Giro Diário">
        <i class="fa-solid fa-dharmachakra fa-xl"></i>
    </div>
    <div class="floating-btn btn-cart" onclick="openCartModal()">
        <i class="fa-solid fa-cart-shopping fa-lg"></i>
        <span class="cart-counter" id="cartCount">0</span>
    </div>
</div>

<!-- MODAL AFILIADOS -->
<div class="modal fade" id="affiliateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-money-bill-trend-up"></i> Sistema de Afiliados</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <?php if($loggedUser): ?>
                    <i class="fa-solid fa-bullhorn fa-3x text-success mb-3"></i>
                    <h4>Indique e Ganhe!</h4>
                    <p class="text-muted">Divulgue seu link exclusivo. Se alguém comprar na loja através dele, você ganha <b>10% do valor</b> em Cash/Coins automaticamente!</p>
                    
                    <div class="bg-light p-3 rounded border mb-3">
                        <label class="small fw-bold text-muted mb-1">Seu Link de Indicação:</label>
                        <div class="input-group">
                            <input type="text" id="affLink" class="form-control fw-bold text-center text-primary" 
                                   value="http://<?=$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF']?>?ref=<?= htmlspecialchars($loggedUser) ?>" readonly>
                            <button class="btn btn-primary" onclick="copyAffLink()"><i class="fa-solid fa-copy"></i> Copiar</button>
                        </div>
                    </div>
                    <small class="text-muted fst-italic">* Você não pode indicar a si mesmo.</small>
                <?php else: ?>
                    <i class="fa-solid fa-lock fa-3x text-secondary mb-3"></i>
                    <h5>Faça Login para Participar</h5>
                    <p class="text-muted">Você precisa estar logado no painel para gerar seu link de afiliado e receber as recompensas.</p>
                    <a href="login.php" class="btn btn-success fw-bold px-4">Fazer Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- CAROUSEL DE BANNERS -->
<?php if($hasBanners): ?>
<div id="storeCarousel" class="carousel slide mb-5 shadow rounded-4 overflow-hidden" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <?php $i=0; $banners_query->data_seek(0); while($b=$banners_query->fetch_assoc()): ?>
        <button type="button" data-bs-target="#storeCarousel" data-bs-slide-to="<?= $i ?>" <?= ($i==0)?'class="active"':'' ?>></button>
        <?php $i++; endwhile; ?>
    </div>
    <div class="carousel-inner">
        <?php $i=0; $banners_query->data_seek(0); while($b=$banners_query->fetch_assoc()): ?>
        <div class="carousel-item <?= ($i==0)?'active':'' ?>" data-bs-interval="5000">
            <img src="<?= htmlspecialchars($b['image_url']) ?>" class="d-block w-100 store-carousel-img" alt="Banner">
            <div class="carousel-caption d-none d-md-block">
                <?php if($b['title']): ?><h2 class="fw-bold text-light"><?= htmlspecialchars($b['title']) ?></h2><?php endif; ?>
                <?php if($b['subtitle']): ?><p class="fs-5 text-light"><?= htmlspecialchars($b['subtitle']) ?></p><?php endif; ?>
                <?php if($b['btn_text']): ?><a href="<?= htmlspecialchars($b['btn_link']) ?>" class="btn btn-warning fw-bold mt-2 shadow"><?= htmlspecialchars($b['btn_text']) ?></a><?php endif; ?>
            </div>
        </div>
        <?php $i++; endwhile; ?>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#storeCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#storeCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
</div>
<?php endif; ?>

<div class="row">
    <!-- SIDEBAR -->
    <div class="col-md-3 mb-4">
        <div class="list-group shadow-sm mb-3 text-uppercase">
            <div class="list-group-item bg-dark text-white fw-bold small"><i class="fa-solid fa-server me-2"></i> Servidores</div>
            <?php foreach($servers as $srv): $isActive = ($srv == $activeServer) ? 'active fw-bold bg-primary text-white' : ''; ?>
            <a href="?server=<?= urlencode($srv) ?>" class="list-group-item list-group-item-action <?= $isActive ?> small">
                <i class="fa-solid fa-<?= ($srv == 'Global') ? 'globe' : 'server' ?> me-2"></i> <?= htmlspecialchars($srv) ?>
            </a>
            <?php endforeach; ?>
        </div>
        
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold border-0 small">META MENSAL</div>
            <div class="card-body pt-0 text-center">
                <div class="progress mb-2" style="height: 12px; border-radius: 10px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: <?= $larguraBarra ?>%"></div>
                </div>
                <small class="fw-bold text-muted"><?= $porcentagemMeta ?>% (R$ <?= number_format($arrecadado,2,',','.') ?>)</small>
                <div class="mt-1">
                    <small class="text-muted">Meta: R$ <?= number_format($metaValor,2,',','.') ?></small>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3 bg-warning bg-gradient text-dark">
            <div class="card-body text-center">
                <div class="badge bg-dark text-warning mb-2 small">MAGNATA DO MÊS</div>
                <?php if($topMensal): ?>
                    <img src="https://cravatar.eu/helmavatar/<?= htmlspecialchars($topMensal['player']) ?>/64.png" class="rounded-circle border border-2 border-white mb-2 shadow-sm" width="54" onerror="this.src='https://cravatar.eu/helmavatar/Steve/64.png'">
                    <h6 class="fw-bold mb-0 small text-uppercase"><?= htmlspecialchars($topMensal['player']) ?></h6>
                    <small class="text-muted">R$ <?= number_format($topMensal['total'],2,',','.') ?></small>
                <?php else: ?>
                    <p class="small mb-0">Sem dados ainda</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold border-0 small text-uppercase">Últimas Compras</div>
            <ul class="list-group list-group-flush small">
                <?php 
                $recents = $conn->query("SELECT player, product_name, created_at FROM rs_sales WHERE status='APPROVED' ORDER BY approved_at DESC LIMIT 5");
                if ($recents && $recents->num_rows > 0):
                    while($r = $recents->fetch_assoc()): 
                        $timeAgo = time_elapsed_string($r['created_at']);
                ?>
                <li class="list-group-item border-0 py-2 d-flex align-items-center">
                    <img src="https://cravatar.eu/helmavatar/<?= htmlspecialchars($r['player']) ?>/24.png" class="me-2 rounded" width="20" onerror="this.src='https://cravatar.eu/helmavatar/Steve/24.png'">
                    <div class="text-truncate">
                        <b><?= htmlspecialchars($r['player']) ?></b><br>
                        <span class="text-muted small"><?= htmlspecialchars($r['product_name']) ?></span><br>
                        <small class="text-muted"><?= $timeAgo ?></small>
                    </div>
                </li>
                <?php 
                    endwhile;
                else:
                ?>
                <li class="list-group-item border-0 py-2 text-center text-muted small">Nenhuma compra recente</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="card border-0 shadow-sm bg-primary text-white text-center p-3">
            <i class="fa-brands fa-discord fa-2x mb-2"></i>
            <h6 class="fw-bold small mb-2 text-uppercase">Nosso Discord</h6>
            <button class="btn btn-light btn-sm fw-bold rounded-pill" onclick="window.open('https://discord.gg/seuservidor', '_blank')">ENTRAR NO DISCORD</button>
        </div>
    </div>

    <!-- CONTEÚDO PRINCIPAL -->
    <div class="col-md-9">
        <div class="position-relative mb-4">
            <input type="text" id="productSearch" class="form-control form-control-lg border-0 shadow-sm rounded-pill ps-5" 
                   placeholder="O que você procura? (Ex: VIP, Coins, Chaves...)" style="background-color: #f8f9fa;">
            <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-4 text-muted"></i>
        </div>

        <?php if(empty($products[$activeServer])): ?>
            <div class="alert alert-warning shadow-sm border-0 text-center py-4">
                <i class="fa-solid fa-box-open fa-3x text-muted mb-3"></i>
                <h5 class="fw-bold">Nenhum produto disponível neste servidor</h5>
                <p class="text-muted">Tente selecionar outro servidor ou volte mais tarde.</p>
            </div>
        <?php else: ?>
            <div class="d-flex flex-wrap gap-2 mb-4" id="categoryTabs">
                <?php $first = true; foreach($products[$activeServer] as $catName => $items): $catId = 'cat-' . preg_replace('/[^a-zA-Z0-9]/', '', $catName); ?>
                <button type="button" class="btn btn-outline-dark fw-bold px-4 py-2 rounded-pill category-tab <?= $first ? 'active' : '' ?>" 
                        data-category="<?= $catId ?>" onclick="showCategory('<?= $catId ?>')">
                    <?= htmlspecialchars($catName) ?> <span class="badge bg-primary ms-1"><?= count($items) ?></span>
                </button>
                <?php $first = false; endforeach; ?>
            </div>

            <div id="categoryContents">
                <?php $first = true; foreach($products[$activeServer] as $catName => $items): $catId = 'cat-' . preg_replace('/[^a-zA-Z0-9]/', '', $catName); ?>
                <div class="category-content <?= $first ? '' : 'd-none' ?>" id="content-<?= $catId ?>">
                    <div class="row" id="products-<?= $catId ?>">
                        <?php foreach($items as $p): 
                            $iconColor = $p['icon_color'] ?? '#0d6efd';
                            $curPrice = (float)$p['price'];
                            $oldPrice = (float)($p['old_price'] ?? 0);
                            $stock = $p['stock_qty'];
                            $isSoldOut = ($stock !== null && $stock <= 0);
                            $hasDiscount = ($oldPrice > 0 && $oldPrice > $curPrice);
                            
                            $promoEnds = $p['promo_ends_at'] ?? null;
                            $secondsLeft = 0;
                            if ($promoEnds) {
                                $now = new DateTime();
                                $end = new DateTime($promoEnds);
                                if ($end > $now) { $secondsLeft = $end->getTimestamp() - $now->getTimestamp(); }
                            }
                        ?>
                        <div class="col-md-4 mb-4 product-item" data-product-name="<?= strtolower(htmlspecialchars($p['name'])) ?>">
                            <div class="card h-100 shadow-sm border-0 hover-effect position-relative product-card">
                                <?php if($secondsLeft > 0): ?>
                                    <div class="flash-sale-badge" data-seconds="<?= $secondsLeft ?>"><i class="fa-solid fa-clock me-1"></i> <span class="timer-text">...</span></div>
                                <?php endif; ?>

                                <?php if($isSoldOut): ?>
                                    <div class="sold-out-overlay"><span class="sold-out-text">ESGOTADO</span></div>
                                <?php elseif($stock !== null && $stock <= 5 && $stock > 0): ?>
                                    <span class="position-absolute top-0 start-50 translate-middle badge rounded-pill bg-danger" style="z-index: 5; margin-top: -8px;">RESTAM <?= $stock ?></span>
                                <?php endif; ?>
                                
                                <div class="card-body text-center pt-4">
                                    <div class="mb-3 p-4 rounded-circle bg-light d-inline-block">
                                        <i class="fa-solid <?= htmlspecialchars($p['icon']) ?> fa-3x" style="color: <?= htmlspecialchars($iconColor) ?>;"></i>
                                    </div>
                                    <h6 class="fw-bold text-uppercase"><?= htmlspecialchars($p['name']) ?></h6>
                                    
                                    <?php if($hasDiscount): ?>
                                        <small class="text-decoration-line-through text-muted d-block">R$ <?= number_format($oldPrice, 2, ',', '.') ?></small>
                                        <h4 class="text-success fw-bold">R$ <?= number_format($curPrice, 2, ',', '.') ?></h4>
                                    <?php else: ?>
                                        <h4 class="text-success fw-bold">R$ <?= number_format($curPrice, 2, ',', '.') ?></h4>
                                    <?php endif; ?>
                                    
                                    <?php if(!empty($p['description'])): ?>
                                        <p class="text-muted small mb-3"><?= htmlspecialchars($p['description']) ?></p>
                                    <?php endif; ?>
                                    
                                    <!-- AVALIAÇÕES -->
                                    <?php if($p['total_reviews'] > 0): ?>
                                        <div class="mb-3">
                                            <div class="star-rating star-rating-small readonly">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fa-solid fa-star <?= $i <= round($p['avg_rating']) ? 'active' : '' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted ms-2">
                                                <strong><?= number_format($p['avg_rating'], 1) ?></strong> 
                                                (<?= $p['total_reviews'] ?> <?= $p['total_reviews'] == 1 ? 'avaliação' : 'avaliações' ?>)
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fa-solid fa-star-half-stroke"></i> Seja o primeiro a avaliar!
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex gap-1 justify-content-center mt-2">
                                        <button class="btn btn-dark fw-bold rounded-pill btn-sm flex-grow-1" 
                                                <?= $isSoldOut ? 'disabled' : '' ?> 
                                                data-bs-toggle="modal" data-bs-target="#buyModal<?= $p['id'] ?>">
                                            <?= $isSoldOut ? 'ESGOTADO' : 'COMPRAR' ?>
                                        </button>
                                        <button class="btn btn-outline-warning rounded-circle btn-sm" 
                                                <?= $isSoldOut ? 'disabled' : '' ?> 
                                                onclick="addToCart(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', <?= $curPrice ?>, '<?= addslashes($p['icon']) ?>', '<?= addslashes($iconColor) ?>')">
                                            <i class="fa-solid fa-cart-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- MODAL DE COMPRA -->
                        <div class="modal fade" id="buyModal<?= $p['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0">
                                    <form method="POST" onsubmit="return validatePurchaseForm(<?= $p['id'] ?>)">
                                        <input type="hidden" name="buy_product" value="1">
                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                        <div class="modal-header bg-light border-0">
                                            <h6 class="modal-title fw-bold">CHECKOUT</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <!-- INFORMAÇÃO DE UPGRADE -->
                                            <div id="upgradeInfo<?= $p['id'] ?>" style="display: none;" class="mb-3"></div>
                                            
                                            <?php if($isAdmin): ?>
                                                <div class="admin-test-box p-3 mb-3 small fw-bold rounded">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="admin_test_mode" id="adminTest<?= $p['id'] ?>" value="1" 
                                                               onchange="toggleAdminTest(<?= $p['id'] ?>, <?= $curPrice ?>)">
                                                        <label class="form-check-label" for="adminTest<?= $p['id'] ?>">
                                                            <i class="fa-solid fa-flask text-danger"></i> MODO TESTE (ADMIN)
                                                        </label>
                                                    </div>
                                                    <div class="text-muted small mt-1" id="adminTestNote<?= $p['id'] ?>" style="display: none;">* O produto será entregue imediatamente sem custo</div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex align-items-center mb-3 p-3 border rounded">
                                                <i class="fa-solid <?= htmlspecialchars($p['icon']) ?> fa-2x me-3" style="color: <?= htmlspecialchars($iconColor) ?>;"></i>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0 fw-bold small text-uppercase"><?= htmlspecialchars($p['name']) ?></h6>
                                                    <span class="text-success fw-bold small">R$ <?= number_format($curPrice, 2, ',', '.') ?></span>
                                                    <?php if($hasDiscount): ?><br><small class="text-muted">De: R$ <?= number_format($oldPrice, 2, ',', '.') ?></small><?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if(isset($bundleMap[$p['id']])): ?>
                                                <div class="bg-light p-3 rounded mb-3 border">
                                                    <small class="fw-bold text-uppercase text-muted d-block mb-2"><i class="fa-solid fa-box-open me-1"></i> 📦 ESTE PACOTE CONTÉM:</small>
                                                    <ul class="list-unstyled mb-0 small">
                                                        <?php foreach($bundleMap[$p['id']] as $itemName): ?>
                                                            <li class="mb-1"><i class="fa-solid fa-check text-success me-1"></i> <?= htmlspecialchars($itemName) ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>

                                            <?php if(!empty($p['upsell_product_id']) && isset($allProductsMap[$p['upsell_product_id']])): 
                                                $upsell = $allProductsMap[$p['upsell_product_id']]; 
                                                $upsellStock = $upsell['stock_qty']; 
                                                $upsellSoldOut = ($upsellStock !== null && $upsellStock <= 0);
                                            ?>
                                                <div class="order-bump-box p-3 mb-3 small <?= $upsellSoldOut ? 'opacity-50' : '' ?>">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="add_upsell" id="upsell<?= $p['id'] ?>" value="1" <?= $upsellSoldOut ? 'disabled' : '' ?> onchange="updateTotal(<?= $p['id'] ?>, <?= $curPrice ?>, <?= (float)$upsell['price'] ?>)">
                                                        <label class="form-check-label fw-bold">
                                                            <i class="fa-solid fa-plus-circle text-danger"></i> LEVE TAMBÉM: <?= htmlspecialchars($upsell['name']) ?> (+R$ <?= number_format($upsell['price'],2,',','.') ?>)
                                                            <?php if($upsellSoldOut): ?><span class="badge bg-danger ms-1">ESGOTADO</span><?php endif; ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <div class="gift-box p-3 mb-3 small rounded">
                                                <div class="form-check form-switch mb-2">
                                                    <input class="form-check-input" type="checkbox" name="is_gift" id="giftToggle<?= $p['id'] ?>" onchange="toggleGift(<?= $p['id'] ?>)">
                                                    <label class="form-check-label fw-bold" for="giftToggle<?= $p['id'] ?>"><i class="fa-solid fa-gift text-danger"></i> Presentear um amigo?</label>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="fw-bold small">NICK DO RECEBEDOR <span class="text-danger">*</span></label>
                                                    <input type="text" name="nick" id="nick<?= $p['id'] ?>" class="form-control" placeholder="Nick In-game (ex: Player123)" required 
                                                           onblur="validateNick(<?= $p['id'] ?>, this.value); logAbandoned(this.value, '<?= addslashes($p['name']) ?>')">
                                                    <div id="nickError<?= $p['id'] ?>" class="text-danger small mt-1 d-none"></div>
                                                </div>
                                                <div id="gifter-container-<?= $p['id'] ?>" style="display: none;">
                                                    <label class="fw-bold small">SEU NICK (QUEM PAGA)</label>
                                                    <input type="text" name="gifter_nick" id="gifterNick<?= $p['id'] ?>" class="form-control" placeholder="Seu nick (ex: SeuNick)" onblur="validateGifterNick(<?= $p['id'] ?>, this.value)">
                                                    <div id="gifterError<?= $p['id'] ?>" class="text-danger small mt-1 d-none"></div>
                                                </div>
                                            </div>

                                            <div class="mb-3 small">
                                                <label class="fw-bold">CUPOM DE DESCONTO</label>
                                                <div class="input-group">
                                                    <input type="text" name="coupon" id="coupon-input-<?= $p['id'] ?>" class="form-control" placeholder="Digite o cupom">
                                                    <button type="button" class="btn btn-outline-dark" onclick="verificarCupom(<?= $p['id'] ?>, <?= $curPrice ?>)">APLICAR</button>
                                                </div>
                                                <div id="coupon-feedback-<?= $p['id'] ?>" class="fw-bold mt-1"></div>
                                            </div>

                                            <button type="submit" class="btn btn-success w-100 fw-bold rounded-pill py-2">
                                                <i class="fa-solid fa-lock me-2"></i> FINALIZAR COMPRA <span id="btn-total-<?= $p['id'] ?>">R$ <?= number_format($curPrice, 2, ',', '.') ?></span>
                                            </button>
                                            
                                            <div class="text-center mt-3"><small class="text-muted"><i class="fa-solid fa-shield-alt me-1"></i> Compra 100% segura • Entrega automática</small></div>
                                        </div>
                                        
                                        <!-- ABA DE AVALIAÇÕES -->
                                        <div class="modal-footer border-top-0 flex-column">
                                            <button type="button" class="btn btn-link text-decoration-none w-100 fw-bold" data-bs-toggle="collapse" data-bs-target="#reviews<?= $p['id'] ?>" onclick="loadReviews(<?= $p['id'] ?>)">
                                                <i class="fa-solid fa-star text-warning me-2"></i>
                                                Ver Avaliações (<?= $p['total_reviews'] ?>)
                                                <i class="fa-solid fa-chevron-down ms-2"></i>
                                            </button>
                                            
                                            <div class="collapse w-100" id="reviews<?= $p['id'] ?>">
                                                <div class="card card-body border-0 bg-light">
                                                    <div id="reviewsContainer<?= $p['id'] ?>">
                                                        <div class="text-center py-4">
                                                            <div class="spinner-border text-primary" role="status">
                                                                <span class="visually-hidden">Carregando...</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php $first = false; endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SEÇÃO FAQ -->
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-2">
                    <i class="fa-solid fa-circle-question text-primary me-2"></i>
                    Perguntas Frequentes
                </h2>
                <p class="text-muted">Tire suas dúvidas antes de comprar. Ainda com dúvidas? Entre no nosso Discord!</p>
            </div>
            
            <div class="accordion accordion-flush" id="faqAccordion">
                
                <!-- FAQ 1 -->
                <div class="accordion-item border rounded mb-3 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            <i class="fa-solid fa-clock text-primary me-3"></i>
                            Quanto tempo demora para receber meu produto?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p class="mb-2">⚡ <strong>Entrega Automática:</strong> A maioria dos produtos é entregue <strong>instantaneamente</strong> após a confirmação do pagamento!</p>
                            <ul class="mb-0">
                                <li><strong>PIX:</strong> Aprovação em até 2 minutos</li>
                                <li><strong>Cartão de Crédito:</strong> Aprovação instantânea</li>
                                <li><strong>Boleto:</strong> Aprovação em até 2 dias úteis</li>
                            </ul>
                            <div class="alert alert-info mt-3 mb-0 small">
                                <i class="fa-solid fa-lightbulb me-2"></i>
                                <strong>Dica:</strong> Você deve estar <strong>ONLINE no servidor</strong> para receber itens automaticamente!
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 2 -->
                <div class="accordion-item border rounded mb-3 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            <i class="fa-solid fa-credit-card text-success me-3"></i>
                            Quais formas de pagamento são aceitas?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <i class="fa-brands fa-pix fa-2x text-success mb-2"></i>
                                        <h6 class="fw-bold mb-1">PIX</h6>
                                        <small class="text-muted">Aprovação em segundos</small>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <i class="fa-solid fa-credit-card fa-2x text-primary mb-2"></i>
                                        <h6 class="fw-bold mb-1">Cartão de Crédito</h6>
                                        <small class="text-muted">Parcelamento disponível</small>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-3 bg-light rounded">
                                        <i class="fa-solid fa-barcode fa-2x text-warning mb-2"></i>
                                        <h6 class="fw-bold mb-1">Boleto</h6>
                                        <small class="text-muted">Compensação em 1-2 dias</small>
                                    </div>
                                </div>
                            </div>
                            <p class="text-center mb-0 mt-2">
                                <i class="fa-solid fa-shield-halved text-success me-2"></i>
                                Todos os pagamentos são processados de forma <strong>100% segura</strong> por gateway certificado.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- FAQ 3 -->
                <div class="accordion-item border rounded mb-3 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            <i class="fa-solid fa-toggle-on text-warning me-3"></i>
                            Como ativo meu VIP ou produto comprado?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-list-ol me-2"></i>Passo a passo:</h6>
                            <ol class="mb-3">
                                <li class="mb-2"><strong>Aguarde o pagamento ser aprovado</strong> (você receberá uma notificação)</li>
                                <li class="mb-2"><strong>Entre no servidor Minecraft</strong> com o nick cadastrado na compra</li>
                                <li class="mb-2"><strong>O sistema entregará automaticamente</strong> - você receberá uma mensagem no chat</li>
                                <li class="mb-2"><strong>Se for VIP:</strong> Saia e entre novamente para ativar as permissões</li>
                            </ol>
                            <div class="alert alert-warning mb-0 small">
                                <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                <strong>Importante:</strong> Certifique-se de ter digitado o <strong>nick correto</strong> na hora da compra!
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 4 -->
                <div class="accordion-item border rounded mb-3 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            <i class="fa-solid fa-gift text-danger me-3"></i>
                            Posso presentear um amigo?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p class="mb-3">🎁 <strong>Sim!</strong> Você pode presentear qualquer jogador do servidor!</p>
                            <h6 class="fw-bold mb-2">Como funciona:</h6>
                            <ul class="mb-3">
                                <li>Na hora da compra, marque a opção <strong>"Presentear um amigo?"</strong></li>
                                <li>Digite o <strong>nick do amigo</strong> que vai receber o presente</li>
                                <li>Digite o <strong>seu nick</strong> (quem está pagando)</li>
                                <li>Seu amigo receberá uma mensagem especial no jogo: <em>"🎁 Presente de [SeuNick]"</em></li>
                            </ul>
                            <div class="bg-light p-3 rounded border border-danger">
                                <p class="mb-0 small text-center">
                                    <i class="fa-solid fa-heart text-danger me-2"></i>
                                    É uma ótima forma de surpreender seus amigos e fortalecer a amizade!
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 5 -->
                <div class="accordion-item border rounded mb-3 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                            <i class="fa-solid fa-rotate-left text-info me-3"></i>
                            Posso cancelar ou reembolsar minha compra?
                        </button>
                    </h2>
                    <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p class="mb-3">De acordo com nossa política de reembolso:</p>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="border border-success rounded p-3 h-100">
                                        <h6 class="text-success fw-bold mb-2">
                                            <i class="fa-solid fa-check-circle me-2"></i>Pode Reembolsar
                                        </h6>
                                        <ul class="small mb-0">
                                            <li>Pagamento aprovado mas item não foi entregue</li>
                                            <li>Erro no produto entregue</li>
                                            <li>Duplicação de pagamento</li>
                                            <li>Problema técnico comprovado</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="border border-danger rounded p-3 h-100">
                                        <h6 class="text-danger fw-bold mb-2">
                                            <i class="fa-solid fa-times-circle me-2"></i>Não Reembolsa
                                        </h6>
                                        <ul class="small mb-0">
                                            <li>Produto já foi entregue e usado</li>
                                            <li>Arrependimento após receber</li>
                                            <li>Nick digitado incorretamente</li>
                                            <li>Ban do servidor após compra</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info mb-0 small mt-3">
                                <i class="fa-solid fa-headset me-2"></i>
                                Para solicitar reembolso, abra um ticket no Discord com o ID do pedido e motivo.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 6 -->
                <div class="accordion-item border rounded mb-3 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                            <i class="fa-solid fa-ticket text-purple me-3"></i>
                            Como uso um cupom de desconto?
                        </button>
                    </h2>
                    <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <h6 class="fw-bold mb-3">Existem 3 formas de usar cupons:</h6>
                            
                            <div class="mb-3">
                                <strong>1️⃣ Na Compra Individual:</strong>
                                <p class="mb-1 small">No modal de checkout, digite o cupom no campo "Cupom de Desconto" e clique em "Aplicar"</p>
                            </div>
                            
                            <div class="mb-3">
                                <strong>2️⃣ No Carrinho:</strong>
                                <p class="mb-1 small">Adicione itens ao carrinho, abra-o e aplique o cupom antes de finalizar</p>
                            </div>
                            
                            <div class="mb-3">
                                <strong>3️⃣ Pela Roleta Diária:</strong>
                                <p class="mb-1 small">Gire a roleta todo dia e ganhe cupons exclusivos!</p>
                            </div>
                            
                            <div class="bg-warning bg-opacity-10 border border-warning rounded p-3">
                                <h6 class="text-warning fw-bold mb-2">
                                    <i class="fa-solid fa-star me-2"></i>Onde conseguir cupons?
                                </h6>
                                <ul class="small mb-0">
                                    <li><strong>Discord:</strong> Anúncios e eventos especiais</li>
                                    <li><strong>Roleta:</strong> Gire diariamente na loja</li>
                                    <li><strong>Promoções:</strong> Fique de olho nas redes sociais</li>
                                    <li><strong>Indicação:</strong> Indique amigos e ganhe cupons</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 7 -->
                <div class="accordion-item border rounded mb-3 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                            <i class="fa-solid fa-box text-secondary me-3"></i>
                            O que tem dentro dos pacotes/bundles?
                        </button>
                    </h2>
                    <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p class="mb-3">Todos os pacotes mostram <strong>exatamente o que você vai receber</strong> antes de comprar!</p>
                            <h6 class="fw-bold mb-2">Como ver o conteúdo:</h6>
                            <ol class="mb-3">
                                <li>Clique no botão "COMPRAR" do produto</li>
                                <li>Se for um pacote, aparecerá uma caixa com o ícone 📦</li>
                                <li>Lá estarão listados todos os itens inclusos</li>
                            </ol>
                            <div class="alert alert-success mb-0 small">
                                <i class="fa-solid fa-check-double me-2"></i>
                                <strong>Transparência total!</strong> Você sempre saberá exatamente o que está comprando.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FAQ 8 -->
                <div class="accordion-item border rounded mb-3 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                            <i class="fa-solid fa-headset text-primary me-3"></i>
                            Não encontrei minha dúvida. Como entro em contato?
                        </button>
                    </h2>
                    <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p class="mb-3">Estamos aqui para ajudar! Escolha o canal que preferir:</p>
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <div class="p-4 bg-light rounded h-100">
                                        <i class="fa-brands fa-discord fa-3x text-primary mb-3"></i>
                                        <h6 class="fw-bold mb-2">Discord</h6>
                                        <p class="small text-muted mb-3">Abra um ticket no canal #suporte</p>
                                        <button class="btn btn-primary btn-sm" onclick="window.open('https://discord.gg/seuservidor', '_blank')">
                                            Abrir Discord
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-4 bg-light rounded h-100">
                                        <i class="fa-solid fa-envelope fa-3x text-success mb-3"></i>
                                        <h6 class="fw-bold mb-2">E-mail</h6>
                                        <p class="small text-muted mb-3">Resposta em até 24h úteis</p>
                                        <a href="mailto:suporte@seuservidor.com" class="btn btn-success btn-sm">
                                            Enviar E-mail
                                        </a>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="p-4 bg-light rounded h-100">
                                        <i class="fa-brands fa-whatsapp fa-3x text-success mb-3"></i>
                                        <h6 class="fw-bold mb-2">WhatsApp</h6>
                                        <p class="small text-muted mb-3">Atendimento rápido</p>
                                        <button class="btn btn-success btn-sm" onclick="window.open('https://wa.me/5511999999999', '_blank')">
                                            Chamar no WhatsApp
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fa-solid fa-clock me-1"></i>
                                    Horário de atendimento: Segunda a Sexta, 9h às 18h
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- CTA Final -->
            <div class="text-center mt-5 p-4 bg-primary bg-opacity-10 rounded-4 border border-primary">
                <h5 class="fw-bold text-primary mb-2">Ainda tem dúvidas?</h5>
                <p class="text-muted mb-3">Nossa equipe está sempre disponível para ajudar você!</p>
                <button class="btn btn-primary btn-lg fw-bold shadow-sm" onclick="window.open('https://discord.gg/seuservidor', '_blank')">
                    <i class="fa-brands fa-discord me-2"></i> Falar com Suporte
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ROLETA -->
<div class="modal fade" id="rouletteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-star"></i> Sorteio Diário</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <h6 class="text-muted mb-3">Gire a roleta e ganhe descontos exclusivos!</h6>
                <div id="canvasContainer">
                    <div class="roulette-arrow"></div>
                    <canvas id="rouletteCanvas" width="300" height="300"></canvas>
                </div>
                <div id="rouletteResult" class="mt-4 d-none">
                    <h4 class="fw-bold" id="resultTitle"></h4>
                    <p id="resultMsg"></p>
                    <div id="couponResult" class="bg-light p-2 rounded border border-warning d-none">
                        <code class="fs-5 text-dark fw-bold" id="couponCode"></code>
                    </div>
                </div>
                <button class="btn btn-warning fw-bold w-100 mt-4 rounded-pill py-2 shadow-sm" id="btnSpin" onclick="spinWheel()">GIRAR AGORA</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CARRINHO -->
<div class="modal fade" id="cartModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-cart-shopping"></i> Seu Carrinho</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="cartEmptyState" class="text-center py-5 d-none">
                    <i class="fa-solid fa-cart-arrow-down fa-3x text-muted mb-3"></i>
                    <h5>Seu carrinho está vazio.</h5>
                    <button class="btn btn-outline-dark mt-2" data-bs-dismiss="modal">Continuar Comprando</button>
                </div>
                <div id="cartContent">
                    <div class="table-responsive mb-4">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Produto</th>
                                    <th>Preço</th>
                                    <th class="text-end">Ação</th>
                                </tr>
                            </thead>
                            <tbody id="cartTableBody"></tbody>
                        </table>
                    </div>
                    <form method="POST" id="checkoutForm">
                        <input type="hidden" name="cart_checkout" value="1">
                        <input type="hidden" name="cart_data" id="cartDataInput">
                        <div class="row">
                            <div class="col-md-6">
                                <?php if($isAdmin): ?>
                                <div class="alert alert-danger py-2 small fw-bold">
                                    <input type="checkbox" name="admin_test_mode" value="1" id="cartAdminTest" onchange="updateCartTotal()"> 
                                    <label for="cartAdminTest">Modo Teste (Grátis)</label>
                                </div>
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label class="fw-bold small">Nick do Jogador <span class="text-danger">*</span></label>
                                    <input type="text" name="nick" class="form-control" placeholder="Nick in-game" required onblur="if(cart.length>0) logAbandoned(this.value, 'Carrinho ('+cart.length+' itens)')">
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="is_gift" id="cartGiftToggle" onchange="document.getElementById('cartGifterDiv').style.display = this.checked ? 'block' : 'none'">
                                    <label class="form-check-label small fw-bold">É um presente?</label>
                                </div>
                                <div class="mb-3" id="cartGifterDiv" style="display:none;">
                                    <input type="text" name="gifter_nick" class="form-control form-control-sm" placeholder="Seu Nick">
                                </div>
                            </div>
                            <div class="col-md-6 bg-light p-3 rounded">
                                <label class="fw-bold small">Cupom de Desconto</label>
                                <div class="input-group mb-2">
                                    <input type="text" id="cartCouponInput" name="coupon" class="form-control form-control-sm" placeholder="Código">
                                    <button type="button" class="btn btn-dark btn-sm" onclick="applyCartCoupon()">Aplicar</button>
                                </div>
                                <div id="cartCouponMsg" class="small fw-bold mb-2"></div>
                                <div class="d-flex justify-content-between border-top pt-2">
                                    <span>Subtotal:</span>
                                    <span class="fw-bold" id="cartSubtotal">R$ 0,00</span>
                                </div>
                                <div class="d-flex justify-content-between text-success">
                                    <span>Desconto:</span>
                                    <span class="fw-bold" id="cartDiscount">- R$ 0,00</span>
                                </div>
                                <div class="d-flex justify-content-between fs-5 fw-bold mt-2">
                                    <span>TOTAL:</span>
                                    <span class="text-primary" id="cartFinalTotal">R$ 0,00</span>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-bold py-3 mt-3 rounded-pill shadow-sm">
                            <i class="fa-solid fa-check-circle me-2"></i> FINALIZAR COMPRA
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// --- COPIAR LINK AFILIADO ---
function copyAffLink() {
    var copyText = document.getElementById("affLink"); 
    copyText.select(); 
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Copiado!', showConfirmButton: false, timer: 1500 });
}

// --- LOG CARRINHO ABANDONADO ---
function logAbandoned(nick, product) {
    if (nick.length < 3) return;
    const formData = new FormData();
    formData.append('log_abandoned', '1');
    formData.append('player', nick);
    formData.append('product', product);
    fetch('loja.php', { method: 'POST', body: formData }).catch(err => console.log(err));
}

// --- VALIDAÇÃO DE NICK ---
function validateNick(productId, nick) {
    const errorDiv = document.getElementById('nickError' + productId);
    if (nick.length < 3 || nick.length > 16 || !/^[a-zA-Z0-9_]+$/.test(nick)) {
        errorDiv.textContent = 'Nick inválido (3-16 caracteres, apenas letras, números e _)';
        errorDiv.classList.remove('d-none');
        return false;
    }
    errorDiv.classList.add('d-none');
    
    // Verifica upgrade disponível
    checkUpgrade(productId, nick);
    
    return true;
}

// --- VERIFICAR UPGRADE DISPONÍVEL ---
function checkUpgrade(productId, playerName) {
    const upgradeContainer = document.getElementById('upgradeInfo' + productId);
    if (!upgradeContainer || !playerName || playerName.length < 3) return;
    
    const formData = new FormData();
    formData.append('check_upgrade', '1');
    formData.append('product_id', productId);
    formData.append('player_name', playerName);
    
    fetch('loja.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.has_upgrade) {
            const info = data.upgrade_info;
            upgradeContainer.innerHTML = `
                <div class="upgrade-box text-center">
                    <div class="upgrade-badge">
                        <i class="fa-solid fa-arrow-up me-2"></i>UPGRADE DISPONÍVEL!
                    </div>
                    <h6 class="mb-3 fw-bold">
                        Você já possui: <span class="badge bg-white text-primary">${info.current_vip}</span>
                    </h6>
                    <p class="mb-3 small">
                        Ao fazer upgrade para <strong>${info.target_vip}</strong>, você paga apenas a diferença!
                    </p>
                    <div class="price-comparison">
                        <div class="price-old">R$ ${info.original_price.toFixed(2).replace('.', ',')}</div>
                        <i class="fa-solid fa-arrow-right fa-lg"></i>
                        <div class="price-new">R$ ${info.upgrade_price.toFixed(2).replace('.', ',')}</div>
                    </div>
                    <div class="savings-tag">
                        <i class="fa-solid fa-piggy-bank me-1"></i>
                        Economia de ${info.savings_percent}% (R$ ${info.discount.toFixed(2).replace('.', ',')})
                    </div>
                </div>
            `;
            upgradeContainer.style.display = 'block';
            
            // Atualiza o botão de pagamento
            const btnTotal = document.getElementById('btn-total-' + productId);
            if (btnTotal) {
                btnTotal.innerHTML = 'R$ ' + info.upgrade_price.toFixed(2).replace('.', ',');
            }
        } else if (data.upgrade_info && data.upgrade_info.is_same) {
            upgradeContainer.innerHTML = `
                <div class="alert alert-warning mb-3">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    <strong>Você já possui este VIP ativo!</strong><br>
                    <small>Esta compra renovará seu VIP atual.</small>
                </div>
            `;
            upgradeContainer.style.display = 'block';
        } else if (data.upgrade_info && data.upgrade_info.is_downgrade) {
            upgradeContainer.innerHTML = `
                <div class="alert alert-info mb-3">
                    <i class="fa-solid fa-crown me-2"></i>
                    <strong>Você já possui ${data.upgrade_info.current_vip}</strong><br>
                    <small>Este seria um downgrade. Tem certeza?</small>
                </div>
            `;
            upgradeContainer.style.display = 'block';
        } else {
            upgradeContainer.style.display = 'none';
        }
    })
    .catch(err => {
        console.error('Erro ao verificar upgrade:', err);
        upgradeContainer.style.display = 'none';
    });
}

function validateGifterNick(productId, nick) {
    const errorDiv = document.getElementById('gifterError' + productId);
    if (nick && (nick.length < 3 || nick.length > 16 || !/^[a-zA-Z0-9_]+$/.test(nick))) {
        errorDiv.textContent = 'Nick inválido';
        errorDiv.classList.remove('d-none');
        return false;
    }
    errorDiv.classList.add('d-none');
    return true;
}

function validatePurchaseForm(productId) {
    const nick = document.getElementById('nick' + productId).value;
    if (!validateNick(productId, nick)) {
        Swal.fire('Erro', 'Nick inválido', 'error');
        return false;
    }
    return true;
}

// --- TOGGLE GIFT ---
function toggleGift(id) {
    const isChecked = event.target.checked;
    document.getElementById('gifter-container-' + id).style.display = isChecked ? 'block' : 'none';
}

// --- TOGGLE ADMIN TEST ---
function toggleAdminTest(id, basePrice) {
    const isChecked = document.getElementById('adminTest' + id).checked;
    document.getElementById('adminTestNote' + id).style.display = isChecked ? 'block' : 'none';
    document.getElementById('btn-total-' + id).innerText = isChecked ? 'R$ 0,00' : 'R$ ' + basePrice.toFixed(2).replace('.', ',');
}

// --- UPDATE TOTAL (UPSELL) ---
function updateTotal(id, mainPrice, upsellPrice) {
    const isChecked = event.target.checked;
    const isTest = document.getElementById('adminTest' + id) && document.getElementById('adminTest' + id).checked;
    const finalPrice = isTest ? 0 : (isChecked ? (mainPrice + upsellPrice) : mainPrice);
    document.getElementById('btn-total-' + id).innerText = 'R$ ' + finalPrice.toFixed(2).replace('.', ',');
}

// --- VERIFICAR CUPOM ---
function verificarCupom(productId, originalPrice) {
    const code = document.getElementById('coupon-input-' + productId).value;
    const feedback = document.getElementById('coupon-feedback-' + productId);
    if (!code) return;
    
    const formData = new FormData();
    formData.append('check_coupon', '1');
    formData.append('code', code);
    
    fetch('loja.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.valid) {
            feedback.className = 'text-success fw-bold mt-1 small';
            feedback.innerText = data.percent + '% aplicado!';
            const discountedPrice = originalPrice * (1 - data.percent / 100);
            document.getElementById('btn-total-' + productId).innerText = 'R$ ' + discountedPrice.toFixed(2).replace('.', ',');
        } else {
            feedback.className = 'text-danger fw-bold mt-1 small';
            feedback.innerText = data.msg;
        }
    })
    .catch(err => {
        feedback.className = 'text-danger fw-bold mt-1 small';
        feedback.innerText = 'Erro ao verificar cupom';
    });
}

// --- ROLETA ---
const rouletteItems = <?= json_encode($rouletteItems) ?>;
const canvas = document.getElementById('rouletteCanvas');
const ctx = canvas ? canvas.getContext('2d') : null;
const centerX = canvas ? canvas.width / 2 : 0;
const centerY = canvas ? canvas.height / 2 : 0;
const radius = canvas ? canvas.width / 2 : 0;

function drawWheel() {
    if (!ctx || rouletteItems.length === 0) return;
    const sliceAngle = (2 * Math.PI) / rouletteItems.length;
    
    rouletteItems.forEach((item, i) => {
        const startAngle = i * sliceAngle - (Math.PI / 2);
        const endAngle = startAngle + sliceAngle;
        
        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.arc(centerX, centerY, radius, startAngle, endAngle);
        ctx.fillStyle = item.color || '#' + Math.floor(Math.random()*16777215).toString(16);
        ctx.fill();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.stroke();
        
        ctx.save();
        ctx.translate(centerX, centerY);
        ctx.rotate(startAngle + sliceAngle / 2);
        ctx.textAlign = "right";
        ctx.fillStyle = "#fff";
        ctx.font = "bold 14px Arial";
        ctx.fillText(item.label, radius - 20, 5);
        ctx.restore();
    });
}

function openRoulette() {
    const modal = new bootstrap.Modal(document.getElementById('rouletteModal'));
    modal.show();
    setTimeout(drawWheel, 200);
}

function spinWheel() {
    const btn = document.getElementById('btnSpin');
    btn.disabled = true;
    btn.innerText = "Girando...";
    
    const formData = new FormData();
    formData.append('spin_roulette', '1');
    
    fetch('loja.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'error') {
            Swal.fire('Ops!', data.msg, 'error');
            btn.disabled = false;
            btn.innerText = "GIRAR AGORA";
            return;
        }
        
        const sliceAngle = 360 / rouletteItems.length;
        const stopAngle = 360 - (data.winner_index * sliceAngle) - (sliceAngle / 2);
        
        if (canvas) {
            canvas.style.transform = `rotate(${1800 + stopAngle}deg)`;
        }
        
        setTimeout(() => {
            document.getElementById('rouletteResult').classList.remove('d-none');
            document.getElementById('resultTitle').innerText = (data.coupon ? "PARABÉNS!" : "POXA!");
            document.getElementById('resultMsg').innerText = data.message;
            
            if (data.coupon) {
                document.getElementById('couponResult').classList.remove('d-none');
                document.getElementById('couponCode').innerText = data.coupon;
                
                Swal.fire({
                    title: 'Cupom Ganho!',
                    text: 'Deseja aplicar este cupom no carrinho agora?',
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: 'Sim',
                    cancelButtonText: 'Não'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('cartCouponInput').value = data.coupon;
                        applyCartCoupon();
                        openCartModal();
                    }
                });
            } else {
                document.getElementById('couponResult').classList.add('d-none');
            }
            
            btn.innerText = "JÁ GIROU HOJE";
        }, 4000);
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Erro', 'Erro ao girar a roleta', 'error');
        btn.disabled = false;
        btn.innerText = "GIRAR AGORA";
    });
}

// --- TIMER PROMOÇÃO FLASH ---
setInterval(function() {
    document.querySelectorAll('.flash-sale-badge').forEach(function(el) {
        let seconds = parseInt(el.getAttribute('data-seconds'));
        if (seconds > 0) {
            seconds--;
            el.setAttribute('data-seconds', seconds);
            let h = Math.floor(seconds / 3600);
            let m = Math.floor((seconds % 3600) / 60);
            let s = seconds % 60;
            const timerText = el.querySelector('.timer-text');
            if (timerText) {
                timerText.innerText = (h < 10 ? '0' + h : h) + ":" + (m < 10 ? '0' + m : m) + ":" + (s < 10 ? '0' + s : s);
            }
        }
    });
}, 1000);

// --- BUSCA DE PRODUTOS ---
document.getElementById('productSearch').addEventListener('keyup', function() {
    const term = this.value.toLowerCase().trim();
    const allCategoryContents = document.querySelectorAll('.category-content');
    const allProductItems = document.querySelectorAll('.product-item');
    
    if (term.length > 0) {
        // Mostra todas as categorias durante a busca
        allCategoryContents.forEach(content => {
            content.classList.remove('d-none');
        });
        
        // Filtra produtos
        allProductItems.forEach(item => {
            const productName = item.getAttribute('data-product-name');
            if (productName && productName.includes(term)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    } else {
        // Restaura visualização normal
        allProductItems.forEach(item => {
            item.style.display = 'block';
        });
        
        const activeTab = document.querySelector('.category-tab.active');
        if (activeTab) {
            const categoryId = activeTab.getAttribute('data-category');
            showCategory(categoryId);
        }
    }
});

// --- SISTEMA DE CATEGORIAS ---
function showCategory(categoryId) {
    // Remove active de todos os botões
    document.querySelectorAll('.category-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Adiciona active no botão clicado
    const activeBtn = document.querySelector(`[data-category="${categoryId}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }
    
    // Esconde todas as categorias
    document.querySelectorAll('.category-content').forEach(content => {
        content.classList.add('d-none');
    });
    
    // Mostra a categoria selecionada
    const selectedContent = document.getElementById('content-' + categoryId);
    if (selectedContent) {
        selectedContent.classList.remove('d-none');
    }
}

// --- SISTEMA DE CARRINHO ---
let cart = JSON.parse(localStorage.getItem('rs_cart')) || [];
let activeCoupon = null;

function updateCartCount() {
    const countEl = document.getElementById('cartCount');
    if (countEl) {
        countEl.innerText = cart.length;
    }
}

function saveCart() {
    localStorage.setItem('rs_cart', JSON.stringify(cart));
    updateCartCount();
}

function addToCart(id, name, price, icon, color) {
    cart.push({ id: id, name: name, price: price, icon: icon, color: color });
    saveCart();
    
    Swal.fire({
        toast: true,
        position: 'bottom-end',
        icon: 'success',
        title: 'Adicionado ao carrinho!',
        showConfirmButton: false,
        timer: 1500
    });
}

function removeFromCart(index) {
    cart.splice(index, 1);
    saveCart();
    renderCart();
}

function openCartModal() {
    renderCart();
    const modal = new bootstrap.Modal(document.getElementById('cartModal'));
    modal.show();
}

function renderCart() {
    const tbody = document.getElementById('cartTableBody');
    const emptyState = document.getElementById('cartEmptyState');
    const cartContent = document.getElementById('cartContent');
    
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (cart.length === 0) {
        if (emptyState) emptyState.classList.remove('d-none');
        if (cartContent) cartContent.classList.add('d-none');
        return;
    }
    
    if (emptyState) emptyState.classList.add('d-none');
    if (cartContent) cartContent.classList.remove('d-none');
    
    cart.forEach((item, index) => {
        tbody.innerHTML += `
            <tr>
                <td>
                    <i class="fa-solid ${item.icon} me-2" style="color:${item.color}"></i> 
                    ${item.name}
                </td>
                <td>R$ ${item.price.toFixed(2).replace('.', ',')}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    updateCartTotal();
}

function updateCartTotal() {
    let subtotal = cart.reduce((sum, item) => sum + item.price, 0);
    let discount = 0;
    
    if (activeCoupon) {
        discount = subtotal * (activeCoupon.percent / 100);
    }
    
    const isTest = document.getElementById('cartAdminTest')?.checked;
    let final = isTest ? 0 : (subtotal - discount);
    
    const subtotalEl = document.getElementById('cartSubtotal');
    const discountEl = document.getElementById('cartDiscount');
    const finalEl = document.getElementById('cartFinalTotal');
    const dataInput = document.getElementById('cartDataInput');
    
    if (subtotalEl) subtotalEl.innerText = 'R$ ' + subtotal.toFixed(2).replace('.', ',');
    if (discountEl) discountEl.innerText = '- R$ ' + discount.toFixed(2).replace('.', ',');
    if (finalEl) finalEl.innerText = 'R$ ' + final.toFixed(2).replace('.', ',');
    if (dataInput) dataInput.value = JSON.stringify(cart);
}

function applyCartCoupon() {
    const code = document.getElementById('cartCouponInput').value;
    const msg = document.getElementById('cartCouponMsg');
    
    if (!code) return;
    
    const formData = new FormData();
    formData.append('check_coupon', '1');
    formData.append('code', code);
    
    fetch('loja.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.valid) {
            activeCoupon = { code: data.code, percent: data.percent };
            msg.className = 'text-success small fw-bold mb-2';
            msg.innerText = `Cupom aplicado: -${data.percent}%`;
            updateCartTotal();
        } else {
            activeCoupon = null;
            msg.className = 'text-danger small fw-bold mb-2';
            msg.innerText = data.msg;
            updateCartTotal();
        }
    })
    .catch(err => {
        msg.className = 'text-danger small fw-bold mb-2';
        msg.innerText = 'Erro ao verificar cupom';
    });
}

// Inicializa contador do carrinho
updateCartCount();

// --- SISTEMA DE AVALIAÇÕES ---
let currentReviewPage = {};

function loadReviews(productId, page = 1) {
    const container = document.getElementById('reviewsContainer' + productId);
    if (!container) return;
    
    // Só carrega se ainda não foi carregado ou se mudou de página
    if (currentReviewPage[productId] === page) return;
    currentReviewPage[productId] = page;
    
    const formData = new FormData();
    formData.append('get_reviews', '1');
    formData.append('product_id', productId);
    formData.append('page', page);
    
    fetch('loja.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            container.innerHTML = '<p class="text-center text-muted">Erro ao carregar avaliações</p>';
            return;
        }
        
        const stats = data.stats;
        const reviews = data.reviews;
        const avgRating = parseFloat(stats.avg_rating || 0).toFixed(1);
        const totalReviews = parseInt(stats.total_reviews || 0);
        
        let html = '';
        
        // Cabeçalho com estatísticas
        if (totalReviews > 0) {
            html += `
                <div class="row mb-4">
                    <div class="col-md-4 text-center">
                        <div class="rating-badge d-inline-block mb-2">
                            <i class="fa-solid fa-star me-1"></i>${avgRating}
                        </div>
                        <p class="mb-0 small text-muted">${totalReviews} ${totalReviews === 1 ? 'avaliação' : 'avaliações'}</p>
                    </div>
                    <div class="col-md-8">
                        ${generateStatsBar(5, stats.star_5, totalReviews)}
                        ${generateStatsBar(4, stats.star_4, totalReviews)}
                        ${generateStatsBar(3, stats.star_3, totalReviews)}
                        ${generateStatsBar(2, stats.star_2, totalReviews)}
                        ${generateStatsBar(1, stats.star_1, totalReviews)}
                    </div>
                </div>
            `;
        }
        
        // Botão de adicionar avaliação (se logado)
        html += `
            <div class="text-center mb-4">
                <button class="btn btn-warning fw-bold" onclick="openReviewForm(${productId})">
                    <i class="fa-solid fa-pen-to-square me-2"></i>Avaliar este Produto
                </button>
            </div>
            <div id="reviewForm${productId}" class="mb-4" style="display:none;">
                <div class="card border-warning">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Sua Avaliação</h6>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nota:</label>
                            <div class="star-rating" id="ratingInput${productId}">
                                <i class="fa-solid fa-star" data-rating="1"></i>
                                <i class="fa-solid fa-star" data-rating="2"></i>
                                <i class="fa-solid fa-star" data-rating="3"></i>
                                <i class="fa-solid fa-star" data-rating="4"></i>
                                <i class="fa-solid fa-star" data-rating="5"></i>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Comentário (opcional):</label>
                            <textarea id="reviewComment${productId}" class="form-control" rows="3" placeholder="Conte sua experiência com este produto..."></textarea>
                        </div>
                        <button class="btn btn-success w-100 fw-bold" onclick="submitReview(${productId})">
                            <i class="fa-solid fa-paper-plane me-2"></i>Enviar Avaliação
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Lista de avaliações
        if (reviews.length > 0) {
            html += '<div class="mb-3"><h6 class="fw-bold">Avaliações dos Clientes:</h6></div>';
            reviews.forEach(review => {
                const timeAgo = timeElapsed(review.created_at);
                html += `
                    <div class="review-card p-3 rounded mb-3">
                        <div class="d-flex align-items-start gap-3">
                            <img src="https://cravatar.eu/helmavatar/${encodeURIComponent(review.player)}/48.png" 
                                 class="review-avatar" alt="${review.player}"
                                 onerror="this.src='https://cravatar.eu/helmavatar/Steve/48.png'">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong>${review.player}</strong>
                                        <div class="star-rating star-rating-small readonly mt-1">
                                            ${generateStars(review.rating)}
                                        </div>
                                    </div>
                                    <small class="text-muted">${timeAgo}</small>
                                </div>
                                ${review.comment ? `<p class="mb-2 small">${review.comment}</p>` : ''}
                                <div class="d-flex gap-3 small text-muted">
                                    <button class="btn btn-sm btn-link text-decoration-none p-0" onclick="markHelpful(${review.id}, ${productId})">
                                        <i class="fa-solid fa-thumbs-up me-1"></i>Útil (${review.helpful_count})
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            // Paginação
            if (data.pages > 1) {
                html += '<div class="text-center mt-3"><div class="btn-group" role="group">';
                for (let i = 1; i <= data.pages; i++) {
                    html += `<button class="btn btn-sm ${i === page ? 'btn-primary' : 'btn-outline-primary'}" onclick="loadReviews(${productId}, ${i})">${i}</button>`;
                }
                html += '</div></div>';
            }
        } else {
            html += '<p class="text-center text-muted py-4"><i class="fa-solid fa-star-half-stroke fa-2x mb-3 d-block"></i>Nenhuma avaliação ainda. Seja o primeiro!</p>';
        }
        
        container.innerHTML = html;
        
        // Ativa sistema de estrelas clicáveis
        const ratingInput = document.getElementById('ratingInput' + productId);
        if (ratingInput) {
            setupStarRating(ratingInput);
        }
    })
    .catch(err => {
        console.error(err);
        container.innerHTML = '<p class="text-center text-danger">Erro ao carregar avaliações</p>';
    });
}

function generateStars(rating) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        html += `<i class="fa-solid fa-star ${i <= rating ? 'active' : ''}"></i>`;
    }
    return html;
}

function generateStatsBar(stars, count, total) {
    const percentage = total > 0 ? Math.round((count / total) * 100) : 0;
    return `
        <div class="d-flex align-items-center gap-2 mb-2">
            <small class="text-nowrap" style="width: 60px;">${stars} <i class="fa-solid fa-star text-warning small"></i></small>
            <div class="review-stats-bar flex-grow-1">
                <div class="review-stats-fill" style="width: ${percentage}%"></div>
            </div>
            <small class="text-muted" style="width: 50px;">${count} (${percentage}%)</small>
        </div>
    `;
}

function timeElapsed(dateString) {
    const now = new Date();
    const past = new Date(dateString);
    const seconds = Math.floor((now - past) / 1000);
    
    if (seconds < 60) return 'agora';
    if (seconds < 3600) return `há ${Math.floor(seconds / 60)}min`;
    if (seconds < 86400) return `há ${Math.floor(seconds / 3600)}h`;
    if (seconds < 2592000) return `há ${Math.floor(seconds / 86400)}d`;
    return `há ${Math.floor(seconds / 2592000)}m`;
}

function setupStarRating(container) {
    let selectedRating = 0;
    const stars = container.querySelectorAll('i');
    
    stars.forEach((star, index) => {
        star.addEventListener('mouseenter', () => {
            stars.forEach((s, i) => {
                s.classList.toggle('active', i <= index);
            });
        });
        
        star.addEventListener('click', () => {
            selectedRating = index + 1;
            container.dataset.rating = selectedRating;
        });
    });
    
    container.addEventListener('mouseleave', () => {
        stars.forEach((s, i) => {
            s.classList.toggle('active', i < selectedRating);
        });
    });
}

function openReviewForm(productId) {
    const form = document.getElementById('reviewForm' + productId);
    if (form.style.display === 'none') {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        form.style.display = 'none';
    }
}

function submitReview(productId) {
    const ratingInput = document.getElementById('ratingInput' + productId);
    const rating = parseInt(ratingInput.dataset.rating || 0);
    const comment = document.getElementById('reviewComment' + productId).value;
    
    if (rating === 0) {
        Swal.fire('Ops!', 'Selecione uma nota de 1 a 5 estrelas', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('submit_review', '1');
    formData.append('product_id', productId);
    formData.append('rating', rating);
    formData.append('comment', comment);
    
    fetch('loja.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Avaliação Enviada!',
                text: data.msg,
                timer: 2000
            });
            
            // Recarrega avaliações
            currentReviewPage[productId] = 0;
            loadReviews(productId);
            
            // Esconde formulário
            document.getElementById('reviewForm' + productId).style.display = 'none';
        } else {
            Swal.fire('Erro', data.msg, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire('Erro', 'Não foi possível enviar a avaliação', 'error');
    });
}

function markHelpful(reviewId, productId) {
    const formData = new FormData();
    formData.append('mark_helpful', '1');
    formData.append('review_id', reviewId);
    
    fetch('loja.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Recarrega avaliações para atualizar contador
            const currentPage = currentReviewPage[productId] || 1;
            currentReviewPage[productId] = 0;
            loadReviews(productId, currentPage);
            
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Obrigado pelo feedback!',
                showConfirmButton: false,
                timer: 1500
            });
        }
    })
    .catch(err => console.error(err));
}

// --- SISTEMA DE NOTIFICAÇÕES TOAST EM TEMPO REAL ---
let lastSaleId = 0;
const toastContainer = document.getElementById('toastContainer');

function timeAgo(dateString) {
    const now = new Date();
    const past = new Date(dateString);
    const seconds = Math.floor((now - past) / 1000);
    
    if (seconds < 60) return 'agora mesmo';
    if (seconds < 3600) return `há ${Math.floor(seconds / 60)} min`;
    if (seconds < 86400) return `há ${Math.floor(seconds / 3600)}h`;
    return `há ${Math.floor(seconds / 86400)} dias`;
}

function showSaleToast(sale) {
    const toast = document.createElement('div');
    toast.className = 'sale-toast';
    toast.innerHTML = `
        <img src="https://cravatar.eu/helmavatar/${encodeURIComponent(sale.player)}/40.png" 
             width="40" height="40" alt="${sale.player}"
             onerror="this.src='https://cravatar.eu/helmavatar/Steve/40.png'">
        <div class="sale-toast-content">
            <div class="sale-toast-player">🔥 ${sale.player}</div>
            <div class="sale-toast-product">acabou de comprar ${sale.product}</div>
            <div class="sale-toast-time">${timeAgo(sale.time)}</div>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Remove após 5 segundos
    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 400);
    }, 5000);
}

function checkRecentSales() {
    const formData = new FormData();
    formData.append('get_recent_sales', '1');
    formData.append('last_id', lastSaleId);
    
    fetch('loja.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.sales && data.sales.length > 0) {
            data.sales.forEach((sale, index) => {
                // Delay para não mostrar todos de uma vez
                setTimeout(() => {
                    showSaleToast(sale);
                    if (sale.id > lastSaleId) {
                        lastSaleId = sale.id;
                    }
                }, index * 1000); // 1 segundo entre cada notificação
            });
        }
    })
    .catch(err => console.log('Erro ao buscar vendas:', err));
}

// Busca inicial para pegar o ID mais recente sem mostrar notificações
fetch('loja.php', { 
    method: 'POST', 
    body: new URLSearchParams({ get_recent_sales: '1', last_id: '0' })
})
.then(r => r.json())
.then(data => {
    if (data.sales && data.sales.length > 0) {
        // Pega apenas o ID mais recente sem mostrar toast
        lastSaleId = Math.max(...data.sales.map(s => s.id));
    }
    
    // Inicia o polling a cada 10 segundos
    setInterval(checkRecentSales, 10000);
})
.catch(err => {
    console.log('Erro na inicialização:', err);
    // Mesmo com erro, inicia o polling
    setInterval(checkRecentSales, 10000);
});
</script>

<?php include 'includes/footer.php'; ?>