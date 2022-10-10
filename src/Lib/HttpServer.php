<?php
namespace DyncIO\Lib;

use Ratchet\Http\HttpServer as RatchetHttpServer;
use Ratchet\Http\HttpServerInterface;

class HttpServer extends RatchetHttpServer {
    
    public function __construct(HttpServerInterface $component)
    {
        parent::__construct($component);
        $this->_reqParser->maxSize = 1024 * 1024;
    }
}