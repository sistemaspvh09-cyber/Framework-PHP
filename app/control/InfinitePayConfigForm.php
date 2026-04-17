<?php
/**
 * InfinitePayConfigForm
 * 
 * Controller for configuring InfinitePay credentials
 * 
 * @version    1.0
 * @package    control
 */
class InfinitePayConfigForm extends TPage
{
    private const DB = 'barbearia';

    public function __construct()
    {
        parent::__construct();
        
        $html = new THtmlRenderer('app/resources/barbearia/infinitepay_config.html');
        $html->enableSection('main', [
            'page_title' => _t('InfinitePay Configuration'),
            'page_subtitle' => _t('Configure your InfinitePay credentials for Tap to Pay'),
            'label_client_id' => _t('Client ID'),
            'label_client_secret' => _t('Client Secret'),
            'hint_credentials' => _t('Get your credentials from your InfinitePay dashboard'),
            'btn_connect' => _t('Connect'),
            'btn_test' => _t('Test Connection'),
            'btn_disconnect' => _t('Disconnect'),
            'msg_connected' => _t('Connected'),
            'msg_not_configured' => _t('Not configured'),
            'msg_testing' => _t('Testing connection...')
        ]);

        $panel = new TPanelGroup(_t('InfinitePay Tap to Pay'));
        $panel->add($html);

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add($panel);

        parent::add($vbox);
    }

    /**
     * Load current configuration
     */
    public static function onShow($param)
    {
        try
        {
            TTransaction::open(self::DB);

            $config = InfinitePayConfig::getInstance();
            
            if (empty($config))
            {
                // Create default config if not exists
                $config = new InfinitePayConfig;
                $config->ativo = 'Y';
                $config->created_at = date('Y-m-d H:i:s');
            }

            $response = [
                'client_id' => (string) $config->client_id,
                'is_configured' => !empty($config->client_id) && !empty($config->client_secret_enc),
                'ativo' => (string) $config->ativo === 'Y'
            ];

            TTransaction::close();

            self::jsonResponse($response);
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            self::jsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Save InfinitePay credentials and test
     */
    public static function onSave($param)
    {
        try
        {
            $clientId = trim((string) ($param['client_id'] ?? ''));
            $clientSecret = trim((string) ($param['client_secret'] ?? ''));

            if (empty($clientId))
            {
                throw new RuntimeException(_t('Client ID is required.'));
            }
            if (empty($clientSecret))
            {
                throw new RuntimeException(_t('Client Secret is required.'));
            }

            TTransaction::open(self::DB);

            $config = InfinitePayConfig::getInstance() ?: new InfinitePayConfig;
            $config->client_id = $clientId;
            
            // Encrypt secret
            $service = new InfinitePayService;
            $reflection = new ReflectionMethod('InfinitePayService', 'criptografar');
            $reflection->setAccessible(true);
            $config->client_secret_enc = $reflection->invoke($service, $clientSecret);

            $config->ativo = 'Y';
            $config->updated_at = date('Y-m-d H:i:s');
            if (empty($config->id))
            {
                $config->created_at = date('Y-m-d H:i:s');
            }
            $config->store();

            // Test connection
            $service = new InfinitePayService;
            $result = $service->testarConexao();

            TTransaction::close();

            if (!$result['success'])
            {
                throw new RuntimeException($result['message']);
            }

            self::jsonResponse([
                'success' => true,
                'message' => _t('InfinitePay configured successfully!'),
                'is_configured' => true
            ]);
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            self::jsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Test connection with current credentials
     */
    public static function onTestarConexao($param)
    {
        try
        {
            TTransaction::open(self::DB);

            $service = new InfinitePayService;
            $result = $service->testarConexao();

            TTransaction::close();

            self::jsonResponse($result);
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            self::jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Disconnect (remove tokens but keep credentials inactive)
     */
    public static function onDesconectar($param)
    {
        try
        {
            TTransaction::open(self::DB);

            $config = InfinitePayConfig::getInstance();
            if (!empty($config))
            {
                $config->ativo = 'N';
                $config->access_token_enc = '';
                $config->refresh_token_enc = '';
                $config->token_expires_at = '';
                $config->updated_at = date('Y-m-d H:i:s');
                $config->store();
            }

            TTransaction::close();

            self::jsonResponse([
                'success' => true,
                'message' => _t('Disconnected successfully.'),
                'is_configured' => false
            ]);
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            self::jsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Response helper
     */
    private static function jsonResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
