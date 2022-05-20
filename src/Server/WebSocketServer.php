<?php

namespace DyncIO\Server;

use DyncIO\Client\ChannelManager;
use SplObjectStorage;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

use DyncIO\Client\WebSocketClient;

class WebSocketServer implements MessageComponentInterface
{

    /** @var SplObjectStorage */
    protected $clients;

    /** @var ChannelManager */
    protected $channelManager;

    public function __construct()
    {
        $this->clients        = new SplObjectStorage;
        $this->channelManager = new ChannelManager;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        global $config;

        // Cria o novo
        $client = new WebSocketClient($this->channelManager, $conn);
    
        // Adiciona a lista de clientes conectados
        $this->clients->attach($client);
        
        // Obtém os parâmetros da conexão
        parse_str($conn->httpRequest->getUri()->getQuery(), $params);
        $clientProtocolVersion = +$params['protocol'];
        $clientAppKey          = $params['path'];

        // Valida a versão do cliente
        if ($clientProtocolVersion < 5 || $clientProtocolVersion > 7) {
            $client->send([
                'event' => 'pusher:error',
                'data'  => ['code' => 4007, 'message' => "Protocol {$clientProtocolVersion} not supported"]
            ]);
            $client->close();
        }
        // Valida a chave da aplicação
        elseif ($clientAppKey != $config['app.key']) {
            $client->send([
                'event' => 'pusher:error',
                'data'  => ['code' => 4001, 'message' => "App key {$clientAppKey} not in this cluster. Did you forget to specify the cluster?"]
            ]);
            $client->close();
        }
        // Conexão realizada com sucesso
        else {
            $client->send([
                'event' => 'pusher:connection_established',
                'data'  => ['socket_id' => $client->getId(), 'activity_timeout' => 120]
            ]);
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $client = $this->getClient($from);

        if (is_null($client)) {
            echo "Cliente não encontrado: {$msg}\n";
            return;
        } elseif (null === ($json = @json_decode($msg))) {
            echo sprintf("[%s] Enviou uma mensagem em um formato desconhecido: %s\n", $client->getId(), $msg);
            return;
        } elseif (!isset($json->event)) {
            echo sprintf("[%s] Enviou uma mensagem inválida: %s\n", $client->getId(), $msg);
            return;
        }

        $client->handler($json);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $client = $this->getClient($conn);

        if (is_null($client)) {
            return;
        }

        $client->dispose();
        $this->clients->detach($client);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Erro {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * @return WebSocketClient
     */
    public function getClient(ConnectionInterface &$conn)
    {
        foreach ($this->clients as $client) {
            if ($conn === $client->getConn()) {
                return $client;
            }
        }

        return null;
    }
}