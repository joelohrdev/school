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
    // Red pen: underlined, like a teacher's note in the margin.
    missing: {
        label: 'Missing',
        icon: CircleAlert,
        className:
            'text-missing font-semibold underline decoration-2 underline-offset-[3px] decoration-missing/60',
    },
    // Highlighter swipe.
    late: {
        label: 'Late',
        icon: AlarmClock,
        className: 'highlighted px-1 font-semibold',
    },
    exempt: {
        label: 'Exempt',
        icon: CircleSlash,
        className: 'text-muted-foreground',
    },
    scored: {
        label: 'Scored',
        icon: CircleCheck,
        className: 'text-good',
    },
    pending: {
        label: 'Not graded',
        icon: Hourglass,
        className: 'text-muted-foreground',
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
                'inline-flex shrink-0 items-center gap-1 text-xs whitespace-nowrap',
                tone,
                className,
            )}
        >
            <Icon className="size-3.5 shrink-0" aria-hidden />
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
