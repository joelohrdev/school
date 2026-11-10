import { formatPercent, formatShortDay } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { TrendPoint } from '@/types/gradebook';

const W = 72;
const H = 28;
const PAD = 4;

/**
 * A 30-day grade trend. Single series, so no legend: the row it sits in
 * names the class. The end marker is the latest snapshot.
 */
export function Sparkline({
    points,
    className,
}: {
    points: TrendPoint[];
    className?: string;
}) {
    const values = points.filter(
        (p): p is { date: string; percent: number } => p.percent !== null,
    );

    if (values.length < 2) {
        // Keeps the column's width so grades beside it still line up.
        return (
            <span
                className={cn('inline-block w-[72px]', className)}
                aria-hidden
            />
        );
    }

    const nums = values.map((v) => v.percent);
    let min = Math.min(...nums);
    let max = Math.max(...nums);

    // Keep a flat line flat-looking instead of stretching noise to full height.
    if (max - min < 4) {
        const mid = (max + min) / 2;
        min = mid - 2;
        max = mid + 2;
    }

    const x = (i: number) => PAD + (i / (values.length - 1)) * (W - PAD * 2);
    const y = (v: number) =>
        PAD + (1 - (v - min) / (max - min)) * (H - PAD * 2);
    const d = values
        .map(
            (v, i) =>
                `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(v.percent).toFixed(1)}`,
        )
        .join(' ');

    const first = values[0];
    const last = values[values.length - 1];
    const summary = `From ${formatPercent(first.percent)} on ${formatShortDay(first.date)} to ${formatPercent(last.percent)} on ${formatShortDay(last.date)}`;

    return (
        <svg
            viewBox={`0 0 ${W} ${H}`}
            width={W}
            height={H}
            className={cn('shrink-0 overflow-visible', className)}
            role="img"
            aria-label={`Grade trend: ${summary}`}
        >
            <title>{summary}</title>
            <path
                d={d}
                fill="none"
                stroke="var(--grade-line)"
                strokeWidth={2}
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle
                cx={x(values.length - 1)}
                cy={y(last.percent)}
                r={4}
                fill="var(--grade-line)"
                stroke="var(--card)"
                strokeWidth={2}
            />
        </svg>
    );
}
