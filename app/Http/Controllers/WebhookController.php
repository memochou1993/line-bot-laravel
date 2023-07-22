<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\ReplyMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;
use LINE\Constants\HTTPHeader;
use LINE\Constants\MessageType;
use LINE\Parser\EventRequestParser;
use LINE\Parser\Exception\InvalidEventRequestException;
use LINE\Parser\Exception\InvalidSignatureException;
use LINE\Webhook\Model\MessageEvent;
use LINE\Webhook\Model\TextMessageContent;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $config = new Configuration();
        $config->setAccessToken(env('LINE_CHANNEL_ACCESS_TOKEN'));
        $client = new MessagingApiApi(new Client(), $config);

        $signature = $request->header(HTTPHeader::LINE_SIGNATURE);
        if (!$signature) {
            abort(Response::HTTP_BAD_REQUEST);
        }

        try {
            $secret = env('LINE_CHANNEL_SECRET');
            $parsedEvents = EventRequestParser::parseEventRequest($request->getContent(), $secret, $signature);
        } catch (InvalidSignatureException) {
            abort(Response::HTTP_BAD_REQUEST);
        } catch (InvalidEventRequestException) {
            abort(Response::HTTP_BAD_REQUEST);
        }

        collect($parsedEvents->getEvents())
            ->filter(fn ($event) => $event instanceof MessageEvent)
            ->filter(fn ($event) => $event->getMessage() instanceof TextMessageContent)
            ->each(function ($event) use ($client) {
                $replyText = $event->getMessage()->getText();

                $client->replyMessage(new ReplyMessageRequest([
                    'replyToken' => $event->getReplyToken(),
                    'messages' => [
                        new TextMessage([
                            'type' => MessageType::TEXT,
                            'text' => $replyText,
                        ]),
                    ],
                ]));
            });

        return response()->json(null);
    }
}
