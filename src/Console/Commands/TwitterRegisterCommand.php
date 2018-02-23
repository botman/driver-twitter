<?php

namespace BotMan\Drivers\Twitter\Console\Commands;

use Illuminate\Console\Command;
use Abraham\TwitterOAuth\TwitterOAuth;

class TwitterRegisterCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'botman:twitter:register {--output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register your bot with Twitter\'s webhook';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $connection = new TwitterOAuth(
            config('botman.twitter.consumer_key'),
            config('botman.twitter.consumer_secret'),
            config('botman.twitter.token'),
            config('botman.twitter.token_secret')
        );

        $url = $this->ask('What is the target url for the Twitter bot?');

        $this->info('Using '.$url);

        $this->info('Pinging Twitter...');

        /*
         * During the beta, access to the API is provided up to 50 account subscriptions per webhook
         * and up to one webhook per Twitter application.
         *
         * So we check if we already have a webhook defined and delete it.
         */
        $webhooks = $connection->get('account_activity/webhooks');
        if (count($webhooks) > 0) {
            $connection->delete('account_activity/webhooks/'.$webhooks[0]->id);
        }

        $webhook = $connection->post('account_activity/webhooks', [
            'url' => $url
        ]);

        if (isset($webhook->errors)) {
            $this->error('Unable to setup Twitter webhook.');
            dump($webhook);
        } else {
            // Subscribe to the webhook
            $connection->post('account_activity/webhooks/'.$webhook->id.'/subscriptions', []);

            $this->info('Your bot is now set up with Twitter\'s webhook!');
        }

        if ($this->option('output')) {
            dump($webhook);
        }
    }
}
