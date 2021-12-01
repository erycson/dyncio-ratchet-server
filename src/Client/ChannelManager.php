<?php

namespace DyncIO\Client;

use InvalidArgumentException;

use DyncIO\Client\Channel;
use DyncIO\Client\PrivateChannel;
use DyncIO\Client\PresenceChannel;

class ChannelManager
{
    protected static $channels = [];

    public function get($name)
    {
        if (array_key_exists($name, self::$channels)) {
            return self::$channels[$name];
        } elseif (strpos($name, 'private-') === 0) {
            echo "[server] Criando canal privado: {$name}\n";
            return self::$channels[$name] = new PrivateChannel($name);
        } elseif (strpos($name, 'presence-') === 0) {
            echo "[server] Criando canal presencial: {$name}\n";
            return self::$channels[$name] = new PresenceChannel($name);
        } else {
            echo "[server] Criando canal publico: {$name}\n";
            return self::$channels[$name] = new Channel($name);
        }
    }

    /**
     * Remove o usuário de todos os canais no qual ele está inscrito
     * 
     * @param WebSocketClient $client 
     * @return $this 
     */
    public function unsubscribeAll(WebSocketClient $client)
    {
        foreach (self::$channels as $channel) {
            $this->unsubscribe($channel, $client);
            $this->removeIfEmpty($channel);
        }

        return $this;
    }

    /**
     * Inscreve um cliente em um canal
     * 
     * @param WebSocketClient $client 
     * @param string $channelName
     * @return mixed[]
     */
    public function add(WebSocketClient $client, $data)
    {
        $channel = $this->get($data->channel);

        try {
            if (!$channel->has($client)) {
                return $channel->subscribe($client, $data);
            }
        } catch (InvalidArgumentException) {
            $client->close();
        }

        return [];
    }

    /**
     * Remove a inscrição de um cliente a um canal
     * 
     * @param WebSocketClient $client
     * @param mixed $channelName 
     * @return $this 
     */
    public function unsubscribe(Channel $channel, WebSocketClient $client)
    {
        if (!is_null($client) && $channel->has($client)) {
            $channel->unsubscribe($client);
        }

        $this->removeIfEmpty($channel);
    }

    /**
     * Remove a inscrição de um cliente a um canal
     * 
     * @param WebSocketClient $client
     * @param mixed $channelName 
     * @return $this 
     */
    public function removeIfEmpty(Channel $channel)
    {
        if ($channel->isEmpty()) {
            echo "[server] Removendo o canal vazio: {$channel->getName()}\n";
            unset(self::$channels[$channel->getName()]);
        }
    }
}