import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AssignmentList } from '@/components/grades/assignment-list';
import { ClassList } from '@/components/grades/class-list';
import { CountBadge, assignmentStatus } from '@/components/grades/status-badge';
import { Segmented, readQuery, writeQuery } from '@/components/segmented';
import { daysBetween, formatDay } from '@/lib/format';
import type {
    AssignmentRow,
    Attendance,
    ClassRow,
    StudentRef,
} from '@/types/gradebook';

type Props = {
    student: StudentRef;
    classes: ClassRow[];
    assignments: AssignmentRow[];
    attendance: Attendance;
    today: string;
};

const TABS = ['classes', 'assignments', 'attendance'] as const;
type Tab = (typeof TABS)[number];

const FILTERS = ['all', 'missing', 'late', 'upcoming', 'recent'] as const;
type Filter = (typeof FILTERS)[number];

const RECENT_DAYS = 14;

export default function StudentShow({
    student,
    classes,
    assignments,
    attendance,
    today,
}: Props) {
    const [tab, setTab] = useState<Tab>(() =>
        readQuery('tab', TABS, 'classes'),
    );
    const [filter, setFilter] = useState<Filter>(() =>
        readQuery('filter', FILTERS, 'all'),
    );

    const groups = useMemo(() => {
        const missing = assignments.filter(
            (a) => assignmentStatus(a) === 'missing',
        );
        const late = assignments.filter((a) => a.late && !a.exempt);
        const upcoming = assignments
            .filter(
                (a) =>
                    a.dueOn &&
                    !a.exempt &&
                    daysBetween(today, a.dueOn) >= 0 &&
                    daysBetween(today, a.dueOn) <= 7 &&
                    assignmentStatus(a) !== 'scored',
            )
            .sort((a, b) => (a.dueOn ?? '').localeCompare(b.dueOn ?? ''));
        const recent = assignments
            .filter(
                (a) =>
                    a.scoredAt &&
                    daysBetween(a.scoredAt.slice(0, 10), today) <= RECENT_DAYS,
            )
            .sort((a, b) => (b.scoredAt ?? '').localeCompare(a.scoredAt ?? ''));

        return {
            all: assignments,
            missing,
            late,
            upcoming,
            recent,
        } satisfies Record<Filter, AssignmentRow[]>;
    }, [assignments, today]);

    const changeTab = (t: Tab) => {
        setTab(t);
        writeQuery('tab', t, 'classes');
    };

    const changeFilter = (f: Filter) => {
        setFilter(f);
        writeQuery('filter', f, 'all');
    };

    const empties: Record<Filter, string> = {
        all: 'No assignments yet.',
        missing: 'Nothing missing. 🎉',
        late: 'Nothing marked late.',
        upcoming: 'Nothing due in the next 7 days.',
        recent: `No scores posted in the last ${RECENT_DAYS} days.`,
    };

    return (
        <>
            <Head title={student.name} />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {student.fullName}
                        </h1>
                        {student.gradeLevel !== null && (
                            <p className="text-sm text-muted-foreground">
                                Grade {student.gradeLevel}
                            </p>
                        )}
                    </div>
                    <div className="flex gap-1.5">
                        <CountBadge
                            status="missing"
                            count={groups.missing.length}
                        />
                        <CountBadge status="late" count={groups.late.length} />
                    </div>
                </div>

                <Segmented
                    label="Sections"
                    value={tab}
                    onChange={changeTab}
                    options={[
                        {
                            value: 'classes',
                            label: 'Classes',
                            count: classes.length,
                        },
                        {
                            value: 'assignments',
                            label: 'Assignments',
                            count: groups.missing.length,
                        },
                        { value: 'attendance', label: 'Attendance' },
                    ]}
                />

                {tab === 'classes' && <ClassList classes={classes} detailed />}

                {tab === 'assignments' && (
                    <div className="space-y-3">
                        <Segmented
                            variant="chips"
                            label="Filter assignments"
                            value={filter}
                            onChange={changeFilter}
                            options={[
                                {
                                    value: 'all',
                                    label: 'All',
                                    count: groups.all.length,
                                },
                                {
                                    value: 'missing',
                                    label: 'Missing',
                                    count: groups.missing.length,
                                },
                                {
                                    value: 'late',
                                    label: 'Late',
                                    count: groups.late.length,
                                },
                                {
                                    value: 'upcoming',
                                    label: 'Due in 7 days',
                                    count: groups.upcoming.length,
                                },
                                {
                                    value: 'recent',
                                    label: 'Recent scores',
                                    count: groups.recent.length,
                                },
                            ]}
                        />
                        <AssignmentList
                            assignments={groups[filter]}
                            today={today}
                            empty={empties[filter]}
                        />
                    </div>
                )}

                {tab === 'attendance' && (
                    <AttendanceSummary attendance={attendance} />
                )}
            </div>
        </>
    );
}

function AttendanceSummary({ attendance }: { attendance: Attendance }) {
    if (attendance.total === 0) {
        return (
            <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                No absences or tardies recorded.
            </p>
        );
    }

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <section className="rounded-lg border p-4">
                <h2 className="mb-2 text-sm font-medium">By type</h2>
                <ul className="space-y-1.5">
                    {attendance.byCode.map((c) => (
                        <li
                            key={`${c.code}-${c.description}`}
                            className="flex items-center justify-between gap-3 text-sm"
                        >
                            <span>
                                {c.description ?? c.code}
                                {c.code && c.description && (
                                    <span className="ml-1.5 text-xs text-muted-foreground">
                                        {c.code}
                                    </span>
                                )}
                            </span>
                            <span className="font-semibold tabular-nums">
                                {c.count}
                            </span>
                        </li>
                    ))}
                </ul>
            </section>
            <section className="rounded-lg border p-4">
                <h2 className="mb-2 text-sm font-medium">Most recent</h2>
                <ul className="space-y-1.5">
                    {attendance.recent.map((r, i) => (
                        <li
                            key={`${r.date}-${i}`}
                            className="flex items-center justify-between gap-3 text-sm"
                        >
                            <span>{formatDay(r.date)}</span>
                            <span className="text-muted-foreground">
                                {r.description ?? r.code}
                            </span>
                        </li>
                    ))}
                </ul>
            </section>
        </div>
    );
}
