/** Parse a date-only string (YYYY-MM-DD) as a local calendar day, not UTC midnight. */
export function parseDay(day: string): Date {
    const [y, m, d] = day.slice(0, 10).split('-').map(Number);

    return new Date(y, m - 1, d);
}

export function formatDay(
    day: string | null | undefined,
    fallback = 'No due date',
): string {
    if (!day) {
        return fallback;
    }

    return parseDay(day).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

export function formatLongDay(day: string): string {
    return parseDay(day).toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    });
}

/** 7 → "7th", 0 → "K": short enough for the page margin. */
export function gradeOrdinal(n: number): string {
    if (n <= 0) {
        return n === 0 ? 'K' : 'PK';
    }

    const teen = n % 100 >= 11 && n % 100 <= 13;
    const suffix = teen ? 'th' : (['th', 'st', 'nd', 'rd'][n % 10] ?? 'th');

    return `${n}${suffix}`;
}

export function formatShortDay(day: string): string {
    return parseDay(day).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

export function formatPercent(percent: number | null | undefined): string {
    if (percent === null || percent === undefined) {
        return '—';
    }

    return `${Number.isInteger(percent) ? percent : percent.toFixed(1)}%`;
}

/** A timestamp's local calendar day, e.g. "Nov 9". */
export function formatShortDate(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

export function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    });
}

export function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export function timeAgo(iso: string, now: Date = new Date()): string {
    const seconds = Math.round(
        (new Date(iso).getTime() - now.getTime()) / 1000,
    );
    const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    const steps: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unit, size] of steps) {
        if (Math.abs(seconds) >= size) {
            return rtf.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}

/** Whole days from `from` to `to` (both YYYY-MM-DD). */
export function daysBetween(from: string, to: string): number {
    return Math.round(
        (parseDay(to).getTime() - parseDay(from).getTime()) / 86_400_000,
    );
}

export function plural(n: number, word: string, many = `${word}s`): string {
    return `${n} ${n === 1 ? word : many}`;
}

export function formatScore(
    score: string | null,
    pointsPossible: number | null,
): string {
    if (score === null) {
        return '—';
    }

    return pointsPossible === null
        ? score
        : `${score}/${Number.isInteger(pointsPossible) ? pointsPossible : pointsPossible.toFixed(1)}`;
}
