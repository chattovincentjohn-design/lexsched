<?php

namespace App\Console\Commands;

use App\Services\PayMongo;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('paymongo:webhook {url? : Public URL of /webhooks/paymongo, defaults to APP_URL}')]
#[Description('Register the payment webhook with PayMongo and print the secret for PAYMONGO_WEBHOOK_SECRET')]
class RegisterPayMongoWebhook extends Command
{
    public function handle(PayMongo $payMongo): int
    {
        if (! $payMongo->isEnabled()) {
            $this->error('Set PAYMONGO_SECRET_KEY in .env first.');

            return self::FAILURE;
        }

        $url = $this->argument('url') ?? url('/webhooks/paymongo');

        if (! str_starts_with($url, 'https://')) {
            $this->error("PayMongo only sends webhooks to public https:// addresses. {$url} won't work.");

            return self::FAILURE;
        }

        try {
            $secret = $payMongo->registerWebhook($url);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Webhook registered for {$url}.");
        $this->line('Add this line to .env, then run php artisan config:clear:');
        $this->line("PAYMONGO_WEBHOOK_SECRET={$secret}");

        return self::SUCCESS;
    }
}
