<?php

namespace DyncIO\Server;

use Exception;
use Psr\Http\Message\RequestInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Symfony\Component\HttpFoundation\Response;

class AssetsServer implements HttpServerInterface
{

    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null)
    {
        global $config;
        $urlPath = parse_url($request->getUri(), PHP_URL_PATH);
        if (!empty($config['network.path'])) {
            $urlPath = str_replace($config['network.path'], '', $urlPath);
        }

        echo "[assets] Enviando arquivo {$urlPath}\n";
        $filePath = public_path($urlPath);

        if ($request->getMethod() != 'GET') {
            $conn->send(new Response('', 400, [
                'Content-Type' => 'text/html; charset=utf-8'
            ]));
        } elseif (!file_exists($filePath)) {
            $conn->send(new Response(sprintf('File not found: %s', $urlPath), 404, [
                'Content-Type' => 'text/html; charset=utf-8'
            ]));
        } else {
            $contents = file_get_contents($filePath);
            $mimeType = $this->getMimeType($filePath);

            $conn->send(new Response($contents, 200, [
                'Content-Type' => $mimeType
            ]));
        }

        $conn->close();
    }

    public function onClose(ConnectionInterface $conn)
    { }

    public function onError(ConnectionInterface $conn, Exception $e)
    { }

    public function onMessage(ConnectionInterface $from, $msg)
    { }

    private function getMimeType($file)
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        return match ($extension) {
            'js' => 'application/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            default => 'text/html; charset=utf-8',
        };
    }
    
}