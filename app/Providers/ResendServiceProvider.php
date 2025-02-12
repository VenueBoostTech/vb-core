<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Resend\Transport\ResendTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class ResendServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Mail::extend('resend', function () {
            return (new ResendTransportFactory)->create(
                new Dsn(
                    'resend+api',
                    'default',
                    config('mail.mailers.resend.api_key')
                )
            );
        });
    }
}
