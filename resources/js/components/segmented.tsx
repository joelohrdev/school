import { cn } from '@/lib/utils';

export type SegmentOption<T extends string> = { value: T; label: string; count?: number };

/** A row of mutually exclusive choices: tabs (role="tablist") or filter chips. */
export function Segmented<T extends string>({
    options,
    value,
    onChange,
    label,
    variant = 'tabs',
}: {
    options: SegmentOption<T>[];
    value: T;
    onChange: (value: T) => void;
    label: string;
    variant?: 'tabs' | 'chips';
}) {
    if (variant === 'chips') {
        return (
            <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label={label}>
                {options.map((o) => (
                    <button
                        key={o.value}
                        type="button"
                        onClick={() => onChange(o.value)}
                        aria-pressed={value === o.value}
                        className={cn(
                            'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition-colors',
                            value === o.value ? 'border-foreground bg-foreground text-background' : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                        )}
                    >
                        {o.label}
                        {o.count !== undefined && <span className="tabular-nums opacity-70">{o.count}</span>}
                    </button>
                ))}
            </div>
        );
    }

    return (
        <div className="flex border-b" role="tablist" aria-label={label}>
            {options.map((o) => (
                <button
                    key={o.value}
                    type="button"
                    role="tab"
                    aria-selected={value === o.value}
                    onClick={() => onChange(o.value)}
                    className={cn(
                        '-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                        value === o.value ? 'border-foreground text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground',
                    )}
                >
                    {o.label}
                    {o.count !== undefined && o.count > 0 && <span className="rounded-full bg-muted px-1.5 text-xs tabular-nums">{o.count}</span>}
                </button>
            ))}
        </div>
    );
}

/** Read and write a query-string key without a server round trip. */
export function readQuery<T extends string>(key: string, allowed: readonly T[], fallback: T): T {
    if (typeof window === 'undefined') {
        return fallback;
    }

    const v = new URLSearchParams(window.location.search).get(key);

    return (allowed as readonly string[]).includes(v ?? '') ? (v as T) : fallback;
}

export function writeQuery(key: string, value: string, fallback: string): void {
    const url = new URL(window.location.href);

    if (value === fallback) {
        url.searchParams.delete(key);
    } else {
        url.searchParams.set(key, value);
    }

    window.history.replaceState(window.history.state, '', url);
}
