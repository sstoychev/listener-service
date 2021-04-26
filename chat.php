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
        $this->predis = new Client();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Opened connection, Total:".$this->clients->count()."\n";
    }

    // Ignore all incomming messages. Clients should not be sending any messages
    // The only accepted messages are from the local python script to notify
    // that there has been update on the scores and we should get the new top
    // players and send them to the clients
    public function onMessage(ConnectionInterface $from, $msg) {
        echo 'Message from:'. $from->remoteAddress . "-" . $msg . "\n";
        if ($from->remoteAddress == '62.171.147.115') {
            $topPlayers = $this->predis->zrevrange('leaderboard', 0, 10, 'WITHSCORES');
            $msg = print_r($topPlayers, true);
            echo $msg;
            foreach ($this->clients as $client) {
                if ($from != $client) {
                    $client->send($msg);
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }
}

$myChat = new MyChat;

// Run the server application through the WebSocket protocol on port 8080
$app = new Ratchet\App('listener-service.dtl.name', 8080, '0.0.0.0');
$app->route('/chat', $myChat, array('*'));
$app->route('/echo', new Ratchet\Server\EchoServer, array('*'));
echo "Starting chat\n";
$app->run();
