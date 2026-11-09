import { Head, Link } from '@inertiajs/react';
import { ClassList } from '@/components/grades/class-list';
import { CountBadge } from '@/components/grades/status-badge';
import { LastSync } from '@/components/last-sync';
import {
    Line,
    LineHeading,
    Page,
    Sheet,
    SheetTitle,
} from '@/components/notebook';
import { SyncNowButton } from '@/components/sync-now-button';
import {
    formatLongDay,
    formatShortDay,
    gradeOrdinal,
    plural,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { show as showSection } from '@/routes/sections';
import { show as showStudent } from '@/routes/students';
import type { StudentSummary, SyncRunRow } from '@/types/gradebook';

type Props = {
    students: StudentSummary[];
    lastSync: SyncRunRow | null;
    today: string;
};

const MISSING_PREVIEW = 5;

const UPCOMING_PREVIEW = 5;

export default function Dashboard({ students, lastSync, today }: Props) {
    return (
        <>
            <Head title="Overview" />
            <Page>
                <div className="flex flex-wrap items-end justify-between gap-3 px-4 pt-5 sm:px-0 sm:pt-0">
                    <div>
                        <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">
                            {formatLongDay(today)}
                        </h1>
                        <LastSync run={lastSync} />
                    </div>
                    <SyncNowButton busy={lastSync?.status === 'running'} />
                </div>

                {students.length === 0 ? (
                    <Sheet>
                        <SheetTitle level={2} title="No kids yet" />
                        <Line ruled={false} className="pb-6">
                            <p className="text-sm text-muted-foreground">
                                Add your PowerSchool login to .env, then sync.
                                Each kid gets their own page here.
                            </p>
                        </Line>
                    </Sheet>
                ) : (
                    students.map((s) => <StudentSheet key={s.id} student={s} />)
                )}
            </Page>
        </>
    );
}

function StudentSheet({ student }: { student: StudentSummary }) {
    const extra = student.missing.length - MISSING_PREVIEW;
    const clear = student.missingCount === 0 && student.lateCount === 0;

    return (
        <Sheet aria-labelledby={`kid-${student.id}`}>
            <SheetTitle
                level={2}
                id={`kid-${student.id}`}
                margin={
                    student.gradeLevel !== null && (
                        <span title={`Grade ${student.gradeLevel}`}>
                            {gradeOrdinal(student.gradeLevel)}
                        </span>
                    )
                }
                title={
                    <Link
                        href={showStudent(student.id)}
                        className="hover:underline hover:decoration-rule hover:decoration-4 hover:underline-offset-[6px]"
                        prefetch
                    >
                        {student.name}
                    </Link>
                }
                aside={
                    clear ? (
                        <span className="text-sm text-good">
                            Nothing missing or late
                        </span>
                    ) : (
                        <>
                            <CountBadge
                                status="missing"
                                count={student.missingCount}
                            />
                            <CountBadge
                                status="late"
                                count={student.lateCount}
                            />
                        </>
                    )
                }
            />

            {student.missing.length > 0 && (
                <section aria-labelledby={`missing-${student.id}`}>
                    <LineHeading
                        id={`missing-${student.id}`}
                        margin={<span className="sr-only">Due</span>}
                    >
                        Missing work
                    </LineHeading>
                    <ul>
                        {student.missing.slice(0, MISSING_PREVIEW).map((m) => (
                            <Line
                                key={m.id}
                                as="li"
                                margin={m.dueOn && formatShortDay(m.dueOn)}
                                className="py-2"
                            >
                                <div className="flex items-baseline justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="truncate font-semibold">
                                            {m.name}
                                        </div>
                                        <Link
                                            href={showSection(m.sectionId)}
                                            className="truncate text-xs text-muted-foreground hover:text-foreground hover:underline"
                                        >
                                            {m.course}
                                        </Link>
                                    </div>
                                    {m.daysOverdue !== null &&
                                        m.daysOverdue > 0 && (
                                            <span className="shrink-0 text-xs font-semibold text-missing">
                                                {plural(m.daysOverdue, 'day')}{' '}
                                                overdue
                                            </span>
                                        )}
                                </div>
                            </Line>
                        ))}
                        {extra > 0 && (
                            <Line as="li" className="py-2">
                                <Link
                                    href={showStudent(student.id, {
                                        query: {
                                            tab: 'assignments',
                                            filter: 'missing',
                                        },
                                    })}
                                    className="text-sm font-semibold text-missing hover:underline"
                                >
                                    See all {student.missing.length} missing
                                </Link>
                            </Line>
                        )}
                    </ul>
                </section>
            )}

            {student.upcoming.length > 0 && (
                <section aria-labelledby={`upcoming-${student.id}`}>
                    <LineHeading
                        id={`upcoming-${student.id}`}
                        margin={<span className="sr-only">Due</span>}
                    >
                        Due this week
                    </LineHeading>
                    <ul>
                        {student.upcoming
                            .slice(0, UPCOMING_PREVIEW)
                            .map((u) => (
                                <Line
                                    key={u.id}
                                    as="li"
                                    margin={u.dueOn && formatShortDay(u.dueOn)}
                                    className="py-2"
                                >
                                    <div className="flex items-baseline justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="truncate font-semibold">
                                                {u.name}
                                            </div>
                                            <Link
                                                href={showSection(u.sectionId)}
                                                className="truncate text-xs text-muted-foreground hover:text-foreground hover:underline"
                                            >
                                                {u.course}
                                            </Link>
                                        </div>
                                        {u.daysUntil !== null && (
                                            <span
                                                className={cn(
                                                    'shrink-0 text-xs',
                                                    u.daysUntil <= 1
                                                        ? 'font-semibold text-foreground'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {dueIn(u.daysUntil)}
                                            </span>
                                        )}
                                    </div>
                                </Line>
                            ))}
                        {student.upcoming.length > UPCOMING_PREVIEW && (
                            <Line as="li" className="py-2">
                                <Link
                                    href={showStudent(student.id, {
                                        query: {
                                            tab: 'assignments',
                                            filter: 'upcoming',
                                        },
                                    })}
                                    className="text-sm font-semibold hover:underline"
                                >
                                    See all {student.upcoming.length} due this
                                    week
                                </Link>
                            </Line>
                        )}
                    </ul>
                </section>
            )}

            <section aria-labelledby={`classes-${student.id}`}>
                <LineHeading
                    id={`classes-${student.id}`}
                    margin={<span className="sr-only">Period</span>}
                >
                    Classes
                </LineHeading>
                <ClassList classes={student.classes} />
            </section>
        </Sheet>
    );
}

function dueIn(days: number): string {
    if (days <= 0) {
        return 'Due today';
    }

    return days === 1 ? 'Due tomorrow' : `In ${days} days`;
}
