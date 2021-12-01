<?php

namespace DyncIO\Server;

use Exception;
use Psr\Http\Message\RequestInterface;
use Pusher\Pusher;
use Pusher\PusherException;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Symfony\Component\HttpFoundation\Response;


class TestServer implements HttpServerInterface
{
    /** @var \Pusher\Pusher */
    protected $pusher;

    public function __construct()
    {
        global $config;

        $this->pusher = new Pusher($config['app.key'], $config['app.secret'], 1, [
            'host' => '127.0.0.1',
            'port' => $config['network.port'],
            'useTLS' => false
        ]);
    }

    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null)
    {
        if ($request->getMethod() == 'POST') {
            parse_str($request->getBody(), $form);
        
            try {
                if (strpos($form['channel_name'], 'presence-') === 0) {
                    $output = $this->pusher->presenceAuth(
                        $form['channel_name'],
                        $form['socket_id'],
                        'user-' . time(),
                        [
                            'dados_inuteis' => $form['socket_id']
                        ]
                    );
                } else {
                    $output = $this->pusher->socketAuth($form['channel_name'], $form['socket_id']);
                }
                
                $conn->send(new Response($output, 200, [
                    'Content-Type' => 'application/javascript'
                ]));
            } catch (PusherException $e) {
                $conn->send(new Response($e->getMessage(), 200));
            }

            $conn->close();
        } else {
            $conn->send(new Response('NOTHING', 200));
            $conn->close();
        }

    }

    public function onClose(ConnectionInterface $conn)
    { }

    public function onError(ConnectionInterface $conn, Exception $e)
    { }

    public function onMessage(ConnectionInterface $from, $msg)
    { }
    
}