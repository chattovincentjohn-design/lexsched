<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends text messages through Semaphore (a Philippine SMS gateway).
 * Without an API key the message is written to the log instead, which is enough for development.
 */
class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $number = $notifiable->routeNotificationFor('sms', $notification);

        if (blank($number) || ! method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);
        $apiKey = config('lexsched.sms.semaphore_key');

        if (blank($apiKey)) {
            Log::info("SMS to {$number}: {$message}");

            return;
        }

        try {
            Http::asForm()->timeout(10)->post('https://api.semaphore.co/api/v4/messages', [
                'apikey' => $apiKey,
                'number' => $number,
                'message' => $message,
                'sendername' => config('lexsched.sms.sender_name'),
            ])->throw();
        } catch (Throwable $exception) {
            // A failed text message must never block the booking itself.
            Log::warning("SMS to {$number} failed: {$exception->getMessage()}");
        }
    }
}
