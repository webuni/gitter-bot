<?php

namespace Webuni\GitterBot;

use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\HttpClient\Response;

class GitterIrcSynchronizer
{
    private $bot;
    private $gitter;
    private $loop;
    private $logger;
    private $queue;
    private $lastGitterTick;
    private $gitterThreshold = 60;

    public function __construct(Bot $bot, Gitter $gitter, LoopInterface $loop, LoggerInterface $logger)
    {
        $this->queue = new \SplQueue();
        $this->queue->setIteratorMode(\SplQueue::IT_MODE_DELETE);

        $this->bot = $bot;
        $this->gitter = $gitter;
        $this->loop = $loop;
        $this->logger = $logger;
    }

    public function run()
    {
        $this->runGitter();
        $this->lastGitterTick = time();
        $this->loop->addPeriodicTimer($this->gitterThreshold, function() {
            if ((time() - $this->lastGitterTick) > $this->gitterThreshold) {
                $this->runGitter();
            }
        });

        $irc = $this->bot->getClient();
        $irc->on('irc.received', function ($message, $write, $connection, $logger) {
            $this->onIrcReceived($message);
        });
        $irc->on('irc.tick', function ($write, $connection, $logger) {
            $this->onIrcTick($write);
        });

        $this->bot->run();
    }

    private function onGitterData($data, Response $response)
    {
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
        $this->logger->info('Gitter write message {message}', ['message' => $text]);
    }

    private function onIrcTick($write)
    {
        $channel = $this->bot->getChannel();
        foreach ($this->queue as $data) {
            if (!isset($data->text) || !isset($data->fromUser->username)) {
                continue;
            }

            foreach (preg_split('/(\r\n|\n|\r)/', $data->text) as $line) {
                $message = sprintf('(%s): %s', $data->fromUser->username, $line);
                $write->ircPrivmsg($channel, $message);
                $this->logger->info('IRC write to channel "{channel}" message "{message}."', ['channel' => $channel, 'message' => $message]);
            }
        }
    }

    private function runGitter()
    {
        $stream = $this->gitter->getMessagesStream();
        $stream->on('response', function (Response $response) {
            $this->lastGitterTick = time();
            $this->logger->info('Gitter response');
            $response->on('data', function ($data, Response $response) {
                $this->lastGitterTick = time();
                $this->logger->info('Gitter data {data}', ['data' => $data]);
                $this->onGitterData($data, $response);
            });
            $response->on('error', function () {
                $this->logger->error('Gitter response error');
            });
            $response->on('end', function () {
                $this->logger->info('Gitter response end');
            });
        });
        $stream->on('error', function() {
            $this->logger->error('Gitter error');
        });
        $stream->on('end', function() {
            $this->logger->info('Gitter end');
        });
        $stream->end();
    }
}
