import { Head, Link, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import OverpaymentController from '@/actions/App/Http/Controllers/Fees/OverpaymentController';
import SchoolProfileController from '@/actions/App/Http/Controllers/Settings/SchoolProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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

export type CashAccount = { id: number; code: string; name: string };
const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';
const rupiah = (amount: string | number | null) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 2,
    }).format(Number(amount ?? 0));
export type OverpaymentRow = {
    id: number;
    guardian_name: string;
    student_name: string | null;
    amount: string;
    posting_date: string;
    status: string;
    status_label: string;
    age_days: number;
    bank_fee: string | null;
    net_refund: string | null;
    bank_total: string | null;
};
type Props = {
    overpayments: {
        data: OverpaymentRow[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: {
        status?: string;
        guardian_id?: number;
        search?: string;
        from?: string;
        to?: string;
    };
    configuration: { account_id: number | null };
    guardians: {
        id: number;
        name: string;
        students: { id: number; full_name: string }[];
    }[];
    cashAccounts: CashAccount[];
    reasons: { value: string; label: string }[];
    today: string;
    canManage: boolean;
    canConfigure: boolean;
};

export default function Overpayments({
    overpayments,
    filters,
    configuration,
    guardians,
    cashAccounts,
    reasons,
    today,
    canManage,
    canConfigure,
}: Props) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        operation: '',
        guardian_id: '',
        student_id: '',
        related_payment_id: '',
        amount: '',
        bank_mutation_date: today,
        posting_date: today,
        deposit_account_id: '',
        reason: 'wrong_amount',
        note: '',
        proof: null as File | null,
    });
    const selectedGuardian = guardians.find(
        (g) => String(g.id) === form.data.guardian_id,
    );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(OverpaymentController.store.url(), {
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };
    return (
        <>
            <Head title="Kelebihan Pembayaran" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Kelebihan Pembayaran"
                        description="Titipan wali dari kelebihan transfer, beserta riwayat pengembaliannya."
                    />
                    {canManage && (
                        <Button
                            disabled={!configuration.account_id}
                            onClick={() => setOpen(true)}
                        >
                            Catat penerimaan
                        </Button>
                    )}
                </div>
                {!configuration.account_id && (
                    <div
                        role="status"
                        className="rounded-lg border p-4 text-sm"
                    >
                        Akun Titipan Wali belum diatur. Minta admin mengaturnya
                        di Profil Sekolah.{' '}
                        {canConfigure && (
                            <Link
                                className="underline"
                                href={SchoolProfileController.edit()}
                            >
                                Buka Profil Sekolah
                            </Link>
                        )}
                    </div>
                )}
                <details className="rounded-lg border p-4 text-sm">
                    <summary className="cursor-pointer font-medium">
                        Cara mencatat transfer gabungan tagihan dan kelebihan
                    </summary>
                    <p className="mt-3">
                        Untuk transfer Rp 3.000.000 dengan tagihan Rp 300.000:
                        wali mengunggah pembayaran Rp 300.000 dengan alokasi Rp
                        300.000, bukti transfer penuh, dan catatan nominal
                        bruto. Verifikasi pembayaran itu terlebih dahulu. Lalu
                        catat kelebihan Rp 2.700.000 dengan ID pembayaran
                        terkait, akun bank dan tanggal posting jurnal yang sama.
                        Cantumkan nominal bruto, bagian tagihan, dan referensi
                        bank di catatan. Bukti yang sama akan memunculkan
                        peringatan duplikat untuk diperiksa.
                    </p>
                    <p className="mt-2">
                        Refund menyelesaikan seluruh titipan; biaya bank
                        dipotong dari dana yang diterima wali. Jika seluruh
                        transfer dikembalikan sebelum pembayaran tagihan
                        diverifikasi, catat seluruhnya sebagai kelebihan dan
                        biarkan tagihan belum dibayar.
                    </p>
                </details>
                <form
                    action={OverpaymentController.index.url()}
                    className="flex flex-wrap items-end gap-3"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="search">Nama wali</Label>
                        <Input
                            id="search"
                            name="search"
                            defaultValue={filters.search ?? ''}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <select
                            id="status"
                            name="status"
                            className={selectClass}
                            defaultValue={filters.status ?? ''}
                        >
                            <option value="">Semua</option>
                            <option value="held">Dititipkan</option>
                            <option value="refunded">Dikembalikan</option>
                            <option value="cancelled">Dibatalkan</option>
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="from">Dari tanggal</Label>
                        <Input
                            id="from"
                            name="from"
                            type="date"
                            defaultValue={filters.from ?? ''}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="to">Sampai tanggal</Label>
                        <Input
                            id="to"
                            name="to"
                            type="date"
                            defaultValue={filters.to ?? ''}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="guardian_filter">Wali</Label>
                        <select
                            id="guardian_filter"
                            name="guardian_id"
                            className={selectClass}
                            defaultValue={filters.guardian_id ?? ''}
                        >
                            <option value="">Semua wali</option>
                            {guardians.map((guardian) => (
                                <option key={guardian.id} value={guardian.id}>
                                    {guardian.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button variant="outline">Tampilkan</Button>
                </form>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                {[
                                    'Tanggal',
                                    'Wali / siswa',
                                    'Titipan',
                                    'Status',
                                    'Umur (hari)',
                                    'Biaya bank',
                                    'Diterima wali',
                                    '',
                                ].map((t) => (
                                    <th key={t} className="px-4 py-3">
                                        {t}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {overpayments.data.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-4 py-3">
                                        {row.posting_date}
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.guardian_name}
                                        <div className="text-muted-foreground">
                                            {row.student_name}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {rupiah(row.amount)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="secondary">
                                            {row.status_label}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.status === 'held'
                                            ? row.age_days
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.bank_fee === null
                                            ? '—'
                                            : rupiah(row.bank_fee)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.net_refund === null
                                            ? '—'
                                            : rupiah(row.net_refund)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link
                                            className="underline"
                                            href={OverpaymentController.show(
                                                row.id,
                                            )}
                                        >
                                            Detail #{row.id}
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {overpayments.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="text-muted-foreground px-4 py-10 text-center"
                                    >
                                        Belum ada kelebihan pembayaran yang
                                        sesuai.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between">
                    <span className="text-sm">
                        Halaman {overpayments.current_page} dari{' '}
                        {overpayments.last_page}
                    </span>
                    <div className="flex gap-3">
                        {overpayments.prev_page_url && (
                            <Link href={overpayments.prev_page_url}>
                                Sebelumnya
                            </Link>
                        )}
                        {overpayments.next_page_url && (
                            <Link href={overpayments.next_page_url}>
                                Berikutnya
                            </Link>
                        )}
                    </div>
                </div>
            </div>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Catat penerimaan kelebihan</DialogTitle>
                        <DialogDescription>
                            Pastikan dana sudah diterima di bank. Penerimaan
                            tersimpan sebagai kewajiban kepada wali.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="guardian_id">Wali</Label>
                            <select
                                id="guardian_id"
                                required
                                className={selectClass}
                                value={form.data.guardian_id}
                                onChange={(e) => {
                                    form.setData('guardian_id', e.target.value);
                                    form.setData('student_id', '');
                                }}
                            >
                                <option value="">Pilih wali</option>
                                {guardians.map((g) => (
                                    <option key={g.id} value={g.id}>
                                        {g.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.guardian_id} />
                            <InputError message={form.errors.operation} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="student_id">Siswa (opsional)</Label>
                            <select
                                id="student_id"
                                className={selectClass}
                                value={form.data.student_id}
                                onChange={(e) =>
                                    form.setData('student_id', e.target.value)
                                }
                            >
                                <option value="">Tanpa siswa</option>
                                {selectedGuardian?.students.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.full_name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.student_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="amount">
                                Jumlah kelebihan (Rp)
                            </Label>
                            <Input
                                id="amount"
                                required
                                type="number"
                                min="0.01"
                                step="0.01"
                                value={form.data.amount}
                                onChange={(e) =>
                                    form.setData('amount', e.target.value)
                                }
                            />
                            <InputError message={form.errors.amount} />
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {(
                                ['bank_mutation_date', 'posting_date'] as const
                            ).map((key) => (
                                <div key={key} className="grid gap-2">
                                    <Label htmlFor={key}>
                                        {key === 'posting_date'
                                            ? 'Tanggal posting'
                                            : 'Tanggal mutasi bank'}
                                    </Label>
                                    <Input
                                        id={key}
                                        required
                                        type="date"
                                        value={form.data[key]}
                                        onChange={(e) =>
                                            form.setData(key, e.target.value)
                                        }
                                    />
                                    <InputError message={form.errors[key]} />
                                </div>
                            ))}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="deposit_account_id">
                                Akun penerimaan
                            </Label>
                            <select
                                id="deposit_account_id"
                                required
                                className={selectClass}
                                value={form.data.deposit_account_id}
                                onChange={(e) =>
                                    form.setData(
                                        'deposit_account_id',
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">Pilih kas/bank</option>
                                {cashAccounts.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.code} — {a.name}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={form.errors.deposit_account_id}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reason">Alasan</Label>
                            <select
                                id="reason"
                                className={selectClass}
                                value={form.data.reason}
                                onChange={(e) =>
                                    form.setData('reason', e.target.value)
                                }
                            >
                                {reasons.map((r) => (
                                    <option key={r.value} value={r.value}>
                                        {r.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.reason} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="related_payment_id">
                                ID pembayaran terkait (opsional)
                            </Label>
                            <Input
                                id="related_payment_id"
                                type="number"
                                min="1"
                                value={form.data.related_payment_id}
                                onChange={(e) =>
                                    form.setData(
                                        'related_payment_id',
                                        e.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.related_payment_id}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="note">Catatan</Label>
                            <textarea
                                id="note"
                                className="border-input min-h-20 rounded-md border p-2 text-sm"
                                value={form.data.note}
                                onChange={(e) =>
                                    form.setData('note', e.target.value)
                                }
                            />
                            <InputError message={form.errors.note} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="proof">
                                Bukti mutasi bank (JPG, PNG, PDF; maks. 5 MB)
                            </Label>
                            <Input
                                id="proof"
                                type="file"
                                required
                                accept="image/jpeg,image/png,application/pdf"
                                onChange={(e) =>
                                    form.setData(
                                        'proof',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={form.errors.proof} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button disabled={form.processing}>
                                {form.processing
                                    ? 'Menyimpan…'
                                    : 'Simpan penerimaan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
