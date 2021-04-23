<?php

namespace BotMan\Drivers\Twitter;

use BotMan\BotMan\Users\User;
use Illuminate\Support\Collection;
use BotMan\BotMan\Drivers\HttpDriver;
use Abraham\TwitterOAuth\TwitterOAuth;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Interfaces\VerifiesService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;

class TwitterDriver extends HttpDriver implements VerifiesService
{
    protected $headers = [];

    const DRIVER_NAME = 'Twitter';

    /** @var TwitterOAuth */
    protected $connection;

    /**
     * @param Request $request
     * @return void
     */
    public function buildPayload(Request $request)
    {
        $this->payload = new ParameterBag(json_decode($request->getContent(), true) ?? []);
        $this->headers = $request->headers->all();
        $this->event = Collection::make($this->payload->get('direct_message_events'));
        $this->config = Collection::make($this->config->get('twitter', []));

        $this->connection = new TwitterOAuth(
            $this->config->get('consumer_key'),
            $this->config->get('consumer_secret'),
            $this->config->get('token'),
            $this->config->get('token_secret')
        );
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        if (isset($this->headers['x-twitter-webhooks-signature'])) {
            $signature = $this->headers['x-twitter-webhooks-signature'][0];
            $hash = hash_hmac('sha256', json_encode($this->payload->all()), $this->config->get('consumer_secret'), true);

            return $signature === 'sha256='.base64_encode($hash);
        }
        return false;
    }

    /**
     * Retrieve the chat message(s).
     *
     * @return array
     */
    public function getMessages()
    {
        return $this->event
            ->filter(function ($event) {
                return $event['type'] === 'message_create';
            })
            ->map(function ($event) {
                $message = $event['message_create'];

                return new IncomingMessage($message['message_data']['text'], $message['sender_id'], $message['target']['recipient_id'], $event);
            })->toArray();
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return ! empty($this->config->get('consumer_secret'));
    }

    /**
     * Retrieve User information.
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return User
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        $sender_id = $matchingMessage->getRecipient();

        $user = Collection::make($this->payload->get('users'))->first(function ($user) use ($sender_id) {
            return $user['id'] === $sender_id;
        });

        return new User($user['id'], null, null, $user['name'], $user);
    }

    /**
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
     * @return Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        $payload = $message->getPayload();
        $answer = Answer::create($message->getText())->setMessage($message);

        if (isset($payload['message_create']['message_data']['quick_reply_response']['metadata'])) {
            $answer->setInteractiveReply(true);
            $answer->setValue($payload['message_create']['message_data']['quick_reply_response']['metadata']);
        }

        return $answer;
    }

    /**
     * Convert a Question object into a valid Twitter message object.
     *
     * @param \BotMan\BotMan\Messages\Outgoing\Question $question
     * @return array
     */
    private function convertQuestion(Question $question)
    {
        $buttons = Collection::make($question->getButtons())->map(function ($button) {
            return [
                'label' => $button['text'],
                'metadata' => $button['value']
            ];
        });

        return [
            'text' => $question->getText(),
            'quick_reply' => [
                'type'=>'options',
                'options' => $buttons->toArray(),
            ],
        ];
    }

    /**
     * @param OutgoingMessage|\BotMan\BotMan\Messages\Outgoing\Question $message
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return array
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $payload = [
            'event' => [
                'type' => 'message_create',
                'message_create' => [
                    'target' => [
                        'recipient_id' => $matchingMessage->getSender()
                    ],
                    'message_data' => [
                        'text' => ''
                    ],
                ]
            ]
        ];
        if ($message instanceof OutgoingMessage) {
            $payload['event']['message_create']['message_data']['text'] = $message->getText();
        } elseif ($message instanceof Question) {
            $payload['event']['message_create']['message_data'] = $this->convertQuestion($message);
        }

        return $payload;
    }

    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        $this->connection->post('direct_messages/events/new', $payload, true);

        return Response::create($this->connection->getLastBody(), $this->connection->getLastHttpCode());
    }

    /**
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return Response
     */
    public function types(IncomingMessage $matchingMessage)
    {
        $this->connection->post('direct_messages/indicate_typing', [
            'recipient_id' => $matchingMessage->getSender()
        ], false);

        return Response::create('', $this->connection->getLastHttpCode());
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return Response
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        $this->connection->post($endpoint, $parameters, true);

        return Response::create($this->connection->getLastBody(), $this->connection->getLastHttpCode());
    }

    /**
     * @param Request $request
     */
    public function verifyRequest(Request $request)
    {
        if (! is_null($request->get('crc_token'))) {
            $hash = hash_hmac('sha256', $request->get('crc_token'), $this->config->get('consumer_secret'), true);

            return Response::create(json_encode([
                'response_token' => 'sha256='.base64_encode($hash)
            ]), 200, [
                'Content-Type' => 'application/json'
            ])->send();
        }
    }
}
