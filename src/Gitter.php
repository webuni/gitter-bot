<?php

namespace Webuni\GitterBot;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

use React\Dns\Resolver\Factory as DnsFactory;
use React\HttpClient\Factory as HttpFactory;

class Gitter
{
    private $client;
    private $react;
    private $token;
    private $room;
    private $logger;

    public function __construct($token, $room, LoopInterface $loop, LoggerInterface $logger)
    {
        $this->token = $token;
        $this->room = $room;
        $this->logger = $logger;

        $handlerStack = HandlerStack::create(new HttpClientAdapter($loop));
        $handlerStack->push(Middleware::log($logger, new MessageFormatter(MessageFormatter::SHORT)));
        $this->client = new Client([
            'base_uri' => 'https://api.gitter.im',
            'headers' => $this->getHeaders(),
            'handler' => $handlerStack,
        ]);

        $this->react = (new HttpFactory())->create($loop, (new DnsFactory())->createCached('8.8.8.8', $loop));
    }

    public function getRoom()
    {
        $rooms = json_decode($this->client->get('/v1/rooms')->getBody());
        foreach ($rooms as $room) {
            if ($this->room === $room->name) {
                return $room;
            }
        }

        throw new \InvalidArgumentException('Unable to get room "'.$this->room.'".');
    }

    public function getUser()
    {
        $users = json_decode($this->client->get('/v1/user')->getBody());

        return reset($users);
    }

    public function postMessage($room, $text)
    {
        return json_decode($this->client->post('/v1/rooms/'.$room->id.'/chatMessages', ['form_params' => ['text' => $text]])->getBody());
    }

    public function getMessagesStream()
    {
        return $this->react->request('GET', 'https://stream.gitter.im/v1/rooms/'.$this->getRoom()->id.'/chatMessages', $this->getHeaders());

        //dump('https://stream.gitter.im/v1/rooms/'.$this->getRoom()->id.'/chatMessages'); exit;
        //return $this->client->getAsync('https://stream.gitter.im/v1/rooms/'.$this->getRoom()->id.'/chatMessages', ['stream' => true]);
    }

    private function getHeaders()
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ];
    }
}
