<?php

namespace DyncIO\Server;

use Ratchet\App;
use Ratchet\Server\IoServer;

class AppServer extends App
{

    /**
     * @return \React\EventLoop\LoopInterface
     */
    public function getLoop()
    {
        return $this->_server->loop;
    }

    // public function setupWebhook()
    // {
    //     global $config;

    //     $this->webhookHost    = $config->{'webhook.host'};
    //     $this->webhookDelay   = $config->{'webhook.delay'};
    //     $this->webhookTimeout = $config->{'webhook.timeout'};

    //     if (!empty($this->webhookHost) && $this->webhookDelay > 0) {
    //         $this->createWebhookTimer();
    //     }
    // }

    // public function createWebhookTimer()
    // {
    //     $this->getLoop()->addPeriodicTimer(5, function () {
    //         echo time() . "\n";
    //     });
    // }
}