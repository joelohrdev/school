<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * One school quarter: a name and the dates it runs, inclusive. Dates are
 * plain calendar days, compared as Y-m-d strings so time zones can't shift
 * an assignment into the wrong quarter.
 */
final readonly class Quarter
{
    public function __construct(
        public string $name,
        public string $startsOn,
        public string $endsOn,
        public string $source,
    ) {}

    public static function fromDates(string $name, CarbonInterface $start, CarbonInterface $end, string $source): self
    {
        return new self($name, $start->toDateString(), $end->toDateString(), $source);
    }

    public function key(): string
    {
        return strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', trim($this->name)));
    }

    public function contains(CarbonInterface|string|null $date): bool
    {
        if ($date === null) {
            return false;
        }

        $day = is_string($date) ? substr($date, 0, 10) : $date->toDateString();

        return $day >= $this->startsOn && $day <= $this->endsOn;
    }

    public function hasStarted(CarbonInterface $today): bool
    {
        return $this->startsOn <= $today->toDateString();
    }

    /** Whether another span of dates is this quarter, give or take a week. */
    public function matches(?CarbonInterface $start, ?CarbonInterface $end): bool
    {
        if ($start === null || $end === null) {
            return false;
        }

        $days = fn (string $a, CarbonInterface $b) => abs((int) CarbonImmutable::parse($a, 'UTC')->diffInDays(CarbonImmutable::parse($b->toDateString(), 'UTC'), false));

        return $days($this->startsOn, $start) <= 7 && $days($this->endsOn, $end) <= 7;
    }

    /**
     * @return array{key: string, name: string, startsOn: string, endsOn: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'name' => $this->name,
            'startsOn' => $this->startsOn,
            'endsOn' => $this->endsOn,
        ];
    }
}
