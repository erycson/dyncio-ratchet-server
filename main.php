<?php
require_once 'vendor/autoload.php';

use DyncIO\Api\EventServer;
use DyncIO\Server\AppServer;
use DyncIO\Server\TestServer;
use DyncIO\Server\AssetsServer;
use DyncIO\Server\WebSocketServer;

function public_path($path = '') {
    return sprintf('%s/public/%s', __DIR__, ltrim($path, '/'));
}

function random_id() {
    return sprintf(
        '%d.%d',
        unpack('N', random_bytes(4))[1],
        unpack('N', random_bytes(4))[1]
    );
}

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

cli_set_process_title("dyncio-ratchet-server {$config['network.host']}:{$config['network.port']}");

if ($config['network.path'] == '/') {
    throw new Exception('A configuração "network.path" não pode ser igual a "/", deixe nulo/vazio ou escreve um caminho');
}

$server = new AppServer($config['network.host'], $config['network.port'], '0.0.0.0');
$server->route("{$config['network.path']}/apps/{app_id}/events", new EventServer, ['*']);
$server->route("{$config['network.path']}/webhook", new TestServer, ['*']);

$server->route("{$config['network.path']}/app/{path<.*>}", new WebSocketServer, ['*']);
$server->route("{$config['network.path']}/{path<.*>}", new AssetsServer, ['*']);

// Adiciona o método POST a API
$server->routes->get('rr-1')->setMethods(['POST']);
$server->routes->get('rr-2')->setMethods(['POST']);

echo "[sistema] Servidor Iniciado: {$config['network.host']}:{$config['network.port']}\n";
$server->run();

/**
 * Sobre SSL, veja:
 * @see https://stackoverflow.com/questions/16979793/php-ratchet-websocket-ssl-connect
 * @see https://github.com/ratchetphp/Ratchet/issues/489#issuecomment-457714221
 */
