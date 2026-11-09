import { Link, usePage } from '@inertiajs/react';
import { Line, Sheet, SheetTitle } from '@/components/notebook';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="bg-binder px-4 py-3 sm:px-6">
                <Link
                    href={home()}
                    className="text-lg font-extrabold tracking-tight text-white"
                >
                    {name}
                </Link>
            </header>
            <main className="flex flex-1 items-start justify-center sm:items-center sm:p-6">
                <Sheet className="w-full sm:max-w-md">
                    <SheetTitle level={1} title={title} sub={description} />
                    <Line ruled={false} className="py-6">
                        {children}
                    </Line>
                </Sheet>
            </main>
        </div>
    );
}
