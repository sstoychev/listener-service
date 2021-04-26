<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Predis\Client;

    // Make sure composer dependencies have been installed
    require __DIR__ . '/vendor/autoload.php';

/**
 * chat.php
 * Send any incoming messages to all connected clients (except sender)
 */
class MyChat implements MessageComponentInterface {
    protected $clients;
    protected $predis;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $predis = new Client();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Opened connection, Total:".$this->clients->count()."\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo 'Message from:'. $from->remoteAddress . "-" . $msg . "\n";
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }

    public function sendTopPlayers(Client $predis, $chan, $msg) {
        if ($chan != 'leaderboard') {
            return;
        }

        $topPlayers = $predis->zrevrangebyscore('leaderboard', 0, 10);
        $msg = print_r($topPlayers, true);
        foreach ($this->clients as $client) {
            $client->send($msg);
        }
    }
}

$myChat = new MyChat;

// Run the server application through the WebSocket protocol on port 8080
$app = new Ratchet\App('listener-service.dtl.name', 8080, '0.0.0.0');
$app->route('/chat', $myChat, array('*'));
$app->route('/echo', new Ratchet\Server\EchoServer, array('*'));
echo "Starting chat\n";
$app->run();
