<?php

namespace App\Services\Twilio;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class TwilioService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client(config('TWILIO_SID'), config('TWILIO_AUTH_TOKEN'));
    }

    /**
     * Send SMS globally
     *
     * @param string $to   Recipient number with country code, e.g. +8801XXXXXXXXX
     * @param string $message
     * @return bool
     */
    public function sendSMS(string $to, string $message): bool
    {
        Log::info("Client: " . json_encode($this->client));
        try {
            $this->client->messages->create($to, [
                'from' => config('TWILIO_PHONE_NUMBER'),
                'body' => $message
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Twilio SMS Error: ' . $e->getMessage());
            return false;
        }
    }
}
