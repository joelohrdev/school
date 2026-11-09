import { Link } from '@inertiajs/react';
import {
    StatusBadge,
    assignmentStatus,
} from '@/components/grades/status-badge';
import { Blank, Line } from '@/components/notebook';
import {
    daysBetween,
    formatPercent,
    formatScore,
    formatShortDay,
    plural,
} from '@/lib/format';
import { show } from '@/routes/sections';
import type { AssignmentRow } from '@/types/gradebook';

/** Assignments as ruled lines, due date in the margin. */
export function AssignmentList({
    assignments,
    today,
    showCourse = true,
    empty,
}: {
    assignments: AssignmentRow[];
    today: string;
    showCourse?: boolean;
    empty: string;
}) {
    if (assignments.length === 0) {
        return <Blank>{empty}</Blank>;
    }

    return (
        <ul>
            {assignments.map((a) => {
                const status = assignmentStatus(a);
                const overdue =
                    status === 'missing' && a.dueOn
                        ? daysBetween(a.dueOn, today)
                        : null;

                return (
                    <Line
                        key={a.id}
                        as="li"
                        className="py-2.5"
                        margin={
                            a.dueOn ? (
                                <span title={`Due ${a.dueOn}`}>
                                    <span className="sr-only">Due </span>
                                    {formatShortDay(a.dueOn)}
                                </span>
                            ) : (
                                <span className="sr-only">No due date</span>
                            )
                        }
                    >
                        <div className="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-4">
                            <div className="min-w-0 flex-1">
                                <div className="font-semibold">{a.name}</div>
                                <div className="flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                    {showCourse && (
                                        <Link
                                            href={show(a.sectionId)}
                                            className="hover:text-foreground hover:underline"
                                        >
                                            {a.course}
                                        </Link>
                                    )}
                                    {a.category && <span>{a.category}</span>}
                                    {overdue !== null && overdue > 0 && (
                                        <span className="font-semibold text-missing">
                                            {plural(overdue, 'day')} overdue
                                        </span>
                                    )}
                                </div>
                                {a.comment && (
                                    <p className="mt-1 border-l-2 border-rule pl-2 text-sm text-muted-foreground italic">
                                        {a.comment}
                                    </p>
                                )}
                            </div>
                            <div className="flex items-baseline justify-between gap-3 sm:justify-end">
                                <div className="flex gap-2">
                                    <StatusBadge status={status} />
                                    {a.late && status !== 'late' && (
                                        <StatusBadge status="late" />
                                    )}
                                </div>
                                <span className="w-24 text-right tabular-nums">
                                    <span className="font-semibold">
                                        {formatScore(a.score, a.pointsPossible)}
                                    </span>
                                    {a.percent !== null && (
                                        <span className="ml-1.5 text-xs text-muted-foreground">
                                            {formatPercent(a.percent)}
                                        </span>
                                    )}
                                </span>
                            </div>
                        </div>
                    </Line>
                );
            })}
        </ul>
    );
}
