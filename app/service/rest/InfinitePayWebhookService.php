<?php
/**
 * InfinitePayWebhookService
 * 
 * Public webhook endpoint for InfinitePay payment notifications
 * No Adianti inheritance to allow public access without authentication
 * 
 * @version    1.0
 * @package    service
 * @subpackage rest
 */
class InfinitePayWebhookService
{
    private const DB = 'barbearia';

    /**
     * Handle webhook from InfinitePay
     * This is the public entry point called by webhook.php
     */
    public static function handle(): void
    {
        try
        {
            // Get raw body for signature validation
            $body = file_get_contents('php://input');
            $signature = $_SERVER['HTTP_X_INFINITEPAY_SIGNATURE'] ?? '';
            $payload = json_decode($body, true);

            if (empty($payload) || empty($signature))
            {
                self::sendError('Missing payload or signature', 400);
                return;
            }

            // Open transaction and process webhook
            TTransaction::open(self::DB);

            try
            {
                $service = new InfinitePayService;
                $service->processarWebhook($payload, $signature);

                TTransaction::close();
                self::sendSuccess();
            }
            catch (Exception $e)
            {
                TTransaction::rollback();
                self::sendError($e->getMessage(), 400);
            }
        }
        catch (Throwable $e)
        {
            self::sendError('Internal error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send success response
     */
    private static function sendSuccess(): void
    {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'message' => 'Webhook processed']);
        exit;
    }

    /**
     * Send error response
     */
    private static function sendError(string $message, int $code): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $message]);
        exit;
    }
}
