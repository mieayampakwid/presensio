import { Head, Link, router } from '@inertiajs/react';
import Reader from '@/actions/App/Http/Controllers/Announcements/AnnouncementReaderController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { Announcement, PageData } from './types';
type Row = {
    id: number;
    name: string;
    guardians: {
        name: string;
        has_account: boolean;
        read: boolean;
        acknowledged: boolean;
    }[];
    read: boolean;
    acknowledged: boolean;
};
type Report = {
    kind: string;
    as_of: string;
    totals: {
        total: number;
        read: number;
        acknowledged: number;
        accountless_guardians: number;
    };
    rows: PageData<Row>;
    filter: string;
};
export default function Recipients({
    announcement,
    recipientReport: r,
}: {
    announcement: Announcement;
    recipientReport: Report;
}) {
    return (
        <>
            <Head title="Status penerima" />
            <div className="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
                <Link
                    className="text-sm underline underline-offset-4"
                    href={Reader.show(announcement.id)}
                >
                    Kembali ke pengumuman
                </Link>
                <Heading
                    title="Status penerima"
                    description={`${announcement.title} · Keanggotaan saat ini per ${r.as_of}`}
                />
                <div className="flex flex-wrap gap-x-8 gap-y-3 rounded-lg border p-4 text-sm">
                    <p>
                        Total: <strong>{r.totals.total}</strong>
                    </p>
                    <p>
                        Sudah membaca: <strong>{r.totals.read}</strong>
                    </p>
                    <p>
                        Sudah konfirmasi:{' '}
                        <strong>{r.totals.acknowledged}</strong>
                    </p>
                    <p>
                        Wali tanpa akun aktif:{' '}
                        <strong>{r.totals.accountless_guardians}</strong>
                    </p>
                </div>
                <div className="max-w-xs space-y-2">
                    <Label htmlFor="recipient-status">Filter status</Label>
                    <select
                        id="recipient-status"
                        className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm"
                        value={r.filter}
                        onChange={(e) =>
                            router.get(
                                Reader.recipients.url(announcement.id),
                                e.target.value
                                    ? { status: e.target.value }
                                    : {},
                            )
                        }
                    >
                        <option value="">Semua penerima</option>
                        <option value="unread">Belum membaca</option>
                        <option value="unacknowledged">Belum konfirmasi</option>
                    </select>
                </div>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="p-4">
                                    {r.kind === 'students'
                                        ? 'Siswa'
                                        : 'Pengguna'}
                                </th>
                                {r.kind === 'students' && (
                                    <th className="p-4">Wali</th>
                                )}
                                <th className="p-4">Membaca</th>
                                <th className="p-4">Konfirmasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {r.rows.data.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="p-4 font-medium">
                                        {row.name}
                                    </td>
                                    {r.kind === 'students' && (
                                        <td className="p-4">
                                            {row.guardians.length
                                                ? row.guardians.map((g, i) => (
                                                      <p key={i}>
                                                          {g.name}
                                                          {!g.has_account && (
                                                              <span className="text-muted-foreground">
                                                                  {' '}
                                                                  · Belum
                                                                  memiliki akun
                                                                  aktif
                                                              </span>
                                                          )}
                                                      </p>
                                                  ))
                                                : 'Belum ada wali terhubung'}
                                        </td>
                                    )}
                                    <td className="p-4">
                                        {row.read ? 'Sudah' : 'Belum'}
                                    </td>
                                    <td className="p-4">
                                        {row.acknowledged ? 'Sudah' : 'Belum'}
                                    </td>
                                </tr>
                            ))}
                            {r.rows.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground p-8 text-center"
                                    >
                                        Tidak ada penerima dengan status ini.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between gap-3 text-sm">
                    <span>
                        Halaman {r.rows.current_page} dari {r.rows.last_page}
                    </span>
                    <div className="flex gap-2">
                        {r.rows.prev_page_url && (
                            <Button asChild variant="outline">
                                <Link href={r.rows.prev_page_url}>
                                    Sebelumnya
                                </Link>
                            </Button>
                        )}
                        {r.rows.next_page_url && (
                            <Button asChild variant="outline">
                                <Link href={r.rows.next_page_url}>
                                    Berikutnya
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
