import { Link, router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

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

type NotificationProps = {
    unread_count: number;
    latest10: NotificationItem[];
};

export function NotificationBell() {
    const { notifications } = usePage().props as unknown as {
        notifications?: NotificationProps | null;
    };

    if (!notifications) {
        return null;
    }

    const { unread_count, latest10 } = notifications;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative h-9 w-9 cursor-pointer"
                    aria-label={`Notifikasi (${unread_count} belum dibaca)`}
                >
                    <Bell className="size-5 opacity-80 hover:opacity-100" />
                    {unread_count > 0 && (
                        <span className="bg-destructive text-destructive-foreground absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold">
                            {unread_count > 99 ? '99+' : unread_count}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-80 sm:w-96" align="end">
                <div className="flex items-center justify-between border-b px-4 py-2">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-semibold">
                            Notifikasi
                        </span>
                        {unread_count > 0 && (
                            <span className="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs font-medium">
                                {unread_count} baru
                            </span>
                        )}
                    </div>
                    {unread_count > 0 && (
                        <button
                            type="button"
                            onClick={() =>
                                router.post('/notifications/read-all')
                            }
                            className="text-primary hover:text-primary/80 flex cursor-pointer items-center gap-1 text-xs"
                        >
                            <CheckCheck className="size-3.5" />
                            Tandai semua dibaca
                        </button>
                    )}
                </div>

                <div className="max-h-80 divide-y overflow-y-auto">
                    {latest10.length === 0 ? (
                        <div className="text-muted-foreground p-4 text-center text-xs">
                            Tidak ada notifikasi saat ini.
                        </div>
                    ) : (
                        latest10.map((item) => (
                            <DropdownMenuItem
                                key={item.id}
                                onClick={() =>
                                    router.post(
                                        `/notifications/${item.id}/read`,
                                    )
                                }
                                className={`focus:bg-accent/50 flex cursor-pointer flex-col items-start gap-1 p-3 ${
                                    item.read_at === null
                                        ? 'bg-primary/5 font-medium'
                                        : ''
                                }`}
                            >
                                <div className="flex w-full items-center justify-between gap-2">
                                    <span className="text-foreground line-clamp-1 text-xs font-semibold">
                                        {item.data.title}
                                    </span>
                                    {item.created_at && (
                                        <span className="text-muted-foreground shrink-0 text-[10px]">
                                            {item.created_at}
                                        </span>
                                    )}
                                </div>
                                <p className="text-muted-foreground line-clamp-2 text-xs font-normal">
                                    {item.data.body}
                                </p>
                            </DropdownMenuItem>
                        ))
                    )}
                </div>

                <DropdownMenuSeparator />

                <div className="p-2 text-center">
                    <Link
                        href="/notifications"
                        className="text-primary hover:text-primary/80 block text-xs font-medium"
                    >
                        Lihat semua notifikasi
                    </Link>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
