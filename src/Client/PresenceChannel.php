<?php

namespace DyncIO\Client;

use InvalidArgumentException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

use DyncIO\Client\PrivateChannel;
use Exception;

/**
 * 
 * @see https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol/#presence-channel-events
 * @package DyncIO\Client
 */
class PresenceChannel extends PrivateChannel
{
    protected mixed $clientsData = [];

    /**
     * @param WebSocketClient $conn
     * @return $this 
     */
    public function subscribe(WebSocketClient $client, $data)
    {
        // Valida o $data
        if (null === ($json = @json_decode($data->channel_data))) {
            throw new InvalidArgumentException(sprintf('Os dados do usuário %s não é JSON válido', $client->getId()));
        } elseif (!property_exists($json, 'user_id')) {
            throw new InvalidArgumentException(sprintf('Os dados enviados não possuem "user_id" do usuário %s', $client->getId()));
        } elseif (!property_exists($json, 'user_info')) {
            throw new InvalidArgumentException(sprintf('Os dados enviados não possuem "user_info" do usuário %s', $client->getId()));
        }

        // Adiciona a conexão a lista de inscritos
        parent::subscribe($client, $data);

        // Guarda os dados do cliente deste canal
        $this->clientsData[$client->getId()] = $json;

        // Cria a lista de eventos a serem enviados para o Webhook
        $events = [];
        if (1 === $this->subscribers->count()) {
            $events[] = ['name' => 'channel_occupied', 'channel' => $this->getName()];
        }

        foreach ($this->subscribers as $subscriber) {
            // Ignora o usuário que acabou de entrar
            if ($subscriber->getId() == $client->getId()) {
                continue;
            }

            //  {"event":"pusher_internal:member_added","data":"{\"user_id\":\"1637699463\",\"user_info\":{\"dados_inuteis\":true}}","channel":"presence-canal"}
            $subscriber->send([
                'event'   => 'pusher_internal:member_added',
                'channel' => $this->getName(),
                'data' => [
                    'user_id'   => $json->user_id,
                    'user_info' => $json->user_info
                ]
            ]);
        }

        $events[] = ['name' => 'member_added', 'channel' => $this->getName(), 'user_id' => $json->user_id];

        $this->notifyWebhook($events);

        // Retorna os dados do canal a serem enviados para o cliente
        return [
            'presence' => $this->getChannelData()
        ];
    }

    /**
     * @param WebSocketClient $conn
     * @return $this 
     */
    public function unsubscribe(WebSocketClient $client)
    {
        parent::unsubscribe($client);

        if (!array_key_exists($client->getId(), $this->clientsData)) {
            echo sprintf("[%s] Tentou sair do canal %s, sem estar nele\n", $client->getId(), $this->getName());
        }

        // Remove os dados do cliente deste canal
        $userInfo = $this->clientsData[$client->getId()];
        unset($this->clientsData[$client->getId()]);

        // Cria a lista de eventos a serem enviados para o Webhook
        $events = [
            ['name' => 'member_removed', 'channel' => $this->getName(), 'user_id' => $userInfo->user_id]
        ];

        // Se tiver vazio, avisa que o canal ficou vazio
        if (0 === $this->subscribers->count()) {
            $events[] = ['name' => 'channel_vacated', 'channel' => $this->getName()];
        } else {
            // {"event":"pusher_internal:member_removed","data":"{\"user_id\":\"1637699463\"}","channel":"presence-canal"}
            foreach ($this->subscribers as $subscriber) {
                $subscriber->send([
                    'event'   => 'pusher_internal:member_removed',
                    'channel' => $this->getName(),
                    'data' => [
                        'user_id' => $userInfo->user_id
                    ]
                ]);
            }
        }
        $this->notifyWebhook($events);

        return [];
    }

    protected function notifyWebhook($events)
    {
        // Se "webhook.host" for null ou false, é pq está desabilitado
        if (!$this->webhookHost) {
            return;
        }

        $body = json_encode([
            'time'   => time(),
            'events' => $events
        ]);

        $signature = hash_hmac('sha256', $body, $this->appSecret, false);

        $request = new Request('POST', $this->webhookHost, [
            'Content-Type'       => 'application/json',
            'X-Pusher-Key'       => $this->appKey,
            'X-Pusher-Signature' => $signature,
        ], $body);

        $client = new Client;

        // Envia a requisição sem esperar pela resposta
        $promise = $client->sendAsync($request);
        $promise->then();
    }

    protected function getChannelData()
    {
        $ids = [];
        foreach ($this->clientsData as $data) {
            $ids[$data->user_id] = $data->user_info;
        }

        return [
            'count' => count($this->subscribers),
            'ids'   => array_keys($this->clientsData),
            'hash'  => $ids,
        ];
    }
}