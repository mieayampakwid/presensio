import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export type AuditLogEntry = {
    id: number;
    action: string;
    user: { id: number; username: string } | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    reason: string | null;
    created_at: string;
};

export function AuditHistory({ entries }: { entries: AuditLogEntry[] }) {
    if (!entries || entries.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Riwayat Perubahan
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-muted-foreground text-sm">
                        Belum ada catatan riwayat perubahan.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Riwayat Perubahan</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {entries.map((entry) => (
                    <div
                        key={entry.id}
                        className="space-y-1 border-b pb-3 text-sm last:border-b-0 last:pb-0"
                    >
                        <div className="flex items-center justify-between">
                            <span className="font-medium">
                                {entry.user ? entry.user.username : 'Sistem'}
                            </span>
                            <span className="text-muted-foreground text-xs">
                                {entry.created_at}
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge
                                variant="outline"
                                className="text-xs capitalize"
                            >
                                {entry.action}
                            </Badge>
                            {entry.reason && (
                                <span className="text-muted-foreground text-xs italic">
                                    "{entry.reason}"
                                </span>
                            )}
                        </div>
                        {(entry.old_values || entry.new_values) && (
                            <div className="text-muted-foreground bg-muted mt-1 rounded p-2 font-mono text-xs">
                                {entry.old_values && (
                                    <div>
                                        Sebelum:{' '}
                                        {JSON.stringify(entry.old_values)}
                                    </div>
                                )}
                                {entry.new_values && (
                                    <div>
                                        Sesudah:{' '}
                                        {JSON.stringify(entry.new_values)}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
