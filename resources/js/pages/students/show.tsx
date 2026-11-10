import { Head, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AssignmentList } from '@/components/grades/assignment-list';
import { ClassList } from '@/components/grades/class-list';
import { DailyCountChart } from '@/components/grades/daily-count-chart';
import { GradeHistoryChart } from '@/components/grades/grade-history-chart';
import { CountBadge, assignmentStatus } from '@/components/grades/status-badge';
import {
    Blank,
    Line,
    LineHeading,
    Page,
    Sheet,
    SheetTitle,
} from '@/components/notebook';
import { Segmented, readQuery, writeQuery } from '@/components/segmented';
import { daysBetween, formatShortDay, gradeOrdinal } from '@/lib/format';
import type {
    AssignmentRow,
    Attendance,
    ClassRow,
    HistoryRow,
    StudentRef,
} from '@/types/gradebook';

type Props = {
    student: StudentRef;
    classes: ClassRow[];
    assignments: AssignmentRow[];
    attendance: Attendance;
    history: HistoryRow[];
    today: string;
};

const TABS = ['classes', 'assignments', 'trends', 'attendance'] as const;
type Tab = (typeof TABS)[number];

const FILTERS = ['all', 'missing', 'late', 'upcoming', 'recent'] as const;
type Filter = (typeof FILTERS)[number];

const RECENT_DAYS = 14;

export default function StudentShow({
    student,
    classes,
    assignments,
    attendance,
    history,
    today,
}: Props) {
    const { url } = usePage();
    const [tab, setTab] = useState<Tab>(() =>
        readQuery(url, 'tab', TABS, 'classes'),
    );
    const [filter, setFilter] = useState<Filter>(() =>
        readQuery(url, 'filter', FILTERS, 'all'),
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
        missing: 'Nothing missing.',
        late: 'Nothing marked late.',
        upcoming: 'Nothing due in the next 7 days.',
        recent: `No scores posted in the last ${RECENT_DAYS} days.`,
    };

    return (
        <>
            <Head title={student.name} />
            <Page>
                <Sheet>
                    <SheetTitle
                        margin={
                            student.gradeLevel !== null && (
                                <span title={`Grade ${student.gradeLevel}`}>
                                    {gradeOrdinal(student.gradeLevel)}
                                </span>
                            )
                        }
                        title={student.fullName}
                        aside={
                            <>
                                <CountBadge
                                    status="missing"
                                    count={groups.missing.length}
                                />
                                <CountBadge
                                    status="late"
                                    count={groups.late.length}
                                />
                            </>
                        }
                    />

                    <Line>
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
                                    count: assignments.length,
                                },
                                { value: 'trends', label: 'Trends' },
                                { value: 'attendance', label: 'Attendance' },
                            ]}
                        />
                    </Line>

                    {tab === 'classes' && (
                        <ClassList classes={classes} detailed />
                    )}

                    {tab === 'assignments' && (
                        <>
                            <Line className="bg-muted/50 py-2.5">
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
                            </Line>
                            <AssignmentList
                                assignments={groups[filter]}
                                today={today}
                                empty={empties[filter]}
                            />
                        </>
                    )}

                    {tab === 'trends' && <Trends history={history} />}

                    {tab === 'attendance' && (
                        <AttendanceSummary attendance={attendance} />
                    )}
                </Sheet>
            </Page>
        </>
    );
}

function Trends({ history }: { history: HistoryRow[] }) {
    return (
        <>
            <section aria-labelledby="trend-average">
                <LineHeading id="trend-average">Class average</LineHeading>
                <Line ruled={false} className="py-4">
                    <p className="mb-3 text-sm text-muted-foreground">
                        The average of current class grades, each day a sync
                        ran.
                    </p>
                    <GradeHistoryChart
                        points={history.map((h) => ({
                            date: h.date,
                            percent: h.average,
                        }))}
                        label="Average"
                        title="Class average"
                    />
                </Line>
            </section>
            <section aria-labelledby="trend-missing">
                <LineHeading id="trend-missing">
                    Missing assignments
                </LineHeading>
                <Line ruled={false} className="py-4">
                    <p className="mb-3 text-sm text-muted-foreground">
                        How many assignments were marked missing each day.
                    </p>
                    <DailyCountChart
                        points={history.map((h) => ({
                            date: h.date,
                            value: h.missing,
                        }))}
                        label="Missing"
                        title="Missing assignments per day"
                    />
                </Line>
            </section>
        </>
    );
}

function AttendanceSummary({ attendance }: { attendance: Attendance }) {
    if (attendance.total === 0) {
        return <Blank>No absences or tardies recorded.</Blank>;
    }

    return (
        <>
            <section aria-labelledby="att-by-type">
                <LineHeading
                    id="att-by-type"
                    margin={<span className="sr-only">Count</span>}
                >
                    By type
                </LineHeading>
                <ul>
                    {attendance.byCode.map((c) => (
                        <Line
                            key={`${c.code}-${c.description}`}
                            as="li"
                            className="py-2"
                            margin={
                                <span className="text-sm font-semibold text-foreground">
                                    {c.count}
                                </span>
                            }
                        >
                            {c.description ?? c.code}
                            {c.code && c.description && (
                                <span className="ml-2 text-xs text-muted-foreground">
                                    {c.code}
                                </span>
                            )}
                        </Line>
                    ))}
                </ul>
            </section>
            <section aria-labelledby="att-recent">
                <LineHeading
                    id="att-recent"
                    margin={<span className="sr-only">Date</span>}
                >
                    Most recent
                </LineHeading>
                <ul>
                    {attendance.recent.map((r, i) => (
                        <Line
                            key={`${r.date}-${i}`}
                            as="li"
                            className="py-2"
                            margin={formatShortDay(r.date)}
                        >
                            {r.description ?? r.code}
                        </Line>
                    ))}
                </ul>
            </section>
        </>
    );
}
