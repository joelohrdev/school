import { Form, Head, Link } from '@inertiajs/react';
import {
    AlarmClock,
    CircleAlert,
    CircleCheck,
    Sparkles,
    TrendingDown,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Segmented } from '@/components/segmented';
import { Button } from '@/components/ui/button';
import { formatDay, formatPercent, formatScore, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, read, readAll } from '@/routes/alerts';
import { show as showSection } from '@/routes/sections';
import type { AlertRow, AlertType, Paginated } from '@/types/gradebook';
import { router } from '@inertiajs/react';

type Props = { alerts: Paginated<AlertRow>; filter: 'all' | 'unread' };

const meta: Record<
    AlertType,
    { label: string; icon: typeof CircleAlert; tone: string }
> = {
    missing: { label: 'Missing', icon: CircleAlert, tone: 'text-missing' },
    late: { label: 'Late', icon: AlarmClock, tone: 'text-late' },
    grade_drop: {
        label: 'Grade drop',
        icon: TrendingDown,
        tone: 'text-missing',
    },
    missing_resolved: {
        label: 'Turned in',
        icon: CircleCheck,
        tone: 'text-good',
    },
    score_posted: {
        label: 'New score',
        icon: Sparkles,
        tone: 'text-grade-line',
    },
};

export default function AlertsIndex({ alerts, filter }: Props) {
    const hasUnread = alerts.data.some((a) => !a.readAt);

    return (
        <>
            <Head title="Alerts" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Alerts
                    </h1>
                    {(hasUnread || filter === 'unread') && (
                        <Form
                            {...readAll.form()}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Mark all read
                                </Button>
                            )}
                        </Form>
                    )}
                </div>

                <Segmented
                    variant="chips"
                    label="Show"
                    value={filter}
                    onChange={(f) =>
                        router.get(
                            index({
                                query:
                                    f === 'unread' ? { filter: 'unread' } : {},
                            }).url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                    options={[
                        { value: 'all', label: 'All' },
                        { value: 'unread', label: 'Unread' },
                    ]}
                />

                {alerts.data.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                        {filter === 'unread'
                            ? 'All caught up.'
                            : 'No alerts yet. They appear after the second sync, when there is something to compare.'}
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {alerts.data.map((a) => (
                            <AlertItem key={a.id} alert={a} />
                        ))}
                    </ul>
                )}

                {alerts.last_page > 1 && (
                    <nav
                        className="flex items-center justify-between text-sm"
                        aria-label="Pagination"
                    >
                        {alerts.prev_page_url ? (
                            <Link href={alerts.prev_page_url} preserveScroll>
                                ← Newer
                            </Link>
                        ) : (
                            <span />
                        )}
                        <span className="text-muted-foreground">
                            Page {alerts.current_page} of {alerts.last_page}
                        </span>
                        {alerts.next_page_url ? (
                            <Link href={alerts.next_page_url} preserveScroll>
                                Older →
                            </Link>
                        ) : (
                            <span />
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

function AlertItem({ alert }: { alert: AlertRow }) {
    const { label, icon: Icon, tone } = meta[alert.type];
    const p = alert.payload;
    const unread = !alert.readAt;

    let body: ReactNode;

    if (alert.type === 'grade_drop') {
        body = (
            <>
                <span className="font-medium">{p.course_name}</span> fell from{' '}
                {formatPercent(p.previous_percent)} to{' '}
                <span className="font-medium">{formatPercent(p.percent)}</span>
                {p.letter_grade && ` (${p.letter_grade})`}
            </>
        );
    } else if (alert.type === 'score_posted') {
        body = (
            <>
                <span className="font-medium">{p.assignment_name}</span>:{' '}
                {formatScore(p.score ?? null, p.points_possible ?? null)}
                {p.percent !== null &&
                    p.percent !== undefined &&
                    ` · ${formatPercent(p.percent)}`}
            </>
        );
    } else {
        body = (
            <>
                <span className="font-medium">{p.assignment_name}</span>
                {p.due_on && (
                    <span className="text-muted-foreground">
                        , due {formatDay(p.due_on)}
                    </span>
                )}
            </>
        );
    }

    return (
        <li className={cn('flex gap-3 p-3', unread && 'bg-accent/40')}>
            <Icon className={cn('mt-0.5 size-4 shrink-0', tone)} aria-hidden />
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                    <span className={cn('font-medium', tone)}>{label}</span>
                    <span>{alert.student.name}</span>
                    {p.section_id &&
                        p.course_name &&
                        alert.type !== 'grade_drop' && (
                            <Link
                                href={showSection(p.section_id)}
                                className="hover:text-foreground hover:underline"
                            >
                                {p.course_name}
                            </Link>
                        )}
                    <span>{timeAgo(alert.createdAt)}</span>
                </div>
                <p className="text-sm">{body}</p>
            </div>
            {unread && (
                <Form
                    {...read.form(alert.id)}
                    options={{ preserveScroll: true }}
                    className="shrink-0"
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            size="sm"
                            variant="ghost"
                            className="h-7 px-2 text-xs"
                            disabled={processing}
                        >
                            <span className="sr-only">
                                Mark {label.toLowerCase()} alert for{' '}
                                {p.assignment_name ?? p.course_name} as{' '}
                            </span>
                            Read
                        </Button>
                    )}
                </Form>
            )}
        </li>
    );
}

AlertsIndex.layout = {
    breadcrumbs: [{ title: 'Alerts', href: index() }],
};
