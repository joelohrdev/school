import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AssignmentList } from '@/components/grades/assignment-list';
import { ClassList } from '@/components/grades/class-list';
import { DailyCountChart } from '@/components/grades/daily-count-chart';
import { GradeHistoryChart } from '@/components/grades/grade-history-chart';
import { QuarterPicker } from '@/components/grades/quarter-picker';
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
import {
    daysBetween,
    formatShortDay,
    gradeOrdinal,
    plural,
} from '@/lib/format';
import { show as showSection } from '@/routes/sections';
import type {
    AssignmentRow,
    Attendance,
    ClassRow,
    HistoryRow,
    QuarterPicker as Quarters,
    StudentRef,
} from '@/types/gradebook';

type Props = {
    student: StudentRef;
    quarters: Quarters;
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
    quarters,
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

    const inYear = quarters.selected === 'year';

    const empties: Record<Filter, string> = {
        all: inYear
            ? 'No assignments yet.'
            : 'No assignments due this quarter.',
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

                    <Line className="bg-muted/50 py-2.5">
                        <QuarterPicker quarters={quarters} />
                    </Line>

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
                            <AssignmentsByClass
                                classes={classes}
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

/**
 * Assignments under a heading per class, in period order, each class's
 * work in the order the filter sorted it. Classes with nothing to show
 * are left out.
 */
function AssignmentsByClass({
    classes,
    assignments,
    today,
    empty,
}: {
    classes: ClassRow[];
    assignments: AssignmentRow[];
    today: string;
    empty: string;
}) {
    if (assignments.length === 0) {
        return <Blank>{empty}</Blank>;
    }

    const known = new Set(classes.map((c) => c.id));
    const groups = [
        ...classes.map((c) => ({
            id: c.id,
            course: c.course,
            period: c.period,
            rows: assignments.filter((a) => a.sectionId === c.id),
        })),
        // Work from a class no longer on the schedule still gets shown.
        ...[
            ...new Set(
                assignments
                    .filter((a) => !known.has(a.sectionId))
                    .map((a) => a.sectionId),
            ),
        ].map((id) => {
            const rows = assignments.filter((a) => a.sectionId === id);

            return { id, course: rows[0].course, period: null, rows };
        }),
    ].filter((g) => g.rows.length > 0);

    return (
        <>
            {groups.map((g) => {
                const missing = g.rows.filter(
                    (a) => assignmentStatus(a) === 'missing',
                ).length;

                return (
                    <section key={g.id} aria-labelledby={`class-${g.id}`}>
                        <LineHeading
                            id={`class-${g.id}`}
                            margin={
                                g.period && (
                                    <span
                                        title={`Period ${g.period}`}
                                        className="font-semibold text-foreground"
                                    >
                                        <span className="sr-only">Period </span>
                                        {g.period.match(/^\d+/)?.[0] ??
                                            g.period}
                                    </span>
                                )
                            }
                            aside={
                                <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                    {plural(g.rows.length, 'assignment')}
                                    {missing > 0 && (
                                        <span className="font-semibold text-missing">
                                            , {missing} missing
                                        </span>
                                    )}
                                </span>
                            }
                        >
                            <Link
                                href={showSection(g.id)}
                                className="hover:underline hover:decoration-rule hover:decoration-2 hover:underline-offset-4"
                            >
                                {g.course}
                            </Link>
                        </LineHeading>
                        <AssignmentList
                            assignments={g.rows}
                            today={today}
                            showCourse={false}
                            empty={empty}
                        />
                    </section>
                );
            })}
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
