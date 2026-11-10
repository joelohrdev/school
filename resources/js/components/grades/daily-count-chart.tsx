import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Button } from '@/components/ui/button';
import { formatDay, formatShortDay } from '@/lib/format';

const HEIGHT = 180;
const M = { top: 12, right: 12, bottom: 28, left: 32 };
const MAX_BAR = 24;

export type DailyCount = { date: string; value: number };

/**
 * A whole-number count per day (missing assignments, say) as columns from a
 * zero baseline. One series, one hue, no legend box: the heading names it.
 * Each day's slot is a hover and focus target, and a table view carries the
 * same numbers.
 */
export function DailyCountChart({
    points,
    label,
    title,
}: {
    points: DailyCount[];
    /** Column name in the table view and the tooltip, e.g. "Missing". */
    label: string;
    /** What the chart shows, for screen readers. */
    title: string;
}) {
    const wrap = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(600);
    const [active, setActive] = useState<number | null>(null);
    const [showTable, setShowTable] = useState(false);

    useEffect(() => {
        const el = wrap.current;

        if (!el) {
            return;
        }

        const ro = new ResizeObserver(([entry]) =>
            setWidth(Math.max(240, entry.contentRect.width)),
        );
        ro.observe(el);

        return () => ro.disconnect();
    }, []);

    if (points.length < 2) {
        return (
            <p className="text-sm text-muted-foreground">
                This fills in as daily syncs build up history.
            </p>
        );
    }

    const max = Math.max(...points.map((p) => p.value));
    const top = Math.max(2, niceMax(max));
    const ticks = countTicks(top);

    const innerW = width - M.left - M.right;
    const innerH = HEIGHT - M.top - M.bottom;
    const slot = innerW / points.length;
    const bar = Math.max(2, Math.min(MAX_BAR, slot - 2));
    const cx = (i: number) => M.left + slot * i + slot / 2;
    const y = (v: number) => M.top + (1 - v / top) * innerH;
    const baseline = y(0);
    const xLabels = [
        ...new Set([0, Math.floor((points.length - 1) / 2), points.length - 1]),
    ];

    const onKey = (e: KeyboardEvent<SVGSVGElement>) => {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            e.preventDefault();
            const step = e.key === 'ArrowLeft' ? -1 : 1;
            setActive((a) =>
                Math.min(
                    points.length - 1,
                    Math.max(0, (a ?? points.length - 1) + step),
                ),
            );
        }
    };

    const point = active === null ? null : points[active];

    return (
        <div className="space-y-2">
            <div ref={wrap} className="relative">
                <svg
                    width={width}
                    height={HEIGHT}
                    className="block max-w-full outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    role="img"
                    aria-label={`${title}, ${formatDay(points[0].date)} to ${formatDay(points[points.length - 1].date)}. Latest: ${points[points.length - 1].value}. Use the arrow keys to step through days.`}
                    tabIndex={0}
                    onKeyDown={onKey}
                    onBlur={() => setActive(null)}
                    onPointerLeave={() => setActive(null)}
                >
                    {ticks.map((t) => (
                        <g key={t}>
                            <line
                                x1={M.left}
                                x2={width - M.right}
                                y1={y(t)}
                                y2={y(t)}
                                stroke="var(--border)"
                                strokeWidth={1}
                            />
                            <text
                                x={M.left - 8}
                                y={y(t)}
                                dy="0.32em"
                                textAnchor="end"
                                className="fill-muted-foreground text-[11px] tabular-nums"
                            >
                                {t}
                            </text>
                        </g>
                    ))}
                    {xLabels.map((i) => (
                        <text
                            key={i}
                            x={cx(i)}
                            y={HEIGHT - 8}
                            textAnchor={
                                i === 0
                                    ? 'start'
                                    : i === points.length - 1
                                      ? 'end'
                                      : 'middle'
                            }
                            className="fill-muted-foreground text-[11px]"
                        >
                            {formatShortDay(points[i].date)}
                        </text>
                    ))}
                    {points.map((p, i) => (
                        <g key={p.date}>
                            {p.value > 0 && (
                                <path
                                    d={column(
                                        cx(i) - bar / 2,
                                        y(p.value),
                                        bar,
                                        baseline - y(p.value),
                                    )}
                                    fill="var(--grade-line)"
                                    opacity={
                                        active === null || active === i
                                            ? 1
                                            : 0.45
                                    }
                                />
                            )}
                            {/* The whole day's slot is the hit target, so a zero day still has a tooltip. */}
                            <rect
                                x={M.left + slot * i}
                                y={M.top}
                                width={slot}
                                height={innerH}
                                fill="transparent"
                                onPointerEnter={() => setActive(i)}
                                onPointerDown={() => setActive(i)}
                            />
                        </g>
                    ))}
                </svg>
                {point && active !== null && (
                    <div
                        className="pointer-events-none absolute top-0 rounded-md border bg-popover px-2.5 py-1.5 text-xs shadow-sm"
                        style={{
                            left: Math.min(
                                Math.max(cx(active) - 60, 0),
                                width - 120,
                            ),
                            width: 120,
                        }}
                        role="status"
                    >
                        <div className="text-muted-foreground">
                            {formatDay(point.date)}
                        </div>
                        <div className="text-sm font-semibold tabular-nums">
                            {point.value} {label.toLowerCase()}
                        </div>
                    </div>
                )}
            </div>
            <Button
                variant="ghost"
                size="sm"
                className="h-7 px-2 text-xs text-muted-foreground"
                onClick={() => setShowTable((s) => !s)}
                aria-expanded={showTable}
            >
                {showTable ? 'Hide table' : 'Show as table'}
            </Button>
            {showTable && (
                <div className="max-h-64 overflow-auto rounded-sm border border-rule">
                    <table className="w-full text-sm">
                        <thead className="sticky top-0 bg-muted text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-1.5 font-medium">
                                    Date
                                </th>
                                <th className="px-3 py-1.5 text-right font-medium">
                                    {label}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {[...points].reverse().map((p) => (
                                <tr key={p.date} className="border-t">
                                    <td className="px-3 py-1.5">
                                        {formatDay(p.date)}
                                    </td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">
                                        {p.value}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

/** A column with a 4px rounded top and a square foot on the baseline. */
function column(x: number, y: number, w: number, h: number): string {
    const r = Math.min(4, w / 2, h);

    return `M${x},${y + h}V${y + r}Q${x},${y} ${x + r},${y}H${x + w - r}Q${x + w},${y} ${x + w},${y + r}V${y + h}Z`;
}

function niceMax(max: number): number {
    if (max <= 4) {
        return max;
    }

    const step = max <= 10 ? 2 : max <= 25 ? 5 : 10;

    return Math.ceil(max / step) * step;
}

function countTicks(top: number): number[] {
    const step = top <= 4 ? 1 : top <= 10 ? 2 : top <= 25 ? 5 : 10;
    const ticks: number[] = [];

    for (let t = 0; t <= top; t += step) {
        ticks.push(t);
    }

    return ticks;
}
