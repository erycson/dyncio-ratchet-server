<?php

namespace DyncIO\Api;

use Exception;
use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Symfony\Component\HttpFoundation\Response;

use DyncIO\Client\ChannelManager;

class EventServer implements HttpServerInterface
{
    /** @var ChannelManager */
    protected $channelManager;

    public function __construct()
    {
        $this->channelManager = new ChannelManager();
    }

    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null)
    {
        $body = json_decode($request->getBody(), true);

        $channels = [];
        foreach ($body['channels'] as $channelName) {
            $channel = $this->channelManager->get($channelName);
            $channels[$channelName] = $channel->count();

            // Se tiver alguém, envia a atualização para esse canal
            if ($channel->count() > 0) {
                $channel->broadcast([
                    "channel" => $channelName,
                    "event"   => $body['name'],
                    "data"    => $body['data'],
                ]);
    
                echo sprintf(
                    "[api] Canal '%s', Evento '%s', Mensagem: %s\n",
                    $channelName,
                    $body['name'],
                    $body['data']
                );
            }
            // Se não tiver ninguém, remove o canal
            else {
                $this->channelManager->removeIfEmpty($channel);
            }
        }

        $response = json_encode([
            'channels' => $channels
        ]);

        $conn->send(new Response($response, 200, [
            'Content-Type' => 'text/html; charset=utf-8'
        ]));

        $conn->close();
    }

    public function onClose(ConnectionInterface $conn)
    { }

    public function onError(ConnectionInterface $conn, Exception $e)
    { }

    public function onMessage(ConnectionInterface $from, $msg)
    { }
    
}