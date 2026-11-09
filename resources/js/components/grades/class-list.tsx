import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { GradeValue } from '@/components/grades/grade-value';
import { Sparkline } from '@/components/grades/sparkline';
import { show } from '@/routes/sections';
import type { ClassRow } from '@/types/gradebook';

export function ClassList({
    classes,
    detailed = false,
}: {
    classes: ClassRow[];
    detailed?: boolean;
}) {
    if (classes.length === 0) {
        return (
            <p className="py-4 text-sm text-muted-foreground">
                No classes yet.
            </p>
        );
    }

    return (
        <ul className="divide-y">
            {classes.map((c) => (
                <li key={c.id}>
                    <Link
                        href={show(c.id)}
                        className="-mx-2 flex items-center gap-3 rounded-md px-2 py-2.5 hover:bg-accent/60"
                        prefetch
                    >
                        <div className="min-w-0 flex-1">
                            <div className="truncate text-sm font-medium">
                                {c.course}
                            </div>
                            <div className="truncate text-xs text-muted-foreground">
                                {[
                                    c.period && `Period ${c.period}`,
                                    c.teacher,
                                    detailed && c.room && `Room ${c.room}`,
                                ]
                                    .filter(Boolean)
                                    .join(' · ') || '—'}
                            </div>
                        </div>
                        <Sparkline points={c.trend} />
                        <GradeValue
                            percent={c.percent}
                            letter={c.letter}
                            className="w-[4.5rem] justify-end"
                        />
                        <ChevronRight
                            className="size-4 shrink-0 text-muted-foreground"
                            aria-hidden
                        />
                    </Link>
                </li>
            ))}
        </ul>
    );
}
