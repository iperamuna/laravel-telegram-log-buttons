<?php

namespace Iperamuna\TelegramLog\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Iperamuna\TelegramLog\Support\TelegramCallbackRegistry;
use Symfony\Component\HttpFoundation\Response;

class TelegramCallbackController extends Controller
{
    public function __invoke(Request $request, TelegramCallbackRegistry $registry)
    {
        $config = config('telegram-log.callback', []);

        if (! ($config['enabled'] ?? true)) {
            abort(404);
        }

        if (! empty($config['secret'])) {
            $secret = $request->header('X-Telegram-Callback-Secret');

            if ($secret !== $config['secret']) {
                return response()->json(['ok' => false, 'error' => 'Invalid secret'], Response::HTTP_FORBIDDEN);
            }
        }

        $update = $request->all();
        $data = $update['callback_query']['data'] ?? null;

        if (! is_string($data)) {
            return response()->json(['ok' => false, 'error' => 'No callback data'], Response::HTTP_OK);
        }

        $parts = explode(':', $data, 2);
        $action = $parts[0] ?? $data;

        $handler = $registry->resolve($action);

        if ($handler) {
            $handler($update, $data);
        }

        return response()->json(['ok' => true]);
    }
}
