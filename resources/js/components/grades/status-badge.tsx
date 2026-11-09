import {
    AlarmClock,
    CircleAlert,
    CircleCheck,
    CircleSlash,
    Hourglass,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { AssignmentRow } from '@/types/gradebook';

export type AssignmentStatus =
    | 'missing'
    | 'late'
    | 'exempt'
    | 'scored'
    | 'pending';

export function assignmentStatus(
    a: Pick<
        AssignmentRow,
        'missing' | 'late' | 'exempt' | 'score' | 'percent' | 'letter'
    >,
): AssignmentStatus {
    if (a.exempt) {
        return 'exempt';
    }

    if (a.missing) {
        return 'missing';
    }

    if (a.late) {
        return 'late';
    }

    return a.score !== null || a.percent !== null || a.letter !== null
        ? 'scored'
        : 'pending';
}

const styles: Record<
    AssignmentStatus,
    { label: string; icon: typeof CircleAlert; className: string }
> = {
    missing: {
        label: 'Missing',
        icon: CircleAlert,
        className: 'bg-missing-soft text-missing border-missing/25',
    },
    late: {
        label: 'Late',
        icon: AlarmClock,
        className: 'bg-late-soft text-late border-late/25',
    },
    exempt: {
        label: 'Exempt',
        icon: CircleSlash,
        className: 'bg-muted text-muted-foreground border-transparent',
    },
    scored: {
        label: 'Scored',
        icon: CircleCheck,
        className: 'bg-good-soft text-good border-good/25',
    },
    pending: {
        label: 'Not graded',
        icon: Hourglass,
        className: 'bg-muted text-muted-foreground border-transparent',
    },
};

export function StatusBadge({
    status,
    label,
    className,
}: {
    status: AssignmentStatus;
    label?: string;
    className?: string;
}) {
    const { label: defaultLabel, icon: Icon, className: tone } = styles[status];

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs font-medium whitespace-nowrap',
                tone,
                className,
            )}
        >
            <Icon className="size-3" aria-hidden />
            {label ?? defaultLabel}
        </span>
    );
}

export function CountBadge({
    status,
    count,
    noun,
}: {
    status: 'missing' | 'late';
    count: number;
    noun?: string;
}) {
    if (count === 0) {
        return null;
    }

    return (
        <StatusBadge
            status={status}
            label={`${count} ${noun ?? styles[status].label.toLowerCase()}`}
        />
    );
}
