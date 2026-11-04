<?php

namespace App\Mail;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public const SUBJECTS = [
        'submitted' => 'Кампания отправлена на модерацию',
        'approved' => 'Кампания одобрена — размещаем в рекламных сетях',
        'rejected' => 'Кампания отклонена модератором',
        'needs_changes' => 'Нужно поправить кампанию',
        'started' => 'Ваша реклама запущена!',
        'budget_80' => 'Израсходовано 80% бюджета кампании',
        'completed' => 'Кампания завершена',
        'report' => 'Первые результаты вашей кампании',
    ];

    public function __construct(public Campaign $campaign, public string $type, public array $extra = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (self::SUBJECTS[$this->type] ?? 'Новости по кампании').' — «'.$this->campaign->displayTitle().'»');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.campaign-status');
    }
}
