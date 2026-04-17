<?php
use Adianti\Http\AdiantiHttpClient;

/**
 * InfinitePayService
 * 
 * Handles InfinitePay OAuth authentication, API calls, encryption/decryption,
 * and webhook processing for Tap to Pay integration.
 * 
 * @version    1.0
 * @package    service
 * @subpackage barbearia
 */
class InfinitePayService
{
    private const BASE_URL = 'https://api.infinitepay.io';
    private const TOKEN_URL = '/oauth/token';
    private const DB = 'barbearia';
    private const ENCRYPTION_METHOD = 'AES-256-CBC';

    /**
     * Get active configuration or throw exception
     * @return InfinitePayConfig
     * @throws Exception
     */
    private function getConfig()
    {
        $config = InfinitePayConfig::getInstance();
        if (empty($config))
        {
            throw new Exception(_t('InfinitePay is not configured. Please configure it first.'));
        }
        return $config;
    }

    /**
     * Get encryption key derived from application seed
     * @return string
     */
    private function getEncryptionKey()
    {
        $config = include 'app/config/application.php';
        $seed = $config['general']['seed'] ?? 'default_seed';
        return hash('sha256', $seed, true);
    }

    /**
     * Encrypt a value using AES-256-CBC
     * @param string $value
     * @return string Base64 encoded IV + encrypted data
     */
    private function criptografar($value)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPTION_METHOD));
        $encrypted = openssl_encrypt($value, self::ENCRYPTION_METHOD, $this->getEncryptionKey(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a value encrypted with criptografar()
     * @param string $encrypted Base64 encoded IV + encrypted data
     * @return string
     */
    private function descriptografar($encrypted)
    {
        $data = base64_decode($encrypted);
        $ivLen = openssl_cipher_iv_length(self::ENCRYPTION_METHOD);
        $iv = substr($data, 0, $ivLen);
        $encryptedData = substr($data, $ivLen);
        return openssl_decrypt($encryptedData, self::ENCRYPTION_METHOD, $this->getEncryptionKey(), OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Refresh token if necessary (check expiration)
     * @throws Exception
     */
    private function renovarTokenSeNecessario()
    {
        $config = $this->getConfig();
        $expiresAt = strtotime((string) $config->token_expires_at);
        $now = time();

        // Refresh 5 minutes before expiration
        if ($expiresAt && ($now + 300) > $expiresAt)
        {
            $this->autenticar();
        }
    }

    /**
     * Authenticate with InfinitePay OAuth and save encrypted tokens
     * @throws Exception
     */
    private function autenticar()
    {
        $config = $this->getConfig();
        
        $payload = [
            'client_id' => $config->client_id,
            'client_secret' => $this->descriptografar($config->client_secret_enc),
            'grant_type' => 'client_credentials'
        ];

        $client = new AdiantiHttpClient;
        $response = $client->request(
            'post',
            self::BASE_URL . self::TOKEN_URL,
            json_encode($payload),
            ['Content-Type' => 'application/json'],
            false,
            true
        );

        $result = json_decode($response, true);

        if (empty($result['access_token']))
        {
            throw new Exception(_t('Failed to authenticate with InfinitePay.'));
        }

        $config->access_token_enc = $this->criptografar($result['access_token']);
        $config->refresh_token_enc = !empty($result['refresh_token']) 
            ? $this->criptografar($result['refresh_token']) 
            : $config->refresh_token_enc;
        $expiresIn = (int) ($result['expires_in'] ?? 3600);
        $config->token_expires_at = date('Y-m-d H:i:s', time() + $expiresIn);
        $config->updated_at = date('Y-m-d H:i:s');
        $config->store();
    }

    /**
     * Create a charge in InfinitePay
     * @param float $valor
     * @param string $descricao
     * @param int $agendamentoId
     * @return array ['charge_id' => '...', 'status' => '...', 'payment_url' => '...']
     * @throws Exception
     */
    public function criarCobranca($valor, $descricao, $agendamentoId)
    {
        try
        {
            $this->renovarTokenSeNecessario();
            $config = $this->getConfig();
            $accessToken = $this->descriptografar($config->access_token_enc);

            $payload = [
                'amount' => (float) $valor,
                'currency' => 'BRL',
                'description' => (string) $descricao,
                'metadata' => [
                    'agendamento_id' => (int) $agendamentoId
                ]
            ];

            $client = new AdiantiHttpClient;
            $response = $client->request(
                'post',
                self::BASE_URL . '/v2/charges',
                json_encode($payload),
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken
                ],
                false,
                true
            );

            $result = json_decode($response, true);

            if (empty($result['id']))
            {
                throw new Exception(
                    _t('Failed to create charge: ') . ($result['error'] ?? 'Unknown error')
                );
            }

            // Create transaction record
            $transacao = new InfinitePayTransacao;
            $transacao->agendamento_id = (int) $agendamentoId;
            $transacao->infinitepay_charge_id = $result['id'];
            $transacao->valor = (float) $valor;
            $transacao->status = $result['status'] ?? 'pending';
            $transacao->response_data = json_encode($result);
            $transacao->save();

            // Link transaction in agendamento
            $agendamento = new Agendamento($agendamentoId);
            $agendamento->infinitepay_transacao_id = $transacao->id;
            $agendamento->save();

            TTransaction::close();

            return [
                'charge_id' => $result['id'],
                'status' => $result['status'] ?? 'pending',
                'payment_url' => $result['payment_url'] ?? null,
                'qr_code' => $result['qr_code'] ?? null
            ];
        }
        catch (Exception $e)
        {
            throw $e;
        }
    }

    /**
     * Check charge status in InfinitePay
     * @param string $chargeId
     * @return array ['status' => '...', 'paid_at' => '...', ...]
     * @throws Exception
     */
    public function consultarCobranca($chargeId)
    {
        try
        {
            $this->renovarTokenSeNecessario();
            $config = $this->getConfig();
            $accessToken = $this->descriptografar($config->access_token_enc);

            $client = new AdiantiHttpClient;
            $response = $client->request(
                'get',
                self::BASE_URL . '/v2/charges/' . urlencode($chargeId),
                '',
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken
                ],
                false,
                true
            );

            $result = json_decode($response, true);

            if (empty($result['id']))
            {
                throw new Exception(_t('Charge not found.'));
            }

            return $result;
        }
        catch (Exception $e)
        {
            throw $e;
        }
    }

    /**
     * Process webhook from InfinitePay
     * Validates signature, updates transaction status, auto-completes agendamento if approved
     * @param array $payload
     * @param string $signature X-InfinitePay-Signature header value
     * @return bool
     * @throws Exception
     */
    public function processarWebhook($payload, $signature)
    {
        try
        {
            $config = $this->getConfig();
            $secret = $this->descriptografar($config->client_secret_enc);

            // Validate HMAC signature
            $body = json_encode($payload);
            $expectedSignature = hash_hmac('sha256', $body, $secret);

            // Compare signatures safely (constant-time)
            if (!hash_equals($expectedSignature, $signature))
            {
                throw new Exception('Invalid webhook signature');
            }

            $chargeId = $payload['charge_id'] ?? $payload['id'] ?? null;
            if (empty($chargeId))
            {
                throw new Exception('Invalid webhook payload: missing charge_id');
            }

            // Find transaction
            $transacao = InfinitePayTransacao::findByChargeId($chargeId);
            if (empty($transacao))
            {
                throw new Exception('Transaction not found for charge: ' . $chargeId);
            }

            // Update transaction
            $transacao->status = $payload['status'] ?? 'pending';
            $transacao->response_data = json_encode($payload);
            $transacao->save();

            // Auto-complete agendamento if approved
            if ($transacao->status === 'approved')
            {
                $agendamento = new Agendamento($transacao->agendamento_id);
                $statusAtual = strtolower((string) $agendamento->status);
                $permitidos = ['agendado', 'confirmado', 'em atendimento'];

                if (in_array($statusAtual, $permitidos, true))
                {
                    $agendamento->status = 'Concluido';
                    $agendamento->forma_pagamento = 'InfinitePay';
                    $agendamento->data_conclusao = date('Y-m-d H:i:s');
                    $agendamento->save();

                    // Generate cash movement
                    self::gerarMovimentoCaixa($agendamento);
                }
            }

            return true;
        }
        catch (Exception $e)
        {
            throw $e;
        }
    }

    /**
     * Generate cash movement for agendamento (private helper)
     * @param Agendamento $agendamento
     */
    private static function gerarMovimentoCaixa(Agendamento $agendamento)
    {
        // Check if movement already exists
        $existing = MovimentoCaixa::findBy(['agendamento_id' => $agendamento->id]);
        if (!empty($existing))
        {
            return;
        }

        $mov = new MovimentoCaixa;
        $dataMovimento = $agendamento->data_conclusao ?: $agendamento->data_agendamento;
        $dataMovimento = Agendamento::normalizeDate((string) $dataMovimento);
        $mov->data_movimento = $dataMovimento;
        $mov->tipo = 'E';
        $mov->descricao = 'Agendamento #' . $agendamento->id . ' (InfinitePay)';
        $mov->valor = (float) ($agendamento->valor_cobrado ?? 0);
        $mov->categoria = 'Agendamento';
        $mov->agendamento_id = $agendamento->id;
        $mov->save();
    }

    /**
     * Test connection with current credentials
     * @return array ['success' => true, 'message' => '...']
     * @throws Exception
     */
    public function testarConexao()
    {
        try
        {
            $this->autenticar();
            $config = $this->getConfig();

            if (empty($config->access_token_enc))
            {
                throw new Exception(_t('Authentication failed: no access token.'));
            }

            return [
                'success' => true,
                'message' => _t('Connection successful!')
            ];
        }
        catch (Exception $e)
        {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
