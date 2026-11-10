import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { Line, Page, Sheet, SheetTitle } from '@/components/notebook';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editQuarters } from '@/routes/quarters';
import { edit as editSecurity } from '@/routes/security';

const items = [
    { title: 'Profile', href: edit() },
    { title: 'Security', href: editSecurity() },
    { title: 'Quarters', href: editQuarters() },
    { title: 'Appearance', href: editAppearance() },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <Page>
            <Sheet>
                <SheetTitle
                    title="Settings"
                    sub="Your account, password, school quarters and theme."
                />
                <Line>
                    <nav
                        className="-mb-px flex [scrollbar-width:none] gap-5 overflow-x-auto [&::-webkit-scrollbar]:hidden"
                        aria-label="Settings"
                    >
                        {items.map((item) => {
                            const active = isCurrentOrParentUrl(item.href);

                            return (
                                <Link
                                    key={toUrl(item.href)}
                                    href={item.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'shrink-0 border-b-[3px] pt-3 pb-2 font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        active
                                            ? 'border-foreground text-foreground'
                                            : 'border-transparent text-muted-foreground hover:border-rule hover:text-foreground',
                                    )}
                                >
                                    {item.title}
                                </Link>
                            );
                        })}
                    </nav>
                </Line>
                <Line ruled={false} className="py-6">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </Line>
            </Sheet>
        </Page>
    );
}
