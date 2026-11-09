import { Link } from '@inertiajs/react';
import { CircleAlert, CircleCheck } from 'lucide-react';
import { timeAgo } from '@/lib/format';
import { index } from '@/routes/sync';
import type { SyncRunRow } from '@/types/gradebook';

export function LastSync({ run }: { run: SyncRunRow | null }) {
    if (!run) {
        return <span className="text-sm text-muted-foreground">Never synced</span>;
    }

    const failed = run.status === 'failed';
    const Icon = failed ? CircleAlert : CircleCheck;

    return (
        <Link href={index()} className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <Icon className={failed ? 'size-4 text-missing' : 'size-4 text-good'} aria-hidden />
            {failed ? 'Last sync failed' : run.status === 'running' ? 'Syncing now' : 'Synced'} {timeAgo(run.finishedAt ?? run.startedAt)}
        </Link>
    );
}
