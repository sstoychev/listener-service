<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Predis\Client;

// Make sure composer dependencies have been installed
require __DIR__ . '/vendor/autoload.php';

/**
 * leaderboard.php
 * Ignore all messages from outside
 */
class Leaderboard implements MessageComponentInterface {
    protected $clients;
    protected $predis;
    public $config;

    const LEADERBOARD = 'leaderboard';

    public function __construct(array $config) {
        $this->clients = new \SplObjectStorage;
        $this->predis = new Client();
        $this->config = $config;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
    }

    // Ignore all incomming messages. Clients should not be sending any messages
    // The only accepted messages are from the local python script to notify
    // that there has been update on the scores and we should get the new top
    // players and send them to the clients
    public function onMessage(ConnectionInterface $from, $msg) {
        if ($from->remoteAddress == $this->config['local_address']) {
            $topPlayers = $this->predis->zrevrange(self::LEADERBOARD, 0, 10, 'WITHSCORES');

            $data = [
                self::LEADERBOARD => $topPlayers
            ];

            $msg = json_encode($data);
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


// we need the settings in ini so they can be read by python too
$config = parse_ini_file("config.ini");
$leaderboard = new Leaderboard($config);

// Run the server application through the WebSocket protocol on port 8080
$app = new Ratchet\App($config['listen_url'], $config['local_port'], '0.0.0.0');
$app->route('/'.Leaderboard::LEADERBOARD, $leaderboard, array('*'));
$app->run();
