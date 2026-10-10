import { Head, router, useForm } from '@inertiajs/react';
import { AlertCircle, Check, Info, Lock, Unlock } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PeriodRow = {
    period: string;
    status: 'open' | 'closed';
    is_open: boolean;
    closed_at: string | null;
    closed_by: string | null;
};

type Props = {
    periods: PeriodRow[];
};

export default function PeriodsIndex({ periods }: Props) {
    const [reopeningPeriod, setReopeningPeriod] = useState<PeriodRow | null>(
        null,
    );
    const [closingPeriod, setClosingPeriod] = useState<PeriodRow | null>(null);

    const reopenForm = useForm({
        reason: '',
    });

    const handleCloseConfirm = () => {
        if (!closingPeriod) return;
        router.post(
            `/finance/periods/${closingPeriod.period}/close`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setClosingPeriod(null),
            },
        );
    };

    const handleReopenSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!reopeningPeriod) return;
        reopenForm.post(`/finance/periods/${reopeningPeriod.period}/reopen`, {
            preserveScroll: true,
            onSuccess: () => {
                setReopeningPeriod(null);
                reopenForm.reset();
            },
        });
    };

    return (
        <>
            <Head title="Periode Akuntansi" />

            <div className="mx-auto max-w-5xl space-y-6 p-4">
                <Heading
                    title="Periode Akuntansi & Tutup Buku"
                    description="Kelola penguncian buku besar bulanan untuk mencegah posting transaksi ke periode yang telah berlalu."
                />

                {/* Preconditions Notice */}
                <div className="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50/70 p-4 text-blue-900 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-300">
                    <Info className="mt-0.5 h-5 w-5 shrink-0 text-blue-600 dark:text-blue-400" />
                    <div className="space-y-1 text-xs">
                        <span className="font-semibold">
                            Prasyarat Tutup Buku Bulanan:
                        </span>
                        <p>
                            Sebelum periode ditutup, sistem akan memverifikasi
                            bahwa <strong>Neraca Saldo (Trial Balance)</strong>{' '}
                            per akhir bulan tersebut seimbang (Total Debit =
                            Total Kredit). Setelah ditutup, transaksi baru atau
                            pembalikan tidak dapat dimasukkan ke tanggal periode
                            ini.
                        </p>
                    </div>
                </div>

                <div className="bg-card overflow-hidden rounded-lg border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Periode
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status Buku
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Waktu Ditutup
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Ditutup Oleh
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {periods.map((p) => (
                                    <tr
                                        key={p.period}
                                        className="hover:bg-muted/20 border-t"
                                    >
                                        <td className="px-4 py-3 font-mono text-base font-bold">
                                            {p.period}
                                        </td>
                                        <td className="px-4 py-3">
                                            {p.is_open ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-300 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30"
                                                >
                                                    <Unlock className="mr-1 h-3 w-3" />{' '}
                                                    Terbuka
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="default"
                                                    className="bg-slate-700"
                                                >
                                                    <Lock className="mr-1 h-3 w-3" />{' '}
                                                    Ditutup
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3 text-xs whitespace-nowrap">
                                            {p.closed_at
                                                ? new Date(
                                                      p.closed_at,
                                                  ).toLocaleDateString(
                                                      'id-ID',
                                                      {
                                                          year: 'numeric',
                                                          month: 'short',
                                                          day: 'numeric',
                                                          hour: '2-digit',
                                                          minute: '2-digit',
                                                      },
                                                  )
                                                : '-'}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3 text-xs">
                                            {p.closed_by ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {p.is_open ? (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setClosingPeriod(p)
                                                    }
                                                >
                                                    <Lock className="mr-1 h-3.5 w-3.5" />{' '}
                                                    Tutup Periode
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setReopeningPeriod(p)
                                                    }
                                                >
                                                    <Unlock className="mr-1 h-3.5 w-3.5" />{' '}
                                                    Buka Kembali
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Close Confirm Dialog */}
            <Dialog
                open={closingPeriod !== null}
                onOpenChange={(open) => !open && setClosingPeriod(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Konfirmasi Tutup Periode</DialogTitle>
                        <DialogDescription>
                            Apakah Anda yakin ingin menutup periode akuntansi{' '}
                            <strong>{closingPeriod?.period}</strong>? Setelah
                            ditutup, tidak ada transaksi yang dapat diposting
                            pada tanggal bulan ini.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setClosingPeriod(null)}
                        >
                            Batal
                        </Button>
                        <Button onClick={handleCloseConfirm}>
                            Ya, Tutup Periode
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Reopen Dialog */}
            <Dialog
                open={reopeningPeriod !== null}
                onOpenChange={(open) => !open && setReopeningPeriod(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Buka Kembali Periode</DialogTitle>
                        <DialogDescription>
                            Membuka kembali periode{' '}
                            <strong>{reopeningPeriod?.period}</strong>{' '}
                            memerlukan alasan resmi untuk dicatat pada log
                            audit.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleReopenSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="reopen-reason">
                                Alasan Pembukaan Kembali
                            </Label>
                            <Input
                                id="reopen-reason"
                                placeholder="Contoh: Koreksi penyesuaian transaksi audit akhir tahun"
                                value={reopenForm.data.reason}
                                onChange={(e) =>
                                    reopenForm.setData('reason', e.target.value)
                                }
                                required
                            />
                            {reopenForm.errors.reason && (
                                <p className="text-destructive text-xs">
                                    {reopenForm.errors.reason}
                                </p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setReopeningPeriod(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={reopenForm.processing}
                            >
                                Konfirmasi Buka Periode
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

PeriodsIndex.layout = {
    breadcrumbs: [
        { title: 'Keuangan', href: '/finance/accounts' },
        { title: 'Periode Akuntansi', href: '#' },
    ],
};
