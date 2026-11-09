import { Link, usePage } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as alerts } from '@/routes/alerts';
import { show as student } from '@/routes/students';
import { index as sync } from '@/routes/sync';

type Tab = {
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
    active: boolean;
    count?: number;
};

/**
 * The app as a binder: a navy cover band with index tabs, one per kid,
 * and the current page as the sheet the active tab belongs to.
 */
export default function AppBinderLayout({ children }: { children: ReactNode }) {
    const page = usePage<{ student?: { id: number } }>();
    const { auth, kids, unreadAlerts, name } = page.props;
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const getInitials = useInitials();
    const viewedKid = page.props.student?.id;

    const tabs: Tab[] = [
        {
            label: 'Overview',
            href: dashboard(),
            active: isCurrentUrl(dashboard()),
        },
        ...kids.map((k) => ({
            label: k.name,
            href: student(k.id),
            active: viewedKid === k.id,
        })),
        {
            label: 'Alerts',
            href: alerts(),
            active: isCurrentOrParentUrl(alerts()),
            count: unreadAlerts,
        },
        { label: 'Sync', href: sync(), active: isCurrentOrParentUrl(sync()) },
    ];

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="bg-binder text-binder-ink">
                <div className="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 pt-3 sm:px-6">
                    <Link
                        href={dashboard()}
                        className="rounded-sm text-lg font-extrabold tracking-tight text-white focus-visible:ring-2 focus-visible:ring-binder-ink focus-visible:outline-none"
                        prefetch
                    >
                        {name}
                    </Link>

                    {auth.user && (
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                className="flex size-9 items-center justify-center rounded-full border border-binder-ink/30 text-sm font-semibold hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-binder-ink focus-visible:outline-none"
                                aria-label="Account"
                                data-test="sidebar-menu-button"
                            >
                                {getInitials(auth.user.name)}
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <UserMenuContent user={auth.user} />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>

                <nav
                    aria-label="Main"
                    className="mx-auto mt-3 flex w-full max-w-5xl [scrollbar-width:none] gap-1 overflow-x-auto px-3 sm:px-5 [&::-webkit-scrollbar]:hidden"
                >
                    {tabs.map((t) => (
                        <Link
                            key={t.label + toKey(t.href)}
                            href={t.href}
                            prefetch
                            aria-current={t.active ? 'page' : undefined}
                            className={cn(
                                'relative inline-flex shrink-0 items-center gap-1.5 rounded-t-md px-3.5 pt-2 pb-2.5 text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset',
                                t.active
                                    ? 'bg-background text-foreground'
                                    : 'bg-white/[0.07] text-binder-ink hover:bg-white/15',
                            )}
                        >
                            {t.label}
                            {t.count !== undefined && t.count > 0 && (
                                <span
                                    className={cn(
                                        'min-w-5 rounded-full px-1.5 text-center text-xs leading-5 tabular-nums',
                                        t.active
                                            ? 'bg-missing text-background'
                                            : 'bg-missing text-white dark:text-background',
                                    )}
                                >
                                    {t.count}
                                    <span className="sr-only"> unread</span>
                                </span>
                            )}
                        </Link>
                    ))}
                </nav>
            </header>

            <main className="flex min-w-0 flex-1 flex-col">{children}</main>
        </div>
    );
}

function toKey(href: Tab['href']): string {
    return typeof href === 'string' ? href : href.url;
}
