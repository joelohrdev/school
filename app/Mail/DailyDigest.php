<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{alert_ids: list<int>, students: list<array<string, mixed>>, totals: array<string, int>}  $digest
     */
    public function __construct(public array $digest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.digest', with: [
            'students' => $this->digest['students'],
            'dashboardUrl' => route('dashboard'),
        ]);
    }

    public function subjectLine(): string
    {
        $t = $this->digest['totals'];

        $parts = array_filter([
            $t['newly_missing'] ? $t['newly_missing'].' newly missing' : null,
            $t['newly_late'] ? $t['newly_late'].' late' : null,
            $t['grade_drops'] ? $t['grade_drops'].' grade '.($t['grade_drops'] === 1 ? 'drop' : 'drops') : null,
            $t['scores'] ? $t['scores'].' new '.($t['scores'] === 1 ? 'score' : 'scores') : null,
            $t['resolved'] ? $t['resolved'].' turned in' : null,
        ]);

        return 'School update: '.($parts === [] ? 'changes since yesterday' : implode(', ', $parts));
    }
}
