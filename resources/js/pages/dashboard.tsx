import { Head, Link } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { ClassList } from '@/components/grades/class-list';
import { CountBadge } from '@/components/grades/status-badge';
import { LastSync } from '@/components/last-sync';
import { SyncNowButton } from '@/components/sync-now-button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { formatDay, plural } from '@/lib/format';
import { dashboard } from '@/routes';
import { show as showSection } from '@/routes/sections';
import { show as showStudent } from '@/routes/students';
import type { StudentSummary, SyncRunRow } from '@/types/gradebook';

type Props = { students: StudentSummary[]; lastSync: SyncRunRow | null; today: string };

const MISSING_PREVIEW = 5;

export default function Dashboard({ students, lastSync, today }: Props) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">{formatDay(today)}</h1>
                        <LastSync run={lastSync} />
                    </div>
                    <SyncNowButton busy={lastSync?.status === 'running'} />
                </div>

                {students.length === 0 ? (
                    <Card className="items-center gap-3 py-12 text-center">
                        <GraduationCap className="size-8 text-muted-foreground" aria-hidden />
                        <div>
                            <p className="font-medium">No kids yet</p>
                            <p className="text-sm text-muted-foreground">Set your PowerSchool login in .env, then run a sync.</p>
                        </div>
                        <SyncNowButton />
                    </Card>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {students.map((s) => (
                            <StudentCard key={s.id} student={s} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function StudentCard({ student }: { student: StudentSummary }) {
    const extra = student.missing.length - MISSING_PREVIEW;

    return (
        <Card className="gap-4 py-4">
            <CardHeader className="flex flex-row items-start justify-between gap-2 px-4">
                <div>
                    <Link href={showStudent(student.id)} className="text-lg font-semibold hover:underline" prefetch>
                        {student.name}
                    </Link>
                    {student.gradeLevel !== null && <p className="text-xs text-muted-foreground">Grade {student.gradeLevel}</p>}
                </div>
                <div className="flex flex-wrap justify-end gap-1.5">
                    <CountBadge status="missing" count={student.missingCount} />
                    <CountBadge status="late" count={student.lateCount} />
                    {student.missingCount === 0 && student.lateCount === 0 && (
                        <span className="text-xs text-muted-foreground">Nothing missing</span>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-4 px-4">
                {student.missing.length > 0 && (
                    <section aria-labelledby={`missing-${student.id}`}>
                        <h2 id={`missing-${student.id}`} className="mb-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            Missing work
                        </h2>
                        <ul className="divide-y rounded-lg border border-missing/25 bg-missing-soft/40">
                            {student.missing.slice(0, MISSING_PREVIEW).map((m) => (
                                <li key={m.id} className="flex items-center gap-3 px-3 py-2">
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate text-sm font-medium">{m.name}</div>
                                        <Link href={showSection(m.sectionId)} className="truncate text-xs text-muted-foreground hover:underline">
                                            {m.course}
                                        </Link>
                                    </div>
                                    <div className="text-right text-xs">
                                        <div>{formatDay(m.dueOn)}</div>
                                        {m.daysOverdue !== null && m.daysOverdue > 0 && <div className="text-missing">{plural(m.daysOverdue, 'day')} overdue</div>}
                                    </div>
                                </li>
                            ))}
                        </ul>
                        {extra > 0 && (
                            <Link href={showStudent(student.id, { query: { tab: 'assignments', filter: 'missing' } })} className="mt-1.5 inline-block text-xs text-muted-foreground hover:text-foreground hover:underline">
                                +{extra} more missing
                            </Link>
                        )}
                    </section>
                )}

                <section aria-labelledby={`classes-${student.id}`}>
                    <h2 id={`classes-${student.id}`} className="mb-0.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Classes
                    </h2>
                    <ClassList classes={student.classes} />
                </section>
            </CardContent>
        </Card>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
