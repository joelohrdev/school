import { Link } from '@inertiajs/react';
import { StatusBadge, assignmentStatus } from '@/components/grades/status-badge';
import { daysBetween, formatDay, formatPercent, formatScore } from '@/lib/format';
import { show } from '@/routes/sections';
import type { AssignmentRow } from '@/types/gradebook';

export function AssignmentList({ assignments, today, showCourse = true, empty }: { assignments: AssignmentRow[]; today: string; showCourse?: boolean; empty: string }) {
    if (assignments.length === 0) {
        return <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">{empty}</p>;
    }

    return (
        <ul className="divide-y rounded-lg border">
            {assignments.map((a) => {
                const status = assignmentStatus(a);
                const overdue = status === 'missing' && a.dueOn ? daysBetween(a.dueOn, today) : null;

                return (
                    <li key={a.id} className="flex flex-col gap-1.5 p-3 sm:flex-row sm:items-center sm:gap-4">
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2">
                                <span className="truncate text-sm font-medium">{a.name}</span>
                            </div>
                            <div className="flex flex-wrap gap-x-2 text-xs text-muted-foreground">
                                {showCourse && (
                                    <Link href={show(a.sectionId)} className="hover:text-foreground hover:underline">
                                        {a.course}
                                    </Link>
                                )}
                                {a.category && <span>{a.category}</span>}
                                <span>
                                    Due {formatDay(a.dueOn, 'date not set')}
                                    {overdue !== null && overdue > 0 && <span className="text-missing"> · {overdue} {overdue === 1 ? 'day' : 'days'} overdue</span>}
                                </span>
                            </div>
                            {a.comment && <p className="mt-1 text-xs text-muted-foreground italic">“{a.comment}”</p>}
                        </div>
                        <div className="flex items-center gap-3 sm:justify-end">
                            <StatusBadge status={status} />
                            {a.late && status !== 'late' && <StatusBadge status="late" />}
                            <span className="w-24 text-right text-sm tabular-nums">
                                {formatScore(a.score, a.pointsPossible)}
                                {a.percent !== null && <span className="ml-1.5 text-xs text-muted-foreground">{formatPercent(a.percent)}</span>}
                            </span>
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
