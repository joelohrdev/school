import { useEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent, PointerEvent } from 'react';
import { Button } from '@/components/ui/button';
import { formatDay, formatPercent, formatShortDay } from '@/lib/format';
import type { TrendPoint } from '@/types/gradebook';

const HEIGHT = 220;
const M = { top: 12, right: 12, bottom: 28, left: 40 };

/**
 * Daily class grade over time. One series, one hue, no legend box; a
 * crosshair snaps to the nearest day and a table view carries the same data.
 */
export function GradeHistoryChart({ points }: { points: TrendPoint[] }) {
    const data = useMemo(
        () =>
            points.filter(
                (p): p is { date: string; percent: number } =>
                    p.percent !== null,
            ),
        [points],
    );
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

    if (data.length < 2) {
        return (
            <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                The history chart fills in as daily syncs build up snapshots.
            </p>
        );
    }

    const nums = data.map((d) => d.percent);
    const lo = Math.max(0, Math.floor((Math.min(...nums) - 3) / 5) * 5);
    const hi = Math.ceil((Math.max(...nums) + 3) / 5) * 5;
    const ticks = niceTicks(lo, hi);

    const innerW = width - M.left - M.right;
    const innerH = HEIGHT - M.top - M.bottom;
    const x = (i: number) =>
        M.left +
        (data.length === 1 ? innerW / 2 : (i / (data.length - 1)) * innerW);
    const y = (v: number) => M.top + (1 - (v - lo) / (hi - lo)) * innerH;
    const path = data
        .map(
            (d, i) =>
                `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(d.percent).toFixed(1)}`,
        )
        .join(' ');
    const xLabels = [
        ...new Set([0, Math.floor((data.length - 1) / 2), data.length - 1]),
    ];

    const onMove = (e: PointerEvent<SVGRectElement>) => {
        const box = e.currentTarget.getBoundingClientRect();
        const px = e.clientX - box.left;
        const i = Math.round((px / box.width) * (data.length - 1));
        setActive(Math.min(data.length - 1, Math.max(0, i)));
    };

    const onKey = (e: KeyboardEvent<SVGSVGElement>) => {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            e.preventDefault();
            const step = e.key === 'ArrowLeft' ? -1 : 1;
            setActive((a) =>
                Math.min(
                    data.length - 1,
                    Math.max(0, (a ?? data.length - 1) + step),
                ),
            );
        }
    };

    const point = active === null ? null : data[active];

    return (
        <div className="space-y-2">
            <div ref={wrap} className="relative">
                <svg
                    width={width}
                    height={HEIGHT}
                    className="block max-w-full outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    role="img"
                    aria-label={`Grade history from ${formatDay(data[0].date)} to ${formatDay(data[data.length - 1].date)}. Use the arrow keys to step through days.`}
                    tabIndex={0}
                    onKeyDown={onKey}
                    onBlur={() => setActive(null)}
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
                                {t}%
                            </text>
                        </g>
                    ))}
                    {xLabels.map((i) => (
                        <text
                            key={i}
                            x={x(i)}
                            y={HEIGHT - 8}
                            textAnchor={
                                i === 0
                                    ? 'start'
                                    : i === data.length - 1
                                      ? 'end'
                                      : 'middle'
                            }
                            className="fill-muted-foreground text-[11px]"
                        >
                            {formatShortDay(data[i].date)}
                        </text>
                    ))}
                    <path
                        d={path}
                        fill="none"
                        stroke="var(--grade-line)"
                        strokeWidth={2}
                        strokeLinejoin="round"
                        strokeLinecap="round"
                    />
                    {point && active !== null && (
                        <>
                            <line
                                x1={x(active)}
                                x2={x(active)}
                                y1={M.top}
                                y2={M.top + innerH}
                                stroke="var(--muted-foreground)"
                                strokeWidth={1}
                                strokeDasharray="3 3"
                            />
                            <circle
                                cx={x(active)}
                                cy={y(point.percent)}
                                r={4.5}
                                fill="var(--grade-line)"
                                stroke="var(--card)"
                                strokeWidth={2}
                            />
                        </>
                    )}
                    {active === null && (
                        <circle
                            cx={x(data.length - 1)}
                            cy={y(data[data.length - 1].percent)}
                            r={4.5}
                            fill="var(--grade-line)"
                            stroke="var(--card)"
                            strokeWidth={2}
                        />
                    )}
                    <rect
                        x={M.left}
                        y={M.top}
                        width={innerW}
                        height={innerH}
                        fill="transparent"
                        onPointerMove={onMove}
                        onPointerDown={onMove}
                        onPointerLeave={() => setActive(null)}
                    />
                </svg>
                {point && active !== null && (
                    <div
                        className="pointer-events-none absolute top-0 rounded-md border bg-popover px-2.5 py-1.5 text-xs shadow-sm"
                        style={{
                            left: Math.min(
                                Math.max(x(active) - 60, 0),
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
                            {formatPercent(point.percent)}
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
                <div className="max-h-64 overflow-auto rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="sticky top-0 bg-muted text-left text-xs text-muted-foreground">
                            <tr>
                                <th className="px-3 py-1.5 font-medium">
                                    Date
                                </th>
                                <th className="px-3 py-1.5 text-right font-medium">
                                    Grade
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {[...data].reverse().map((d) => (
                                <tr key={d.date} className="border-t">
                                    <td className="px-3 py-1.5">
                                        {formatDay(d.date)}
                                    </td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">
                                        {formatPercent(d.percent)}
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

function niceTicks(lo: number, hi: number): number[] {
    const span = hi - lo;
    const step = span <= 15 ? 5 : span <= 40 ? 10 : 20;
    const ticks: number[] = [];

    for (let t = Math.ceil(lo / step) * step; t <= hi; t += step) {
        ticks.push(t);
    }

    return ticks;
}
