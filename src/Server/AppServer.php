<?php

namespace DyncIO\Server;

use Ratchet\App;
use Ratchet\ComponentInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Server as Reactor;
use Ratchet\Http\HttpServerInterface;
use Ratchet\Http\OriginCheck;
use Ratchet\Wamp\WampServerInterface;
use Ratchet\Server\IoServer;
use Ratchet\Server\FlashPolicy;
use Ratchet\Http\HttpServer;
use Ratchet\Http\Router;
use Ratchet\MessageComponentInterface;
use Ratchet\WebSocket\MessageComponentInterface as WsMessageComponentInterface;
use Ratchet\WebSocket\WsServer;
use Ratchet\Wamp\WampServer;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\UrlMatcher;

class AppServer extends App
{
    /**
     * Esse método foi reescrito para aceitar $httpHost vazio, que por sua vez desabilita a checagem do host
     * 
     * @param string        $httpHost   HTTP hostname clients intend to connect to. MUST match JS `new WebSocket('ws://$httpHost');`
     * @param int           $port       Port to listen on. If 80, assuming production, Flash on 843 otherwise expecting Flash to be proxied through 8843
     * @param string        $address    IP address to bind to. Default is localhost/proxy only. '0.0.0.0' for any machine.
     * @param LoopInterface $loop       Specific React\EventLoop to bind the application to. null will create one for you.
     */
    public function __construct($httpHost = 'localhost', $port = 8080, $address = '127.0.0.1', LoopInterface $loop = null)
    {
        if (extension_loaded('xdebug') && getenv('RATCHET_DISABLE_XDEBUG_WARN') === false) {
            trigger_error('XDebug extension detected. Remember to disable this if performance testing or going live!', E_USER_WARNING);
        }

        if (null === $loop) {
            $loop = LoopFactory::create();
        }

        $this->httpHost = $httpHost;
        $this->port = $port;

        $socket = new Reactor($address . ':' . $port, $loop);

        $this->routes  = new RouteCollection;
        $this->_server = new IoServer(new HttpServer(new Router(new UrlMatcher($this->routes, new RequestContext))), $socket, $loop);

        $policy = new FlashPolicy;
        if (!empty($httpHost)) {
            $policy->addAllowedAccess($httpHost, 80);
            $policy->addAllowedAccess($httpHost, $port);
        }

        if (80 == $port) {
            $flashUri = '0.0.0.0:843';
        } else {
            $flashUri = 8843;
        }
        $flashSock = new Reactor($flashUri, $loop);
        $this->flashServer = new IoServer($policy, $flashSock);
    }

    /**
     * Esse método foi reescrito para aceitar $httpHost vazio, que por sua vez desabilita a checagem do Origin
     * Add an endpoint/application to the server
     * 
     * @param string             $path The URI the client will connect to
     * @param ComponentInterface $controller Your application to server for the route. If not specified, assumed to be for a WebSocket
     * @param array              $allowedOrigins An array of hosts allowed to connect (same host by default), ['*'] for any
     * @param string             $httpHost Override the $httpHost variable provided in the __construct
     * @return ComponentInterface|WsServer
     */
    public function route($path, ComponentInterface $controller, array $allowedOrigins = [], $httpHost = null)
    {
        if ($controller instanceof HttpServerInterface || $controller instanceof WsServer) {
            $decorated = $controller;
        } elseif ($controller instanceof WampServerInterface) {
            $decorated = new WsServer(new WampServer($controller));
            $decorated->enableKeepAlive($this->_server->loop);
        } elseif ($controller instanceof MessageComponentInterface || $controller instanceof WsMessageComponentInterface) {
            $decorated = new WsServer($controller);
            $decorated->enableKeepAlive($this->_server->loop);
        } else {
            $decorated = $controller;
        }

        if ($httpHost === null) {
            $httpHost = $this->httpHost;
        }

        $allowedOrigins = array_values($allowedOrigins);
        if (0 === count($allowedOrigins)) {
            $allowedOrigins[] = $httpHost;
        }
        if ('*' !== $allowedOrigins[0]) {
            $decorated = new OriginCheck($decorated, $allowedOrigins);
        }

        //allow origins in flash policy server
        if (empty($this->flashServer) === false) {
            foreach($allowedOrigins as $allowedOrgin) {
                $this->flashServer->app->addAllowedAccess($allowedOrgin, $this->port);
            }
        }

        // Desabilita a checagem do Origin quando $httpHost for vazio
        $requirements = empty($this->httpHost) ? [] : ['Origin' => $this->httpHost];

        $this->routes->add('rr-' . ++$this->_routeCounter, new Route($path, ['_controller' => $decorated], $requirements, [], $httpHost, [], ['GET']));

        return $decorated;
    }

    public function getLoop(): \React\EventLoop\LoopInterface
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