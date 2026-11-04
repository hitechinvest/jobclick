<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LoginCodeMail extends Mailable
{
    use Queueable;

    public function __construct(public string $code, public string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Код для входа в JobClick: '.$this->code);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.login-code');
    }
}
