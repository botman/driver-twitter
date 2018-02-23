<?php

namespace Tests;

use Mockery as m;
use BotMan\BotMan\Http\Curl;
use PHPUnit_Framework_TestCase;
use BotMan\Drivers\Twitter\TwitterDriver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TwitterDriverTest extends PHPUnit_Framework_TestCase
{
    const TEST_SECRET = 'test';

    public function tearDown()
    {
        m::close();
    }

    private function getValidDriver()
    {
        $json = '{
    "direct_message_events": [
        {
            "type": "message_create",
            "id": "967033533893136389",
            "created_timestamp": "1519393735751",
            "message_create": {
                "target": {
                    "recipient_id": "132971183"
                },
                "sender_id": "829084918626000896",
                "message_data": {
                    "text": "Hi",
                    "entities": {
                        "hashtags": [],
                        "symbols": [],
                        "user_mentions": [],
                        "urls": []
                    }
                }
            }
        }
    ],
    "users": {
        "132971183": {
            "id": "132971183",
            "created_timestamp": "1271266047000",
            "name": "Marcel Pociot",
            "screen_name": "marcelpociot",
            "location": "Germany",
            "description": "Managing Partner and Developer at Beyond Code. Laravel Evangelist, Open Source Lover, Father and Husband. Working on @botman_io",
            "url": "https://t.co/VGTjPCB1E8",
            "protected": false,
            "verified": false,
            "followers_count": 4023,
            "friends_count": 357,
            "statuses_count": 5179,
            "profile_image_url": "http://pbs.twimg.com/profile_images/905306365794635777/Eoys6pS0_normal.jpg",
            "profile_image_url_https": "https://pbs.twimg.com/profile_images/905306365794635777/Eoys6pS0_normal.jpg"
        },
        "829084918626000896": {
            "id": "829084918626000896",
            "created_timestamp": "1486504223067",
            "name": "botman.io",
            "screen_name": "botman_io",
            "description": "Write cross-platform chat bots with a simple to use, yet powerful API in PHP. Developed by @marcelpociot",
            "url": "https://t.co/N687TrKsE6",
            "protected": false,
            "verified": false,
            "followers_count": 595,
            "friends_count": 56,
            "statuses_count": 145,
            "profile_image_url": "http://pbs.twimg.com/profile_images/872331955697831936/gxzxi8qs_normal.jpg",
            "profile_image_url_https": "https://pbs.twimg.com/profile_images/872331955697831936/gxzxi8qs_normal.jpg"
        }
    }
}';
        $hash = hash_hmac('sha256', json_encode(json_decode($json)), self::TEST_SECRET, true);
        $validSignature = 'sha256='.base64_encode($hash);

        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn($json);
        $htmlInterface = m::mock(Curl::class);

        $request->headers->add(['x-twitter-webhooks-signature' => $validSignature]);

        return new TwitterDriver($request, [
            'twitter' => [
                'consumer_secret' => self::TEST_SECRET
            ]
        ], $htmlInterface);
    }

    private function getDriver($responseData, $htmlInterface = null)
    {
        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));
        if ($htmlInterface === null) {
            $htmlInterface = m::mock(Curl::class);
        }

        $request->headers->add(['x-twitter-webhooks-signature' => 'signature']);

        return new TwitterDriver($request, [], $htmlInterface);
    }

    /** @test */
    public function it_returns_the_driver_name()
    {
        $driver = $this->getDriver([]);
        $this->assertSame('Twitter', $driver->getName());
    }

    /** @test */
    public function it_matches_the_request()
    {
        $driver = $this->getDriver([
            'messages' => [
                ['text' => 'bar'],
                ['text' => 'foo'],
            ],
        ]);
        $this->assertFalse($driver->matchesRequest());

        $driver = $this->getValidDriver();
        $this->assertTrue($driver->matchesRequest());
    }

    /** @test */
    public function it_returns_the_message_object()
    {
        $driver = $this->getValidDriver();

        $this->assertTrue(is_array($driver->getMessages()));
    }

    /** @test */
    public function it_returns_the_message_text()
    {
        $driver = $this->getValidDriver();

        $this->assertSame('Hi', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_returns_the_user_id()
    {
        $driver = $this->getValidDriver();

        $this->assertSame('829084918626000896', $driver->getMessages()[0]->getSender());
    }

    /** @test */
    public function it_returns_the_recipient_id()
    {
        $driver = $this->getValidDriver();

        $this->assertSame('132971183', $driver->getMessages()[0]->getRecipient());
    }

    /** @test */
    public function it_returns_the_user()
    {
        $driver = $this->getValidDriver();

        $user = $driver->getUser($driver->getMessages()[0]);

        $this->assertSame('132971183', $user->getId());
        $this->assertNull($user->getFirstName());
        $this->assertNull($user->getLastName());
        $this->assertSame('Marcel Pociot', $user->getUsername());
    }
}
