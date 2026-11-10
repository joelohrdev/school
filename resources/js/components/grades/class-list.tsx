import { Link } from '@inertiajs/react';
import { GradeValue } from '@/components/grades/grade-value';
import { Sparkline } from '@/components/grades/sparkline';
import { Blank, Line } from '@/components/notebook';
import { show } from '@/routes/sections';
import type { ClassRow } from '@/types/gradebook';

/** Classes as ruled lines, period number in the margin. */
export function ClassList({
    classes,
    detailed = false,
}: {
    classes: ClassRow[];
    detailed?: boolean;
}) {
    if (classes.length === 0) {
        return <Blank>No classes yet. They appear after the first sync.</Blank>;
    }

    // Early in a quarter no class has a trend yet; drop the column rather
    // than leave a row of gaps.
    const showTrend = classes.some(
        (c) => c.trend.filter((p) => p.percent !== null).length > 1,
    );

    return (
        <ul>
            {classes.map((c) => (
                <Line
                    key={c.id}
                    as="li"
                    margin={c.period && <PeriodNote period={c.period} />}
                >
                    <Link
                        href={show(c.id)}
                        className="group -mx-3 flex items-center gap-3 px-3 py-2 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset sm:-mx-5 sm:px-5"
                        prefetch
                    >
                        <div className="min-w-0 flex-1">
                            <div className="truncate font-semibold group-hover:underline group-hover:decoration-rule group-hover:decoration-2 group-hover:underline-offset-4">
                                {c.course}
                            </div>
                            <div className="flex gap-3 truncate text-xs text-muted-foreground">
                                {c.teacher && <span>{c.teacher}</span>}
                                {detailed && c.room && (
                                    <span>Room {c.room}</span>
                                )}
                            </div>
                        </div>
                        {showTrend && <Sparkline points={c.trend} />}
                        <GradeValue
                            percent={c.percent}
                            letter={c.letter}
                            className="w-28 justify-end"
                        />
                    </Link>
                </Line>
            ))}
        </ul>
    );
}

/** "1(A)" reads as period 1, A day. The margin only has room for the period. */
function PeriodNote({ period }: { period: string }) {
    const short = period.match(/^\d+/)?.[0] ?? period;

    return (
        <span title={`Period ${period}`} className="text-sm font-semibold">
            <span className="sr-only">Period </span>
            {short}
        </span>
    );
}
