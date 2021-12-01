# DyncIO - Pusher PHP Server

Implementação simples do servidor do protocolo Pusher usando a biblioteca Ratchet para tratar o WebSockets

Versão do cliente Pusher usada: v7.0.3

## Instalação

```bash
git clone https://github.com/erycson/pusher-ratchet-server.git
cd pusher-ratchet-server
composer install
```

## Configuração

Edite o arquivo `config.json` no servidor, e altere seguindo suas necessidades.

* `app.id`: ID da aplicação
* `app.key`: Chave publica da aplicação
* `app.secret`: Chave privada da aplicação
* `network.host`: IP por onde irá aceitar conexões
* `network.port`: Porta por onde aceitará as conexões
* `network.path`: Caminho base, muito usado caso esteja atrás de um proxy
* `webhook.host`: URL para onde será enviado os eventos do servidor

Já no cliente, lembre-se de adicionar `network.path` aos caminhos se você usou em algo, exemplo:

```js
<script src="http://127.0.0.1:9090/dyncio/assets/js/pusher.min.js?v=v7.0.3"></script>

var pusher = new Pusher('example', { // O mesmo que app.key
    wsHost: '127.0.0.1', // O mesmo que network.host
    wsPort: 9090,  // O mesmo que network.port
    wsPath: '/dyncio', // O mesmo que network.path
    forceTLS: false,
    enabledTransports: ["ws"],
    disabledTransports: ["flash"],
    authEndpoint: "/dyncio/webhook" // URL para autenticação das salas private- e presence-
});
```

Caso não queira implementar a autenticação, use /dyncio/webhook, pois esse servidor implementa de forma básica essa autenticação.

Caso queira implementar por si só, use o exemplo no arqquivo TestServer.php.


## Execução

```bash
php main.php
```
