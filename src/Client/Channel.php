<?php

namespace DyncIO\Client;

use Countable;
use SplObjectStorage;
use IteratorAggregate;

class Channel implements IteratorAggregate, Countable
{
    protected string $name;

    protected SplObjectStorage $subscribers;

    protected string $appKey;

    protected string $appSecret;

    protected string $webhookHost;

    public function __construct($name)
    {
        global $config;
        
        $this->name        = $name;
        $this->subscribers = new SplObjectStorage;

        $this->appKey      = $config['app.key'];
        $this->appSecret   = $config['app.secret'];
        $this->webhookHost = $config['webhook.host'];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getIterator()
    {
        return $this->subscribers;
    }

    public function count()
    {
        return $this->subscribers->count();
    }

    public function isEmpty()
    {
        return $this->subscribers->count() === 0;
    }

    /**
     * @param  WebSocketClient $client
     * @return boolean
     */
    public function has(WebSocketClient $client)
    {
        return $this->subscribers->contains($client);
    }

    /**
     * @param WebSocketClient $client
     * @return mixed[]
     */
    public function subscribe(WebSocketClient $client, $data)
    {
        $this->subscribers->attach($client);
        echo sprintf("[%s] Adicionado ao canal %s\n", $client->getId(), $this->getName());

        return [];
    }

    /**
     * @param WampConnection $conn
     * @return $this 
     */
    public function unsubscribe(WebSocketClient $client)
    {
        if ($this->has($client)) {
            $this->subscribers->detach($client);
            echo sprintf("[%s] Removido do canal %s\n", $client->getId(), $this->getName());
        }
        
        return $this;
    }
    
    /**
     * 
     * @param mixed $msg 
     * @param array $exclude
     * @return $this 
     */
    public function broadcast($msg, $exclude = [])
    {
        $useExclude = !empty($exclude);
        foreach ($this->subscribers as $client) {
            if ($useExclude && in_array($client, $exclude)) {
                continue;
            }

            $client->send($msg);
        }

        return $this;
    }
}