<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Счёт № '.$this->invoice->number.' на оплату рекламы');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => app(InvoiceService::class)->pdf($this->invoice), 'Счёт-'.$this->invoice->number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
