import { formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';

/** A pen ring drawn once around the letter; the stroke overshoots where it started. */
const RING =
    'M30 5C42 5.5 49.5 13 48.5 22.5 47.5 33 36 39 23.5 38 11 37 3 30.5 4 20.5 5 11 14 4.5 26 5.5 33 6 40 9.5 44.5 15';

/** Each letter gets its own slight tilt, so a column of grades looks marked by hand. */
function tilt(letter: string): number {
    return ((letter.charCodeAt(0) * 7) % 11) - 7;
}

function failing(letter: string | null): boolean {
    return letter !== null && /^[DFU]/i.test(letter);
}

/**
 * The class grade the way a teacher marks it: the letter circled in pen,
 * the percent written beside it. D and F are circled in red pen; the
 * letter itself still carries the meaning.
 */
export function GradeValue({
    percent,
    letter,
    size = 'md',
    className,
}: {
    percent: number | null;
    letter: string | null;
    size?: 'md' | 'lg';
    className?: string;
}) {
    if (percent === null && letter === null) {
        return (
            <span className={cn('text-sm text-muted-foreground', className)}>
                No grade
            </span>
        );
    }

    const lg = size === 'lg';

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 tabular-nums',
                lg && 'gap-3',
                className,
            )}
        >
            {letter && (
                <span
                    className={cn(
                        'relative inline-flex shrink-0 items-center justify-center font-extrabold',
                        lg ? 'h-20 w-24 text-5xl' : 'h-9 w-11 text-lg',
                        failing(letter) ? 'text-missing' : 'text-foreground',
                    )}
                >
                    <svg
                        viewBox="0 0 52 42"
                        className="absolute inset-0 size-full"
                        style={{ transform: `rotate(${tilt(letter)}deg)` }}
                        aria-hidden
                        fill="none"
                    >
                        <path
                            d={RING}
                            stroke="currentColor"
                            strokeWidth={lg ? 1.6 : 2.2}
                            strokeLinecap="round"
                            opacity={0.85}
                        />
                    </svg>
                    <span className="relative">{letter}</span>
                </span>
            )}
            <span
                className={cn(
                    'text-muted-foreground',
                    lg ? 'text-2xl font-semibold' : 'text-sm',
                )}
            >
                {formatPercent(percent)}
            </span>
        </span>
    );
}
