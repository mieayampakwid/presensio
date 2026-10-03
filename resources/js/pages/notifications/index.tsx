import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck, ChevronLeft, ChevronRight } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type NotificationItem = {
    id: string;
    data: {
        key?: string;
        title: string;
        body: string;
        url?: string;
    };
    read_at: string | null;
    created_at: string | null;
};

type Paginator = {
    data: NotificationItem[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
    total: number;
};

type Props = {
    notifications: Paginator;
    unread_only: boolean;
};

export default function NotificationsIndex({
    notifications,
    unread_only,
}: Props) {
    return (
        <>
            <Head title="Notifikasi" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Notifikasi"
                        description="Pusat pemberitahuan aktivitas, pengumuman, dan informasi akun Anda."
                    />

                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                router.post('/notifications/read-all')
                            }
                            className="cursor-pointer"
                        >
                            <CheckCheck className="mr-1.5 size-4" />
                            Tandai semua dibaca
                        </Button>
                    </div>
                </div>

                <div className="flex items-center gap-2 border-b pb-2">
                    <Button
                        variant={!unread_only ? 'default' : 'ghost'}
                        size="sm"
                        asChild
                    >
                        <Link href="/notifications">Semua</Link>
                    </Button>
                    <Button
                        variant={unread_only ? 'default' : 'ghost'}
                        size="sm"
                        asChild
                    >
                        <Link href="/notifications?unread=1">Belum dibaca</Link>
                    </Button>
                </div>

                <div className="space-y-2">
                    {notifications.data.length === 0 ? (
                        <div className="border-border text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm">
                            <Bell className="mx-auto mb-2 size-8 opacity-40" />
                            Tidak ada notifikasi{' '}
                            {unread_only ? 'yang belum dibaca' : ''}.
                        </div>
                    ) : (
                        notifications.data.map((item) => (
                            <div
                                key={item.id}
                                onClick={() =>
                                    router.post(
                                        `/notifications/${item.id}/read`,
                                    )
                                }
                                className={`border-border hover:bg-accent/40 flex cursor-pointer items-start justify-between gap-4 rounded-lg border p-4 transition-colors ${
                                    item.read_at === null
                                        ? 'bg-primary/5 font-medium'
                                        : 'bg-card'
                                }`}
                            >
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        {item.read_at === null && (
                                            <span className="bg-primary size-2 rounded-full" />
                                        )}
                                        <h4 className="text-foreground text-sm font-semibold">
                                            {item.data.title}
                                        </h4>
                                    </div>
                                    <p className="text-muted-foreground text-sm font-normal">
                                        {item.data.body}
                                    </p>
                                </div>
                                {item.created_at && (
                                    <span className="text-muted-foreground shrink-0 text-xs">
                                        {item.created_at}
                                    </span>
                                )}
                            </div>
                        ))
                    )}
                </div>

                {notifications.last_page > 1 && (
                    <div className="flex items-center justify-between border-t pt-4">
                        <span className="text-muted-foreground text-xs">
                            Halaman {notifications.current_page} dari{' '}
                            {notifications.last_page} ({notifications.total}{' '}
                            total)
                        </span>
                        <div className="flex items-center gap-2">
                            {notifications.prev_page_url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={notifications.prev_page_url}>
                                        <ChevronLeft className="mr-1 size-4" />
                                        Sebelumnya
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    <ChevronLeft className="mr-1 size-4" />
                                    Sebelumnya
                                </Button>
                            )}

                            {notifications.next_page_url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={notifications.next_page_url}>
                                        Berikutnya
                                        <ChevronRight className="ml-1 size-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Berikutnya
                                    <ChevronRight className="ml-1 size-4" />
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
