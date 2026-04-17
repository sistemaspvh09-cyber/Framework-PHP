<?php
/**
 * InfinitePay Webhook Handler
 * 
 * Public endpoint for receiving webhooks from InfinitePay
 * This file does NOT require user authentication
 * Register this URL in your InfinitePay dashboard: https://your-domain.com/webhook.php
 * 
 * @version    1.0
 */

// Define application path
define('APPLICATION_PATH', 'app');

// Load framework initialization
require_once 'init.php';

// Load webhook service
require_once 'app/service/rest/InfinitePayWebhookService.php';

// Process webhook
InfinitePayWebhookService::handle();
