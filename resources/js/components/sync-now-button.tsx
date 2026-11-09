import { Form } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { store } from '@/routes/sync';

export function SyncNowButton({
    busy = false,
    size = 'sm',
}: {
    busy?: boolean;
    size?: 'sm' | 'default';
}) {
    return (
        <Form {...store.form()} options={{ preserveScroll: true }}>
            {({ processing }) => (
                <Button
                    type="submit"
                    size={size}
                    variant="outline"
                    disabled={busy || processing}
                >
                    <RefreshCw
                        className={cn(
                            'size-4',
                            (busy || processing) && 'animate-spin',
                        )}
                        aria-hidden
                    />
                    {busy ? 'Syncing…' : 'Sync now'}
                </Button>
            )}
        </Form>
    );
}
