<?php

namespace App\Services;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Http;

/** Sends through Twilio's Messages API as SMS or WhatsApp (TWILIO_CHANNEL). */
class TwilioSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        $sid = (string) config('services.twilio.sid');
        $prefix = config('services.twilio.channel') === 'whatsapp' ? 'whatsapp:' : '';

        Http::asForm()
            ->withBasicAuth($sid, (string) config('services.twilio.token'))
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $prefix.config('services.twilio.from'),
                'To' => $prefix.self::e164($phone),
                'Body' => $message,
            ])
            ->throw();
    }

    /** "012-345 6789" → "+60123456789": numbers are typed the local way, Twilio needs the country code. */
    public static function e164(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (str_starts_with(trim($phone), '+')) {
            return '+'.$digits;
        }

        return '+'.(str_starts_with($digits, '0') ? config('services.sms.default_country_code').substr($digits, 1) : $digits);
    }
}
