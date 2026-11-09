<?php

namespace App\Mail;

use App\Models\SyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SyncFailing extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SyncRun $run,
        public int $failures,
        public ?SyncRun $lastSuccess,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Kids Tracker: the last {$this->failures} syncs failed");
    }

    public function content(): Content
    {
        $tz = (string) config('powerschool.timezone');

        return new Content(markdown: 'mail.sync-failing', with: [
            'failures' => $this->failures,
            'error' => $this->run->error,
            'lastSuccess' => $this->lastSuccess?->started_at->setTimezone($tz)->format('D M j, g:i a'),
            'syncUrl' => route('sync.index'),
        ]);
    }
}
