import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Page width and padding shared by every app page. */
export function Page({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 pb-10 sm:px-6 sm:pt-6',
                className,
            )}
        >
            {children}
        </div>
    );
}

/**
 * A sheet of notebook paper with a red margin line. On phones it runs
 * edge to edge; from `sm` up it sits on the desk with a hairline edge.
 */
export function Sheet({
    children,
    className,
    ...props
}: {
    children: ReactNode;
    className?: string;
} & React.HTMLAttributes<HTMLElement>) {
    return (
        <section
            {...props}
            className={cn(
                'sheet border-y border-rule sm:rounded-sm sm:border',
                className,
            )}
        >
            {children}
        </section>
    );
}

/**
 * One ruled line: a margin note left of the red line, content right of it.
 * The margin holds what a person would jot there: a period, a date, a grade level.
 */
export function Line({
    margin,
    children,
    ruled = true,
    className,
    as: Tag = 'div',
}: {
    margin?: ReactNode;
    children: ReactNode;
    ruled?: boolean;
    className?: string;
    as?: 'div' | 'li' | 'header';
}) {
    return (
        <Tag
            className={cn(
                'margin-row items-baseline',
                ruled && 'border-b border-rule last:border-b-0',
                className,
            )}
        >
            <div className="pr-2 text-right text-xs text-muted-foreground tabular-nums sm:pr-3">
                {margin}
            </div>
            <div className="min-w-0 px-3 sm:px-5">{children}</div>
        </Tag>
    );
}

/** The heading at the top of a sheet, written on the first line. */
export function SheetTitle({
    margin,
    title,
    aside,
    sub,
    level = 1,
    id,
}: {
    margin?: ReactNode;
    title: ReactNode;
    aside?: ReactNode;
    sub?: ReactNode;
    level?: 1 | 2;
    id?: string;
}) {
    const H = level === 1 ? 'h1' : 'h2';

    return (
        <Line margin={margin} as="header" className="py-4 sm:py-5">
            <div className="flex flex-wrap items-end justify-between gap-x-4 gap-y-2">
                <div className="min-w-0">
                    <H
                        id={id}
                        className={cn(
                            'leading-tight font-extrabold tracking-tight',
                            level === 1
                                ? 'text-3xl sm:text-4xl'
                                : 'text-2xl sm:text-3xl',
                        )}
                    >
                        {title}
                    </H>
                    {sub && (
                        <div className="mt-1 text-sm text-muted-foreground">
                            {sub}
                        </div>
                    )}
                </div>
                {aside && (
                    <div className="flex flex-wrap items-center gap-2">
                        {aside}
                    </div>
                )}
            </div>
        </Line>
    );
}

/** A small section heading on its own ruled line, in sentence case. */
export function LineHeading({
    children,
    id,
    margin,
    aside,
}: {
    children: ReactNode;
    id?: string;
    margin?: ReactNode;
    aside?: ReactNode;
}) {
    return (
        <Line margin={margin} className="bg-muted/50 py-1.5">
            <div className="flex items-center justify-between gap-3">
                <h2 id={id} className="text-sm font-semibold">
                    {children}
                </h2>
                {aside}
            </div>
        </Line>
    );
}

/** An empty state written on the page, not boxed. */
export function Blank({ children }: { children: ReactNode }) {
    return (
        <Line ruled={false} className="py-8">
            <p className="text-sm text-muted-foreground">{children}</p>
        </Line>
    );
}
