import { Form, Head, Link } from '@inertiajs/react';
import {
    AlarmClock,
    CalendarX2,
    CircleAlert,
    CircleCheck,
    MessageSquareText,
    Sparkles,
    TrendingDown,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Blank, Line, Page, Sheet, SheetTitle } from '@/components/notebook';
import { Segmented } from '@/components/segmented';
import { Button } from '@/components/ui/button';
import {
    formatDay,
    formatPercent,
    formatScore,
    formatShortDate,
} from '@/lib/format';
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
    absence: {
        label: 'Attendance',
        icon: CalendarX2,
        tone: 'text-late',
    },
    comment: {
        label: 'Teacher comment',
        icon: MessageSquareText,
        tone: 'text-foreground',
    },
};

export default function AlertsIndex({ alerts, filter }: Props) {
    const hasUnread = alerts.data.some((a) => !a.readAt);

    return (
        <>
            <Head title="Alerts" />
            <Page>
                <Sheet>
                    <SheetTitle
                        title="Alerts"
                        sub="What changed between syncs. Each one is in the morning email once."
                        aside={
                            (hasUnread || filter === 'unread') && (
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
                            )
                        }
                    />
                    <Line className="bg-muted/50 py-2.5">
                        <Segmented
                            variant="chips"
                            label="Show"
                            value={filter}
                            onChange={(f) =>
                                router.get(
                                    index({
                                        query:
                                            f === 'unread'
                                                ? { filter: 'unread' }
                                                : {},
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
                    </Line>

                    {alerts.data.length === 0 ? (
                        <Blank>
                            {filter === 'unread'
                                ? 'All caught up.'
                                : 'No alerts yet. They start with the second sync, once there is something to compare.'}
                        </Blank>
                    ) : (
                        <ul>
                            {alerts.data.map((a) => (
                                <AlertItem key={a.id} alert={a} />
                            ))}
                        </ul>
                    )}

                    {alerts.last_page > 1 && (
                        <Line
                            ruled={false}
                            className="border-t border-rule py-3"
                        >
                            <nav
                                className="flex items-center justify-between text-sm"
                                aria-label="Pagination"
                            >
                                {alerts.prev_page_url ? (
                                    <Link
                                        href={alerts.prev_page_url}
                                        preserveScroll
                                        className="font-semibold hover:underline"
                                    >
                                        Newer
                                    </Link>
                                ) : (
                                    <span />
                                )}
                                <span className="text-muted-foreground">
                                    Page {alerts.current_page} of{' '}
                                    {alerts.last_page}
                                </span>
                                {alerts.next_page_url ? (
                                    <Link
                                        href={alerts.next_page_url}
                                        preserveScroll
                                        className="font-semibold hover:underline"
                                    >
                                        Older
                                    </Link>
                                ) : (
                                    <span />
                                )}
                            </nav>
                        </Line>
                    )}
                </Sheet>
            </Page>
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
    } else if (alert.type === 'absence') {
        body = (
            <>
                <span className="font-medium">
                    {p.description ?? p.code ?? 'Marked'}
                </span>
                {p.date && (
                    <span className="text-muted-foreground">
                        {' '}
                        on {formatDay(p.date)}
                    </span>
                )}
            </>
        );
    } else if (alert.type === 'comment') {
        body = (
            <>
                <span className="font-medium">{p.assignment_name}</span>:{' '}
                <span className="italic">{p.comment}</span>
            </>
        );
    } else if (alert.type === 'score_posted') {
        body = (
            <>
                <span className="font-medium">{p.assignment_name}</span>:{' '}
                {formatScore(p.score ?? null, p.points_possible ?? null)}
                {p.percent !== null &&
                    p.percent !== undefined &&
                    `, ${formatPercent(p.percent)}`}
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
        <Line
            as="li"
            className={cn('py-2.5', !unread && 'text-muted-foreground')}
            margin={
                <span title={new Date(alert.createdAt).toLocaleString()}>
                    {formatShortDate(alert.createdAt)}
                </span>
            }
        >
            <div className="flex items-baseline gap-3">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-3 text-xs">
                        {unread && (
                            <span
                                className="-mr-1.5 size-2 rounded-full bg-missing"
                                aria-hidden
                            />
                        )}
                        <span
                            className={cn(
                                'inline-flex items-center gap-1 font-semibold',
                                tone,
                            )}
                        >
                            <Icon className="size-3.5" aria-hidden />
                            {label}
                        </span>
                        <span className="font-semibold text-foreground">
                            {alert.student.name}
                        </span>
                        {p.section_id &&
                            p.course_name &&
                            alert.type !== 'grade_drop' && (
                                <Link
                                    href={showSection(p.section_id)}
                                    className="text-muted-foreground hover:text-foreground hover:underline"
                                >
                                    {p.course_name}
                                </Link>
                            )}
                        {unread && <span className="sr-only">Unread</span>}
                    </div>
                    <p className={cn('text-sm', unread && 'text-foreground')}>
                        {body}
                    </p>
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
            </div>
        </Line>
    );
}
