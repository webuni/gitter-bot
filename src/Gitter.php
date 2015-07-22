<?php

namespace Webuni\GitterBot;

use GuzzleHttp\Client;
use React\Dns\Resolver\Factory as DnsFactory;
use React\EventLoop\LoopInterface;
use React\HttpClient\Factory as HttpFactory;

class Gitter
{
    private $guzzle;
    private $token;

    public function __construct($token, $room)
    {
        $this->token = $token;
        $this->room = $room;
    }

    public function getRoom()
    {
        $rooms = json_decode($this->getGuzzle()->get('/v1/rooms')->getBody());
        foreach ($rooms as $room) {
            if ($this->room === $room->name) {
                return $room;
            }
        }

        throw new \InvalidArgumentException('Unable to get room "'.$this->room.'".');
    }

    public function getUser()
    {
        $users = json_decode($this->getGuzzle()->get('/v1/user')->getBody());

        return reset($users);
    }

    public function postMessage($room, $text)
    {
        return json_decode($this->getGuzzle()->post('/v1/rooms/'.$room->id.'/chatMessages', ['form_params' => ['text' => $text]])->getBody());
    }

    public function getMessagesStream(LoopInterface $loop)
    {
        $dnsFactory = new DnsFactory();
        $dnsResolver = $dnsFactory->createCached('8.8.8.8', $loop);

        $httpFactory = new HttpFactory();
        $react = $httpFactory->create($loop, $dnsResolver);

        return $react->request('GET', 'https://stream.gitter.im/v1/rooms/'.$this->getRoom()->id.'/chatMessages', $this->getHeaders());
    }

    private function getGuzzle()
    {
        if (null === $this->guzzle) {
            $this->guzzle = new Client([
                'base_uri' => 'https://api.gitter.im',
                'headers' => $this->getHeaders(),
            ]);
        }

        return $this->guzzle;
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
