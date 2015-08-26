<?php

namespace Webuni\GitterBot;

use GuzzleHttp\Psr7\Response;

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
        //$this->bot->initialize();
        $loop = $this->bot->getClient()->getLoop();

        $stream = $this->gitter->getMessagesStream();

        /*$stream->then(function (Response $response) {
            echo 'BBB';
            $this->onGitterResponse($response);
        }, function (\Exception $e) {
            echo 'XXXXXXXXXXXX'.$e->getMessage();
        });*/

        $stream->on('response', function ($response) {
            $response->on('data', function ($data) {
                dump($data);
            });
            //$this->onGitterResponse($response);
        });
        $stream->end();

        $loop->run();
        return;

        $irc = $this->bot->getClient();
        $irc->on('irc.received', function ($message, $write, $connection, $logger) {
            $this->onIrcReceived($message);
        });
        $irc->on('irc.tick', function ($write, $connection, $logger) {
            $this->onIrcTick($write);
        });

        $this->bot->run();
    }

    private function onGitterResponse(Response $response)
    {
        $body = $response->getBody();
        $data = '';
        while (!$body->eof()) {
            $data .= $body->read(1024);
        }

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
