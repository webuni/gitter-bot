<?php

namespace Webuni\GitterBot;

class GitterIrcSynchronizer
{
    private $queue;
    private $bot;
    private $gitter;

    public function __construct(Bot $bot, Gitter $gitter)
    {
        $this->queue = new \SplQueue();
        $this->queue->setIteratorMode(\SplQueue::IT_MODE_DELETE);

        $this->bot = $bot;
        $this->gitter = $gitter;
    }

    public function run()
    {
        $this->bot->initialize();
        $loop = $this->bot->getLoop();

        $request = $this->gitter->getMessagesStream($loop);
        $request->on('response', function ($response) {
            $this->onGitterResponse($response);
        });
        $request->end();

        $irc = $this->bot->getClient();
        $irc->on('irc.received', function ($message, $write, $connection, $logger) {
            $this->onIrcReceived($message);
        });
        $irc->on('irc.tick', function ($write, $connection, $logger) {
            $this->onIrcTick($write);
        });

        $loop->run();
    }

    private function onGitterResponse($response)
    {
        $response->on('data', function ($data) {
            static $user;
            if (null === $user) {
                $user = $this->gitter->getUser();
            }

            if (!trim($data)) {
                return;
            }

            $data = json_decode($data);
            if (!isset($data->fromUser->username) || $user->username == $data->fromUser->username) {
                return;
            }

            $this->queue->enqueue($data);
        });
    }

    private function onIrcReceived($message)
    {
        if ('PRIVMSG' !== $message['command']) {
            return;
        }

        if (!in_array($this->bot->getChannel(), $message['targets'], true)) {
            return;
        }

        if ($this->bot->getNick() === $message['nick']) {
            return;
        }

        static $room;
        if (null === $room) {
            $room = $this->gitter->getRoom();
        }

        $text = sprintf('*%s*: %s', $message['nick'], $message['params']['text']);
        $this->gitter->postMessage($room, $text);
    }

    private function onIrcTick($write)
    {
        foreach ($this->queue as $data) {
            if (!isset($data->text) || !isset($data->fromUser->username)) {
                continue;
            }

            foreach (preg_split('/(\r\n|\n|\r)/', $data->text) as $line) {
                $message = sprintf('(%s): %s', $data->fromUser->username, $line);
                $write->ircPrivmsg($this->bot->getChannel(), $message);
            }
        }
    }
}
