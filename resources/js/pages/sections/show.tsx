import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Mail } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AssignmentList } from '@/components/grades/assignment-list';
import { GradeHistoryChart } from '@/components/grades/grade-history-chart';
import { GradeValue } from '@/components/grades/grade-value';
import { Segmented } from '@/components/segmented';
import { show as showStudent } from '@/routes/students';
import type {
    AssignmentRow,
    ClassRow,
    StudentRef,
    TrendPoint,
} from '@/types/gradebook';

type Props = {
    student: StudentRef;
    section: ClassRow & { history: TrendPoint[] };
    assignments: AssignmentRow[];
    today: string;
};

export default function SectionShow({
    student,
    section,
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
            <Head title={`${section.course} · ${student.name}`} />
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-5 p-4">
                <div>
                    <Link
                        href={showStudent(student.id)}
                        className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" aria-hidden />
                        {student.name}
                    </Link>
                    <div className="mt-2 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {section.course}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {[
                                    section.period &&
                                        `Period ${section.period}`,
                                    section.room && `Room ${section.room}`,
                                    section.teacher,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </p>
                            {section.teacherEmail && (
                                <a
                                    href={`mailto:${section.teacherEmail}`}
                                    className="mt-1 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground hover:underline"
                                >
                                    <Mail className="size-3.5" aria-hidden />
                                    {section.teacherEmail}
                                </a>
                            )}
                        </div>
                        <div className="text-right">
                            <GradeValue
                                percent={section.percent}
                                letter={section.letter}
                                size="lg"
                            />
                            {section.term && (
                                <p className="text-xs text-muted-foreground">
                                    Current grade · {section.term}
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                <section className="rounded-xl border p-4">
                    <h2 className="mb-3 text-sm font-medium">Grade history</h2>
                    <GradeHistoryChart points={section.history} />
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">Assignments</h2>
                    {categories.length > 1 && (
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
                                            (a.category ?? 'Uncategorized') ===
                                            c,
                                    ).length,
                                })),
                            ]}
                        />
                    )}
                    <AssignmentList
                        assignments={visible}
                        today={today}
                        showCourse={false}
                        empty="No assignments in this class yet."
                    />
                </section>
            </div>
        </>
    );
}
