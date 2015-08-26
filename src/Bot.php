<?php

namespace Webuni\GitterBot;

use Phergie\Irc\Bot\React\Bot as BaseBot;
use Phergie\Irc\Client\React\Client;
use Phergie\Irc\Connection;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

class Bot extends BaseBot
{
    private $nick;
    private $channel;
    private $loop;

    public function __construct($nick, $channel, LoopInterface $loop, LoggerInterface $logger)
    {
        $this->nick = $nick;
        $this->channel = $channel;
        $this->loop = $loop;

        $this->setConfig([
            'connections' => [
                new Connection([
                    'serverHostname' => 'irc.freenode.net',
                    'username' => $nick,
                    'realname' => $nick,
                    'nickname' => $nick,
                ])
            ],
            'plugins' => [
                new \Phergie\Irc\Plugin\React\AutoJoin\Plugin(['channels' => [$channel]]),
                new \EnebeNb\Phergie\Plugin\AutoRejoin\Plugin(['channels' => [$channel]]),
                new \Phergie\Irc\Plugin\React\Pong\Plugin(),
            ]
        ]);

        $this->setLogger($logger);
    }

    public function getChannel()
    {
        return $this->channel;
    }

    public function getNick()
    {
        return $this->nick;
    }

    public function getClient()
    {
        if (null === $this->client) {
            $client = new Client();
            $client->setLogger($this->getLogger());
            $client->setLoop($this->loop);
            $this->setClient($client);
        }

        return $this->client;
    }
}
