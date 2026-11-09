import { Head, usePoll } from '@inertiajs/react';
import { CircleAlert, CircleCheck, CircleSlash, LoaderCircle } from 'lucide-react';
import { useEffect } from 'react';
import { SyncNowButton } from '@/components/sync-now-button';
import { formatDateTime, plural, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/sync';
import type { SyncRunRow } from '@/types/gradebook';

type Props = { runs: SyncRunRow[]; busy: boolean };

const statusMeta: Record<SyncRunRow['status'], { label: string; icon: typeof CircleCheck; tone: string }> = {
    succeeded: { label: 'Succeeded', icon: CircleCheck, tone: 'text-good' },
    failed: { label: 'Failed', icon: CircleAlert, tone: 'text-missing' },
    running: { label: 'Running', icon: LoaderCircle, tone: 'text-muted-foreground' },
    skipped: { label: 'Skipped', icon: CircleSlash, tone: 'text-muted-foreground' },
};

const triggerLabel: Record<SyncRunRow['trigger'], string> = {
    schedule: 'Scheduled',
    manual: 'Sync now',
    cli: 'Command line',
};

export default function SyncIndex({ runs, busy }: Props) {
    const { start, stop } = usePoll(3000, { only: ['runs', 'busy'] }, { autoStart: false });

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
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Sync history</h1>
                        <p className="text-sm text-muted-foreground">Runs automatically on school days at 6:30 am and 3:45 pm.</p>
                    </div>
                    <SyncNowButton busy={busy} />
                </div>

                {runs.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">No syncs yet.</p>
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {runs.map((run) => {
                            const { label, icon: Icon, tone } = statusMeta[run.status];

                            return (
                                <li key={run.id} className="p-3">
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span className={cn('inline-flex items-center gap-1.5 text-sm font-medium', tone)}>
                                            <Icon className={cn('size-4', run.status === 'running' && 'animate-spin')} aria-hidden />
                                            {label}
                                        </span>
                                        <span className="text-sm" title={formatDateTime(run.startedAt)}>
                                            {formatDateTime(run.startedAt)}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {triggerLabel[run.trigger]}
                                            {run.dryRun && ' · dry run'}
                                            {run.durationSeconds !== null && ` · ${run.durationSeconds}s`}
                                        </span>
                                        <span className="ml-auto text-xs text-muted-foreground tabular-nums">
                                            {run.status === 'succeeded'
                                                ? `${run.totals.created} new · ${run.totals.updated} changed · ${plural(run.alerts, 'alert')}`
                                                : timeAgo(run.startedAt)}
                                        </span>
                                    </div>
                                    {run.error && (
                                        <details className="mt-1.5">
                                            <summary className="cursor-pointer text-xs text-muted-foreground">Error details</summary>
                                            <pre className="mt-1 overflow-x-auto rounded-md bg-muted p-2 text-xs whitespace-pre-wrap">{run.error}</pre>
                                        </details>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </>
    );
}

SyncIndex.layout = {
    breadcrumbs: [{ title: 'Sync', href: index() }],
};
