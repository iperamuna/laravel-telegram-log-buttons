<?php

namespace Iperamuna\TelegramLog\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Iperamuna\TelegramLog\Support\TelegramCallbackRegistry;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HealthCheckController extends Controller
{
    public function __invoke(Request $request, TelegramCallbackRegistry $registry)
    {
        $config = config('telegram-log');

        $health = $config['health'] ?? [];
        if (! ($health['enabled'] ?? false)) {
            abort(404);
        }

        if (! empty($health['secret'])) {
            $secret = $request->header('X-Telegram-Log-Health-Secret');
            if ($secret !== $health['secret']) {
                return response()->json(['ok' => false, 'error' => 'Invalid health secret'], Response::HTTP_FORBIDDEN);
            }
        }

        $botToken = $config['bot_token'] ?? null;
        $chatId = $config['chat_id'] ?? null;

        $callbackMap = config('telegram-log-callbacks.map', []);
        $registered = [];
        $missing = [];

        foreach ($callbackMap as $action => $class) {
            if (! is_string($action) || ! is_string($class)) {
                continue;
            }

            if (class_exists($class)) {
                $registered[] = ['action' => $action, 'class' => $class];
            } else {
                $missing[] = ['action' => $action, 'class' => $class];
            }
        }

        $webhookInfo = null;

        if ($botToken) {
            try {
                $client = new Client([
                    'base_uri' => "https://api.telegram.org/bot{$botToken}/",
                    'timeout' => 5,
                ]);

                $response = $client->get('getWebhookInfo');
                $data = json_decode((string) $response->getBody(), true);

                if (is_array($data) && ($data['ok'] ?? false)) {
                    $webhookInfo = $data['result'] ?? null;
                }
            } catch (Throwable $e) {
                $webhookInfo = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'config' => [
                'bot_token_set' => ! empty($botToken),
                'chat_id_set' => ! empty($chatId),
                'callback_enabled' => $config['callback']['enabled'] ?? false,
            ],
            'callbacks' => [
                'configured' => $registered,
                'missing_classes' => $missing,
            ],
            'webhook' => $webhookInfo,
        ]);
    }
}
