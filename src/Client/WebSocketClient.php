<?php

namespace DyncIO\Client;

use InvalidArgumentException;
use Ratchet\ConnectionInterface;

class WebSocketClient
{
    /** @var ConnectionInterface */
    protected $conn;

    /** @var string */
    protected $id;

    /** @var ChannelManager */
    protected $channelManager;

    public function __construct(ChannelManager $channelManager, ConnectionInterface $conn)
    {
        $this->id             = random_id();
        $this->conn           = $conn;
        $this->channelManager = $channelManager;
        
        echo "[{$this->id}] Conectado\n";
    }

    public function getConn()
    {
        return $this->conn;
    }

    public function getId()
    {
        return $this->id;
    }

    public function send($msg)
    {
        if (is_array($msg)) {
            // https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol/#double-encoding
            if (isset($msg['data'])) {
                $msg['data'] = json_encode($msg['data']);
            }

            $this->conn->send(json_encode($msg));
        } elseif (is_string($msg)) {
            $this->conn->send($msg);
        } else {
            throw new InvalidArgumentException('A $msg deve ser um string ou uma array');
        }
    }

    public function close()
    {
        $this->conn->close();
        $this->dispose();
    }

    public function dispose()
    {
        $this->channelManager->unsubscribeAll($this);

        echo "[{$this->id}] Desconectado\n";

        $this->conn           = null;
        $this->connId         = null;
        $this->channelManager = null;
    }

    public function handler($msg)
    {
        echo sprintf("[%s] Recebido: %s\n", $this->id, json_encode($msg));

        switch ($msg->event) {
            case 'pusher:ping':
                $this->onPing();
                break;
            case 'pusher:subscribe':
                $this->onSubscribe($msg);
                break;
            case 'pusher:unsubscribe':
                $this->onUnsubscribe($msg);
                break;
        }
    }

    protected function onPing()
    {
        $this->send([
            'event' => 'pusher:pong',
            'data'  => []
        ]);
    }

    protected function onSubscribe($msg)
    {
        echo sprintf("[%s] Entrou no canal: %s\n", $this->id, $msg->data->channel);

        $data = $this->channelManager->add($this, $msg->data);

        $this->send([
            'event'   => 'pusher_internal:subscription_succeeded',
            'data'    => $data,
            'channel' => $msg->data->channel
        ]);
    }

    protected function onUnsubscribe($msg)
    {
        echo sprintf("[%s] Saiu no canal: %s\n", $this->id, $msg->data->channel);

        $channel = $this->channelManager->get($msg->data->channel);
        $this->channelManager->unsubscribe($channel, $this);
    }
}