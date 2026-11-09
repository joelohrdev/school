import { formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';

export function GradeValue({
    percent,
    letter,
    size = 'md',
    className,
}: {
    percent: number | null;
    letter: string | null;
    size?: 'md' | 'lg';
    className?: string;
}) {
    if (percent === null && letter === null) {
        return (
            <span className={cn('text-sm text-muted-foreground', className)}>
                No grade
            </span>
        );
    }

    return (
        <span
            className={cn(
                'inline-flex items-baseline gap-1.5 tabular-nums',
                className,
            )}
        >
            {letter && (
                <span
                    className={cn(
                        'font-semibold',
                        size === 'lg' ? 'text-5xl tracking-tight' : 'text-base',
                    )}
                >
                    {letter}
                </span>
            )}
            <span
                className={cn(
                    size === 'lg'
                        ? 'text-xl text-muted-foreground'
                        : 'text-sm text-muted-foreground',
                )}
            >
                {formatPercent(percent)}
            </span>
        </span>
    );
}
