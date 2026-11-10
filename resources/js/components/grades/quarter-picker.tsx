import { Link, router } from '@inertiajs/react';
import { Segmented } from '@/components/segmented';
import { formatShortDay } from '@/lib/format';
import { edit as editQuarters } from '@/routes/quarters';
import type { QuarterPicker as Picker } from '@/types/gradebook';

const YEAR = 'year';

/**
 * Chips for each quarter so far plus the whole year. Picking one reloads the
 * page's data for that quarter, keeping the rest of the query string (the
 * open tab, the filter) as it is.
 */
export function QuarterPicker({ quarters }: { quarters: Picker }) {
    if (quarters.options.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                To group by quarter,{' '}
                <Link
                    href={editQuarters()}
                    className="font-semibold text-foreground underline underline-offset-4"
                >
                    enter the quarter dates
                </Link>
                .
            </p>
        );
    }

    const selected = quarters.options.find((q) => q.key === quarters.selected);

    const change = (key: string) => {
        const url = new URL(window.location.href);
        url.searchParams.set('quarter', key);

        router.get(
            url.pathname + url.search,
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
            <Segmented
                variant="chips"
                label="Quarter"
                value={quarters.selected}
                onChange={change}
                options={[
                    ...quarters.options.map((q) => ({
                        value: q.key,
                        label: q.name,
                    })),
                    { value: YEAR, label: 'Whole year' },
                ]}
            />
            <span className="text-xs text-muted-foreground">
                {selected
                    ? `${formatShortDay(selected.startsOn)} to ${formatShortDay(selected.endsOn)}`
                    : 'All quarters'}
            </span>
        </div>
    );
}
