import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    Download,
    Pencil,
    RotateCcw,
    Send,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
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

type JournalLineRow = {
    id: number;
    account_id: number;
    account_code: string;
    account_name: string;
    debit: string;
    credit: string;
    student_id: number | null;
    student_name: string | null;
    memo: string | null;
};

type RelatedEntry = {
    id: number;
    number: string | null;
    entry_date: string;
    description: string;
};

type Props = {
    journal: {
        id: number;
        number: string | null;
        entry_date: string;
        description: string;
        status: 'draft' | 'posted';
        status_label: string;
        source_type: string;
        source_type_label: string;
        source_id: number | null;
        reverses_entry: RelatedEntry | null;
        reversal_entries: RelatedEntry[];
        has_attachment: boolean;
        author: string | null;
        posted_at: string | null;
        total_debit: string;
        total_credit: string;
        is_balanced: boolean;
        lines: JournalLineRow[];
    };
};

export default function JournalShow({ journal }: Props) {
    const [isReverseOpen, setIsReverseOpen] = useState(false);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);

    const reverseForm = useForm({
        reason: '',
        date: new Date().toISOString().slice(0, 10),
    });

    const handlePostDraft = () => {
        router.post(`/finance/journals/${journal.id}/post`, {}, {
            preserveScroll: true,
        });
    };

    const handleDeleteDraft = () => {
        router.delete(`/finance/journals/${journal.id}`, {
            onSuccess: () => setIsDeleteOpen(false),
        });
    };

    const handleReverseSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        reverseForm.post(`/finance/journals/${journal.id}/reverse`, {
            preserveScroll: true,
            onSuccess: () => {
                setIsReverseOpen(false);
                reverseForm.reset();
            },
        });
    };

    return (
        <>
            <Head title={`Jurnal ${journal.number ?? '(Draf)'}`} />

            <div className="space-y-6 p-4 max-w-5xl mx-auto">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <Button variant="ghost" size="sm" asChild className="mb-2">
                            <Link href="/finance/journals">
                                <ArrowLeft className="h-4 w-4 mr-1" /> Kembali ke Daftar Jurnal
                            </Link>
                        </Button>
                        <Heading
                            title={journal.number ? `Ayat Jurnal: ${journal.number}` : 'Draf Ayat Jurnal'}
                            description={journal.description}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {journal.status === 'draft' ? (
                            <>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`/finance/journals/${journal.id}/edit`}>
                                        <Pencil className="h-4 w-4 mr-1" /> Edit Draf
                                    </Link>
                                </Button>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="text-destructive"
                                    onClick={() => setIsDeleteOpen(true)}
                                >
                                    <Trash2 className="h-4 w-4 mr-1" /> Hapus
                                </Button>

                                <Button size="sm" onClick={handlePostDraft}>
                                    <Send className="h-4 w-4 mr-1" /> Posting ke Buku Besar
                                </Button>
                            </>
                        ) : (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setIsReverseOpen(true)}
                            >
                                <RotateCcw className="h-4 w-4 mr-1" /> Buat Jurnal Pembalik
                            </Button>
                        )}
                    </div>
                </div>

                {/* Metadata Card */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-lg border bg-card text-sm">
                    <div>
                        <span className="text-xs text-muted-foreground block">Tanggal Transaksi</span>
                        <span className="font-semibold">{journal.entry_date}</span>
                    </div>

                    <div>
                        <span className="text-xs text-muted-foreground block">Status</span>
                        {journal.status === 'posted' ? (
                            <Badge variant="default" className="bg-emerald-600">Diposting</Badge>
                        ) : (
                            <Badge variant="secondary">Draf</Badge>
                        )}
                    </div>

                    <div>
                        <span className="text-xs text-muted-foreground block">Sumber Transaksi</span>
                        <span className="font-medium">{journal.source_type_label}</span>
                    </div>

                    <div>
                        <span className="text-xs text-muted-foreground block">Dibuat Oleh</span>
                        <span className="font-medium">{journal.author ?? 'Sistem'}</span>
                    </div>

                    {journal.reverses_entry && (
                        <div className="col-span-2 sm:col-span-4 p-3 bg-amber-50 dark:bg-amber-950/30 rounded border border-amber-200 text-xs">
                            <span className="font-semibold text-amber-800 dark:text-amber-300">
                                Membalikkan Jurnal:{' '}
                            </span>
                            <Link
                                href={`/finance/journals/${journal.reverses_entry.id}`}
                                className="text-primary underline ml-1"
                            >
                                {journal.reverses_entry.number} ({journal.reverses_entry.description})
                            </Link>
                        </div>
                    )}

                    {journal.reversal_entries.length > 0 && (
                        <div className="col-span-2 sm:col-span-4 p-3 bg-rose-50 dark:bg-rose-950/30 rounded border border-rose-200 text-xs">
                            <span className="font-semibold text-rose-800 dark:text-rose-300">
                                Dibalikkan Oleh Jurnal:{' '}
                            </span>
                            {journal.reversal_entries.map((rev) => (
                                <Link
                                    key={rev.id}
                                    href={`/finance/journals/${rev.id}`}
                                    className="text-primary underline mr-2"
                                >
                                    {rev.number}
                                </Link>
                            ))}
                        </div>
                    )}

                    {journal.has_attachment && (
                        <div className="col-span-2 sm:col-span-4 pt-2">
                            <Button variant="outline" size="sm" asChild>
                                <a href={`/finance/journals/${journal.id}/attachment`} download>
                                    <Download className="h-4 w-4 mr-1.5" /> Unduh Lampiran Nota/Kuitansi
                                </a>
                            </Button>
                        </div>
                    )}
                </div>

                {/* Journal Lines Table */}
                <div className="rounded-lg border bg-card overflow-hidden">
                    <div className="p-4 bg-muted/30 border-b">
                        <span className="font-semibold text-sm">Ayat Jurnal (Debit & Kredit)</span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Kode</th>
                                    <th className="px-4 py-3 font-medium">Nama Akun</th>
                                    <th className="px-4 py-3 font-medium text-right">Debit (Rp)</th>
                                    <th className="px-4 py-3 font-medium text-right">Kredit (Rp)</th>
                                    <th className="px-4 py-3 font-medium">Siswa</th>
                                    <th className="px-4 py-3 font-medium">Catatan / Memo</th>
                                </tr>
                            </thead>
                            <tbody>
                                {journal.lines.map((line) => (
                                    <tr key={line.id} className="border-t">
                                        <td className="px-4 py-3 font-mono font-medium">
                                            {line.account_code}
                                        </td>
                                        <td className="px-4 py-3 font-medium">
                                            {line.account_name}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-right">
                                            {Number(line.debit) > 0 ? (
                                                <span className="font-semibold text-blue-700 dark:text-blue-400">
                                                    {Number(line.debit).toLocaleString('id-ID')}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">-</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-right">
                                            {Number(line.credit) > 0 ? (
                                                <span className="font-semibold text-amber-700 dark:text-amber-400">
                                                    {Number(line.credit).toLocaleString('id-ID')}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">-</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            {line.student_name ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            {line.memo ?? '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Total Footer */}
                    <div className="p-4 bg-muted/40 border-t flex flex-col sm:flex-row items-center justify-between gap-4 font-mono text-sm">
                        <div className="flex items-center gap-2">
                            {journal.is_balanced ? (
                                <span className="inline-flex items-center text-xs text-emerald-600 font-medium">
                                    <CheckCircle2 className="h-4 w-4 mr-1" /> Seimbang (Balanced)
                                </span>
                            ) : (
                                <span className="inline-flex items-center text-xs text-destructive font-medium">
                                    <AlertCircle className="h-4 w-4 mr-1" /> Tidak Seimbang
                                </span>
                            )}
                        </div>

                        <div className="flex items-center gap-6">
                            <div>
                                <span className="text-muted-foreground mr-2">Total Debit:</span>
                                <span className="font-bold">
                                    Rp {Number(journal.total_debit).toLocaleString('id-ID')}
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground mr-2">Total Kredit:</span>
                                <span className="font-bold">
                                    Rp {Number(journal.total_credit).toLocaleString('id-ID')}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Reversal Modal */}
            <Dialog open={isReverseOpen} onOpenChange={setIsReverseOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Buat Jurnal Pembalik (Reversal)</DialogTitle>
                        <DialogDescription>
                            Sistem akan membuat ayat jurnal baru yang membalik debit dan kredit dari jurnal{' '}
                            <strong>{journal.number}</strong> secara otomatis.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleReverseSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="reverse-date">Tanggal Pembalikan</Label>
                            <Input
                                id="reverse-date"
                                type="date"
                                value={reverseForm.data.date}
                                onChange={(e) => reverseForm.setData('date', e.target.value)}
                                required
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="reverse-reason">Alasan Pembalikan</Label>
                            <Input
                                id="reverse-reason"
                                placeholder="Contoh: Koreksi salah catat nominal / pembatalan nota"
                                value={reverseForm.data.reason}
                                onChange={(e) => reverseForm.setData('reason', e.target.value)}
                                required
                            />
                            {reverseForm.errors.reason && (
                                <p className="text-xs text-destructive">{reverseForm.errors.reason}</p>
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsReverseOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={reverseForm.processing}>
                                Konfirmasi Balikkan Jurnal
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Draft Modal */}
            <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus Draf Jurnal</DialogTitle>
                        <DialogDescription>
                            Apakah Anda yakin ingin menghapus draf jurnal ini?
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsDeleteOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button type="button" variant="destructive" onClick={handleDeleteDraft}>
                            Hapus Draf
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

JournalShow.layout = {
    breadcrumbs: [
        {
            title: 'Keuangan',
            href: '/finance/accounts',
        },
        {
            title: 'Jurnal Manual',
            href: '/finance/journals',
        },
        {
            title: 'Detail Jurnal',
            href: '#',
        },
    ],
};
