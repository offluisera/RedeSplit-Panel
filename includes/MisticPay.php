<?php
/**
 * MisticPay API Integration Library
 * Documentação: https://docs.misticpay.com
 */

class MisticPay {
    private $clientId;
    private $clientSecret;
    private $testMode;
    private $baseUrl;
    private $accessToken;
    private $conn;
    
    public function __construct($conn, $config = null) {
        $this->conn = $conn;
        
        // Se não passou config, busca do banco
        if (!$config) {
            $gateway = $conn->query("SELECT * FROM rs_payment_gateways WHERE name = 'misticpay' AND active = 1")->fetch_assoc();
            
            if (!$gateway) {
                throw new Exception('MisticPay não está configurado ou ativo');
            }
            
            $config = $gateway;
        }
        
        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->testMode = (bool)($config['test_mode'] ?? true);
        
        // URL base da API
        $this->baseUrl = $this->testMode 
            ? 'https://sandbox-api.misticpay.com/v1'
            : 'https://api.misticpay.com/v1';
    }
    
    /**
     * Autentica e obtém token de acesso
     */
    private function authenticate() {
        if ($this->accessToken) {
            return $this->accessToken;
        }
        
        $ch = curl_init($this->baseUrl . '/auth/token');
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials'
            ])
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Falha na autenticação MisticPay: ' . $response);
        }
        
        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'] ?? null;
        
        if (!$this->accessToken) {
            throw new Exception('Token de acesso não retornado');
        }
        
        return $this->accessToken;
    }
    
    /**
     * Faz requisição à API
     */
    private function request($method, $endpoint, $data = null) {
        $token = $this->authenticate();
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init($url);
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method)
        ];
        
        if ($data && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 400) {
            throw new Exception($result['message'] ?? 'Erro na API MisticPay', $httpCode);
        }
        
        return $result;
    }
    
    /**
     * Cria um pagamento PIX
     * 
     * @param array $params Parâmetros do pagamento
     * @return array Dados do pagamento criado
     */
    public function createPixPayment($params) {
        $required = ['amount', 'external_reference', 'payer'];
        
        foreach ($required as $field) {
            if (!isset($params[$field])) {
                throw new Exception("Campo obrigatório ausente: $field");
            }
        }
        
        // Formata dados do pagamento
        $paymentData = [
            'payment_method' => 'pix',
            'amount' => (float)$params['amount'],
            'external_reference' => $params['external_reference'],
            'description' => $params['description'] ?? 'Compra na loja',
            'payer' => [
                'name' => $params['payer']['name'] ?? 'Cliente',
                'email' => $params['payer']['email'] ?? 'cliente@exemplo.com',
                'document' => $params['payer']['document'] ?? '',
                'phone' => $params['payer']['phone'] ?? ''
            ],
            'metadata' => $params['metadata'] ?? []
        ];
        
        // Adiciona URL de retorno se fornecida
        if (isset($params['return_url'])) {
            $paymentData['return_url'] = $params['return_url'];
        }
        
        return $this->request('POST', '/payments', $paymentData);
    }
    
    /**
     * Cria um pagamento com cartão de crédito
     */
    public function createCardPayment($params) {
        $required = ['amount', 'external_reference', 'payer', 'card'];
        
        foreach ($required as $field) {
            if (!isset($params[$field])) {
                throw new Exception("Campo obrigatório ausente: $field");
            }
        }
        
        $paymentData = [
            'payment_method' => 'credit_card',
            'amount' => (float)$params['amount'],
            'external_reference' => $params['external_reference'],
            'description' => $params['description'] ?? 'Compra na loja',
            'installments' => (int)($params['installments'] ?? 1),
            'payer' => [
                'name' => $params['payer']['name'],
                'email' => $params['payer']['email'],
                'document' => $params['payer']['document'],
                'phone' => $params['payer']['phone'] ?? ''
            ],
            'card' => [
                'number' => $params['card']['number'],
                'holder_name' => $params['card']['holder_name'],
                'expiration_month' => $params['card']['expiration_month'],
                'expiration_year' => $params['card']['expiration_year'],
                'cvv' => $params['card']['cvv']
            ],
            'metadata' => $params['metadata'] ?? []
        ];
        
        return $this->request('POST', '/payments', $paymentData);
    }
    
    /**
     * Consulta um pagamento pelo ID
     */
    public function getPayment($paymentId) {
        return $this->request('GET', '/payments/' . $paymentId);
    }
    
    /**
     * Consulta um pagamento pela referência externa
     */
    public function getPaymentByReference($externalRef) {
        $result = $this->request('GET', '/payments?external_reference=' . urlencode($externalRef));
        return $result['data'][0] ?? null;
    }
    
    /**
     * Cancela um pagamento
     */
    public function cancelPayment($paymentId) {
        return $this->request('POST', '/payments/' . $paymentId . '/cancel');
    }
    
    /**
     * Reembolsa um pagamento
     */
    public function refundPayment($paymentId, $amount = null) {
        $data = [];
        if ($amount !== null) {
            $data['amount'] = (float)$amount;
        }
        
        return $this->request('POST', '/payments/' . $paymentId . '/refund', $data);
    }
    
    /**
     * Lista todos os pagamentos (com filtros opcionais)
     */
    public function listPayments($filters = []) {
        $query = http_build_query($filters);
        $endpoint = '/payments' . ($query ? '?' . $query : '');
        
        return $this->request('GET', $endpoint);
    }
    
    /**
     * Obtém saldo da conta
     */
    public function getBalance() {
        return $this->request('GET', '/account/balance');
    }
    
    /**
     * Valida webhook signature
     */
    public function validateWebhook($payload, $signature, $secret) {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Formata valor para a API (centavos para reais)
     */
    public static function formatAmount($amount) {
        return number_format((float)$amount, 2, '.', '');
    }
    
    /**
     * Gera referência externa única
     */
    public static function generateReference($prefix = 'SPLIT') {
        return $prefix . '-' . date('YmdHis') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
    }
}
?>