import { Head, usePoll } from '@inertiajs/react';
import {
    CircleAlert,
    CircleCheck,
    CircleSlash,
    LoaderCircle,
} from 'lucide-react';
import { useEffect } from 'react';
import { Blank, Line, Page, Sheet, SheetTitle } from '@/components/notebook';
import { SyncNowButton } from '@/components/sync-now-button';
import {
    formatDateTime,
    formatShortDate,
    formatTime,
    plural,
    timeAgo,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SyncRunRow } from '@/types/gradebook';

type Props = { runs: SyncRunRow[]; busy: boolean };

const statusMeta: Record<
    SyncRunRow['status'],
    { label: string; icon: typeof CircleCheck; tone: string }
> = {
    succeeded: { label: 'Succeeded', icon: CircleCheck, tone: 'text-good' },
    failed: { label: 'Failed', icon: CircleAlert, tone: 'text-missing' },
    running: {
        label: 'Running',
        icon: LoaderCircle,
        tone: 'text-muted-foreground',
    },
    skipped: {
        label: 'Skipped',
        icon: CircleSlash,
        tone: 'text-muted-foreground',
    },
};

const triggerLabel: Record<SyncRunRow['trigger'], string> = {
    schedule: 'Scheduled',
    manual: 'Sync now',
    cli: 'Command line',
};

export default function SyncIndex({ runs, busy }: Props) {
    const { start, stop } = usePoll(
        3000,
        { only: ['runs', 'busy'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (busy) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [busy, start, stop]);

    return (
        <>
            <Head title="Sync" />
            <Page>
                <Sheet>
                    <SheetTitle
                        title="Sync history"
                        sub="Runs on school days at 6:30 am and 3:45 pm."
                        aside={<SyncNowButton busy={busy} />}
                    />

                    {runs.length === 0 ? (
                        <Blank>
                            No syncs yet. Sync now to pull the first grades.
                        </Blank>
                    ) : (
                        <ul>
                            {runs.map((run) => {
                                const {
                                    label,
                                    icon: Icon,
                                    tone,
                                } = statusMeta[run.status];

                                return (
                                    <Line
                                        key={run.id}
                                        as="li"
                                        className="py-2.5"
                                        margin={
                                            <span
                                                title={formatDateTime(
                                                    run.startedAt,
                                                )}
                                            >
                                                {formatShortDate(run.startedAt)}
                                            </span>
                                        }
                                    >
                                        <div className="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                                            <span
                                                className={cn(
                                                    'inline-flex items-center gap-1.5 self-center text-sm font-semibold',
                                                    tone,
                                                )}
                                            >
                                                <Icon
                                                    className={cn(
                                                        'size-4',
                                                        run.status ===
                                                            'running' &&
                                                            'animate-spin',
                                                    )}
                                                    aria-hidden
                                                />
                                                {label}
                                            </span>
                                            <span className="text-sm">
                                                {formatTime(run.startedAt)}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {[
                                                    triggerLabel[run.trigger],
                                                    run.dryRun && 'dry run',
                                                    run.durationSeconds !==
                                                        null &&
                                                        `${run.durationSeconds}s`,
                                                ]
                                                    .filter(Boolean)
                                                    .join(', ')}
                                            </span>
                                            <span className="text-xs text-muted-foreground tabular-nums sm:ml-auto">
                                                {run.status === 'succeeded'
                                                    ? `${run.totals.created} new, ${run.totals.updated} changed, ${plural(run.alerts, 'alert')}`
                                                    : timeAgo(run.startedAt)}
                                            </span>
                                        </div>
                                        {run.error && (
                                            <details className="mt-1.5 mb-1">
                                                <summary className="cursor-pointer text-xs text-muted-foreground">
                                                    Error details
                                                </summary>
                                                <p className="mt-1 border-l-2 border-missing pl-2 text-xs whitespace-pre-wrap">
                                                    {run.error}
                                                </p>
                                            </details>
                                        )}
                                    </Line>
                                );
                            })}
                        </ul>
                    )}
                </Sheet>
            </Page>
        </>
    );
}
