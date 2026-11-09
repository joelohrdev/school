import { cn } from '@/lib/utils';

export type SegmentOption<T extends string> = {
    value: T;
    label: string;
    count?: number;
};

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
            <div
                className="-mx-3 flex [scrollbar-width:none] gap-2 overflow-x-auto px-3 sm:mx-0 sm:flex-wrap sm:px-0 [&::-webkit-scrollbar]:hidden"
                role="group"
                aria-label={label}
            >
                {options.map((o) => (
                    <button
                        key={o.value}
                        type="button"
                        onClick={() => onChange(o.value)}
                        aria-pressed={value === o.value}
                        className={cn(
                            'inline-flex shrink-0 items-center gap-1.5 rounded-sm border px-2.5 py-1 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            value === o.value
                                ? 'border-foreground bg-foreground font-semibold text-background'
                                : 'border-rule bg-card text-muted-foreground hover:border-input hover:text-foreground',
                        )}
                    >
                        {o.label}
                        {o.count !== undefined && (
                            <span className="tabular-nums opacity-70">
                                {o.count}
                            </span>
                        )}
                    </button>
                ))}
            </div>
        );
    }

    return (
        <div
            className="-mb-px flex [scrollbar-width:none] gap-5 overflow-x-auto [&::-webkit-scrollbar]:hidden"
            role="tablist"
            aria-label={label}
        >
            {options.map((o) => (
                <button
                    key={o.value}
                    type="button"
                    role="tab"
                    aria-selected={value === o.value}
                    onClick={() => onChange(o.value)}
                    className={cn(
                        'inline-flex shrink-0 items-center gap-1.5 border-b-[3px] pt-3 pb-2 font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        value === o.value
                            ? 'border-foreground text-foreground'
                            : 'border-transparent text-muted-foreground hover:border-rule hover:text-foreground',
                    )}
                >
                    {o.label}
                    {o.count !== undefined && o.count > 0 && (
                        <span className="text-xs font-normal text-muted-foreground tabular-nums">
                            {o.count}
                        </span>
                    )}
                </button>
            ))}
        </div>
    );
}

/**
 * Read a query-string key from the Inertia page URL. Use the page URL, not
 * window.location, so the server render and the first client render agree.
 */
export function readQuery<T extends string>(
    pageUrl: string,
    key: string,
    allowed: readonly T[],
    fallback: T,
): T {
    const v = new URL(pageUrl, 'http://localhost').searchParams.get(key);

    return (allowed as readonly string[]).includes(v ?? '')
        ? (v as T)
        : fallback;
}

/** Update a query-string key without a server round trip. */
export function writeQuery(key: string, value: string, fallback: string): void {
    const url = new URL(window.location.href);

    if (value === fallback) {
        url.searchParams.delete(key);
    } else {
        url.searchParams.set(key, value);
    }

    window.history.replaceState(window.history.state, '', url);
}
