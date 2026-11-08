import { router } from '@inertiajs/react';
import Pusher from 'pusher-js';
import { useEffect } from 'react';
import { toast } from 'sonner';

const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;

/**
 * Live contractor notifications from the shared Reverb server (ContractorAlert broadcasts `notification`
 * on private-App.Models.User.{id}): refresh the sidebar badge and show a toast. Without VITE_REVERB_APP_KEY
 * at build time it does nothing; the badge still updates on the next page visit.
 */
export function useLiveNotifications(userId: number | undefined): void {
    useEffect(() => {
        if (!key || !userId) {
            return;
        }

        const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';
        const pusher = new Pusher(key, {
            cluster: '',
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            // Session-authenticated; Laravel exempts this route from CSRF (it only signs the caller's socket).
            channelAuthorization: {
                endpoint: '/broadcasting/auth',
                transport: 'ajax',
            },
        });

        pusher
            .subscribe(`private-App.Models.User.${userId}`)
            .bind('notification', (data: { title: string; body: string }) => {
                toast(data.title, { description: data.body });
                router.reload({ only: ['unreadNotifications'] });
            });

        return () => pusher.disconnect();
    }, [userId]);
}
