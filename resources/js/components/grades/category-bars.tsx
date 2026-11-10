import { useMemo } from 'react';
import { assignmentStatus } from '@/components/grades/status-badge';
import { formatPercent, plural } from '@/lib/format';
import type { AssignmentRow } from '@/types/gradebook';

type Category = {
    name: string;
    average: number | null;
    scored: number;
    missing: number;
};

/**
 * Average score per assignment category, so it's clear where points are
 * being lost. Horizontal bars on a fixed 0–100% scale, one hue, each bar
 * labeled at its tip; missing work is counted beside the name, not folded
 * into the average, since PowerSchool may still change how it scores it.
 */
export function CategoryBars({
    assignments,
}: {
    assignments: AssignmentRow[];
}) {
    const categories = useMemo(() => summarize(assignments), [assignments]);
    const scored = categories.filter((c) => c.average !== null);

    if (scored.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Category averages appear once scores are posted.
            </p>
        );
    }

    return (
        <figure>
            <figcaption className="sr-only">
                Average score by category:{' '}
                {scored
                    .map((c) => `${c.name} ${formatPercent(c.average)}`)
                    .join(', ')}
                .
            </figcaption>
            <ul className="space-y-3" aria-hidden>
                {categories.map((c) => (
                    <li
                        key={c.name}
                        className="grid grid-cols-[minmax(0,8rem)_1fr] items-center gap-x-3 sm:grid-cols-[minmax(0,11rem)_1fr]"
                    >
                        <div className="min-w-0">
                            <div className="truncate text-sm font-semibold">
                                {c.name}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {c.scored === 0
                                    ? 'No scores yet'
                                    : plural(c.scored, 'score')}
                                {c.missing > 0 && (
                                    <span className="font-semibold text-missing">
                                        , {c.missing} missing
                                    </span>
                                )}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <div className="relative h-3 flex-1 rounded-r-[4px] bg-muted">
                                {c.average !== null && (
                                    <div
                                        className="absolute inset-y-0 left-0 rounded-r-[4px] bg-grade-line"
                                        style={{
                                            width: `${Math.max(1, Math.min(100, c.average))}%`,
                                        }}
                                    />
                                )}
                            </div>
                            <span className="w-14 shrink-0 text-right text-sm font-semibold tabular-nums">
                                {formatPercent(c.average)}
                            </span>
                        </div>
                    </li>
                ))}
            </ul>
        </figure>
    );
}

function summarize(assignments: AssignmentRow[]): Category[] {
    const byName = new Map<string, Category & { total: number }>();

    for (const a of assignments) {
        const name = a.category ?? 'Uncategorized';
        const c = byName.get(name) ?? {
            name,
            average: null,
            scored: 0,
            missing: 0,
            total: 0,
        };
        const status = assignmentStatus(a);

        if (status === 'missing') {
            c.missing++;
        } else if (status !== 'exempt' && a.percent !== null) {
            c.scored++;
            c.total += a.percent;
        }

        byName.set(name, c);
    }

    return [...byName.values()]
        .map(({ total, ...c }) => ({
            ...c,
            average:
                c.scored > 0 ? Math.round((total / c.scored) * 10) / 10 : null,
        }))
        .filter((c) => c.scored > 0 || c.missing > 0)
        .sort((a, b) => a.name.localeCompare(b.name));
}
