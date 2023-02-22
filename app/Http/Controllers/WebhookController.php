<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use LINE\LINEBot;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\Event\MessageEvent\TextMessage;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    function handleEvents(Request $request) {
        $channelToken = env('LINE_CHANNEL_ACCESS_TOKEN');
        $channelSecret = env('LINE_CHANNEL_SECRET');

        $httpClient = new CurlHTTPClient($channelToken);
        $bot = new LINEBot($httpClient, ['channelSecret' => $channelSecret]);

        $signature = $request->header(HTTPHeader::LINE_SIGNATURE);
        if (!$signature) {
            return response()->json(null, Response::HTTP_BAD_REQUEST);
        }

        try {
            $events = $bot->parseEventRequest($request->getContent(), $signature);
        } catch (Exception) {
            return response()->json(null, Response::HTTP_BAD_REQUEST);
        }

        collect($events)
            ->filter(fn ($event) => $event instanceof TextMessage)
            ->each(function ($event) use ($bot) {
                $replyText = $event->getText();
                try {
                    $bot->replyText($event->getReplyToken(), $replyText);
                } catch (Exception) {
                    response()->json(null, Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            });

        return response()->json([], Response::HTTP_OK);
    }
}
