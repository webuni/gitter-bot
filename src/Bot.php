<?php

namespace Webuni\GitterBot;

use Phergie\Irc\Bot\React\Bot as BaseBot;
use Phergie\Irc\Connection;
use React\EventLoop\LoopInterface;

class Bot extends BaseBot
{
    private $nick;
    private $channel;

    public function __construct($nick, $channel)
    {
        $this->nick = $nick;
        $this->channel = $channel;
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
                new \Phergie\Irc\Plugin\React\Pong\Plugin(),
            ]
        ]);
    }

    public function getChannel()
    {
        return $this->channel;
    }

    public function getNick()
    {
        return $this->nick;
    }

    /**
     * @return LoopInterface
     */
    public function getLoop()
    {
        return $this->getClient()->getLoop();
    }

    public function initialize()
    {
        $this->setDependencyOverrides($this->config);
        $this->getPlugins($this->config);

        $client = $this->getClient();
        foreach ($this->getConnections($this->config) as $connection) {
            $client->addConnection($connection);
        }
    }
}
