<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MissingWorkAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{name: string, newly_missing: list<array<string, mixed>>, still_missing_count: int}>  $kids
     */
    public function __construct(public array $kids) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.missing-work', with: [
            'kids' => $this->kids,
            'dashboardUrl' => route('dashboard'),
        ]);
    }

    /** "Missing work: Avery has 2 new", "Missing work: Avery 1 new, Blake 2 new". */
    public function subjectLine(): string
    {
        if (count($this->kids) === 1) {
            $n = count($this->kids[0]['newly_missing']);

            return "Missing work: {$this->kids[0]['name']} has {$n} new missing ".Str::plural('assignment', $n);
        }

        return 'Missing work: '.collect($this->kids)
            ->map(fn (array $k) => $k['name'].' '.count($k['newly_missing']).' new')
            ->join(', ');
    }
}
