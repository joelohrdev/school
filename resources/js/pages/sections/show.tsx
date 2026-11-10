import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Mail } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AssignmentList } from '@/components/grades/assignment-list';
import { CategoryBars } from '@/components/grades/category-bars';
import { GradeHistoryChart } from '@/components/grades/grade-history-chart';
import { GradeValue } from '@/components/grades/grade-value';
import {
    Line,
    LineHeading,
    Page,
    Sheet,
    SheetTitle,
} from '@/components/notebook';
import { Segmented } from '@/components/segmented';
import { formatShortDay } from '@/lib/format';
import { show as showStudent } from '@/routes/students';
import type {
    AssignmentRow,
    ClassRow,
    ReportCardRow,
    StudentRef,
    TrendPoint,
} from '@/types/gradebook';

type Props = {
    student: StudentRef;
    section: ClassRow & { history: TrendPoint[] };
    reportCard: ReportCardRow[];
    assignments: AssignmentRow[];
    today: string;
};

export default function SectionShow({
    student,
    section,
    reportCard,
    assignments,
    today,
}: Props) {
    const categories = useMemo(
        () =>
            [
                ...new Set(
                    assignments.map((a) => a.category ?? 'Uncategorized'),
                ),
            ].sort(),
        [assignments],
    );
    const [category, setCategory] = useState('all');

    const visible =
        category === 'all'
            ? assignments
            : assignments.filter(
                  (a) => (a.category ?? 'Uncategorized') === category,
              );

    return (
        <>
            <Head title={`${section.course}, ${student.name}`} />
            <Page>
                <Link
                    href={showStudent(student.id)}
                    className="inline-flex items-center gap-1 self-start px-4 pt-4 text-sm font-semibold text-muted-foreground hover:text-foreground sm:px-0 sm:pt-0"
                >
                    <ArrowLeft className="size-4" aria-hidden />
                    {student.name}
                </Link>
                <Sheet>
                    <SheetTitle
                        margin={
                            section.period && (
                                <span
                                    title={`Period ${section.period}`}
                                    className="text-sm font-semibold"
                                >
                                    <span className="sr-only">Period </span>
                                    {section.period.match(/^\d+/)?.[0] ??
                                        section.period}
                                </span>
                            )
                        }
                        title={section.course}
                        sub={
                            <div className="flex flex-col gap-0.5">
                                <span className="flex flex-wrap gap-x-3">
                                    {section.teacher && (
                                        <span>{section.teacher}</span>
                                    )}
                                    {section.room && (
                                        <span>Room {section.room}</span>
                                    )}
                                    {section.term && (
                                        <span>{section.term}</span>
                                    )}
                                </span>
                                {section.teacherEmail && (
                                    <a
                                        href={`mailto:${section.teacherEmail}`}
                                        className="inline-flex items-center gap-1 self-start hover:text-foreground hover:underline"
                                    >
                                        <Mail
                                            className="size-3.5"
                                            aria-hidden
                                        />
                                        Email {section.teacher ?? 'teacher'}
                                    </a>
                                )}
                            </div>
                        }
                        aside={
                            <GradeValue
                                percent={section.percent}
                                letter={section.letter}
                                size="lg"
                            />
                        }
                    />

                    <section aria-labelledby="grade-history">
                        <LineHeading id="grade-history">
                            Grade history
                        </LineHeading>
                        <Line ruled={false} className="py-4">
                            <GradeHistoryChart points={section.history} />
                        </Line>
                    </section>

                    <section aria-labelledby="by-category">
                        <LineHeading id="by-category">
                            Average by category
                        </LineHeading>
                        <Line ruled={false} className="py-4">
                            <CategoryBars assignments={assignments} />
                        </Line>
                    </section>

                    {reportCard.length > 0 && (
                        <section aria-labelledby="report-card">
                            <LineHeading
                                id="report-card"
                                margin={<span className="sr-only">Term</span>}
                            >
                                Report card
                            </LineHeading>
                            <ul>
                                {reportCard.map((r) => (
                                    <Line
                                        key={r.id}
                                        as="li"
                                        className="py-2"
                                        margin={
                                            <span className="text-sm font-semibold text-foreground">
                                                {r.term}
                                            </span>
                                        }
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="min-w-0">
                                                <div className="font-semibold">
                                                    {r.title ??
                                                        r.term ??
                                                        'Term'}
                                                    {r.current && (
                                                        <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                            Current
                                                        </span>
                                                    )}
                                                </div>
                                                {r.startsOn && r.endsOn && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {formatShortDay(
                                                            r.startsOn,
                                                        )}{' '}
                                                        to{' '}
                                                        {formatShortDay(
                                                            r.endsOn,
                                                        )}
                                                    </div>
                                                )}
                                                {r.comment && (
                                                    <p className="mt-1 border-l-2 border-rule pl-2 text-sm text-muted-foreground italic">
                                                        {r.comment}
                                                    </p>
                                                )}
                                            </div>
                                            <GradeValue
                                                percent={r.percent}
                                                letter={r.letter}
                                                className="w-28 shrink-0 justify-end"
                                            />
                                        </div>
                                    </Line>
                                ))}
                            </ul>
                        </section>
                    )}

                    <section aria-labelledby="class-assignments">
                        <LineHeading
                            id="class-assignments"
                            margin={<span className="sr-only">Due</span>}
                        >
                            Assignments
                        </LineHeading>
                        {categories.length > 1 && (
                            <Line className="py-2.5">
                                <Segmented
                                    variant="chips"
                                    label="Filter by category"
                                    value={category}
                                    onChange={setCategory}
                                    options={[
                                        {
                                            value: 'all',
                                            label: 'All',
                                            count: assignments.length,
                                        },
                                        ...categories.map((c) => ({
                                            value: c,
                                            label: c,
                                            count: assignments.filter(
                                                (a) =>
                                                    (a.category ??
                                                        'Uncategorized') === c,
                                            ).length,
                                        })),
                                    ]}
                                />
                            </Line>
                        )}
                        <AssignmentList
                            assignments={visible}
                            today={today}
                            showCourse={false}
                            empty="No assignments in this class yet."
                        />
                    </section>
                </Sheet>
            </Page>
        </>
    );
}
