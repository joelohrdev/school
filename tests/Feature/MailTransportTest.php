<?php

use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;

// Production sends through Resend; without resend/resend-php installed the
// first email (email verification at registration) crashes with a 500.
it('can build the resend mailer', function () {
    config(['services.resend.key' => 're_test_key']);

    expect(Mail::mailer('resend')->getSymfonyTransport())->toBeInstanceOf(ResendTransport::class);
});
