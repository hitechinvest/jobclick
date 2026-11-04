<?php

namespace App\Mail;

use App\Models\AdminAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AdminAlert $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[JobClick] '.$this->alert->typeLabel());
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.admin-alert');
    }
}
