import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import ReceiptController from '@/actions/App/Http/Controllers/Fees/ReceiptController';
import FeeController from '@/actions/App/Http/Controllers/Parent/FeeController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type BillRow = {
    id: number;
    student_id: number;
    student_name: string;
    class_name: string | null;
    fee_type_name: string;
    title: string;
    period_key: string;
    due_date: string;
    net_amount: string;
    paid_amount: string;
    remaining: string;
    status: string;
    status_label: string;
    has_pending: boolean;
};

type PaymentRow = {
    id: number;
    receipt_number: string | null;
    amount: string;
    payment_date: string;
    status: string;
    status_label: string;
    origin_bank: string | null;
    reject_reason: string | null;
    can_view_receipt: boolean;
};

type Props = {
    portal: {
        summary: { unpaid: string; pending: string; paid: string };
        bills: BillRow[];
        payments: PaymentRow[];
    };
    students: { id: number; full_name: string; class_name: string | null }[];
    schoolBank: {
        bank_name: string | null;
        bank_account_number: string | null;
        bank_account_holder: string | null;
    };
    canUpload: boolean;
    transferAccountConfigured: boolean;
};

const selectClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

const formatRupiah = (value: string) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));

// Work in cents so decimal strings never drift.
const toCents = (value: string) => Math.round((parseFloat(value) || 0) * 100);

const statusVariant = (status: string) => {
    if (status === 'verified' || status === 'paid') {
        return 'default' as const;
    }

    if (status === 'rejected' || status === 'void') {
        return 'destructive' as const;
    }

    return 'secondary' as const;
};

const isOpen = (bill: BillRow) =>
    bill.status === 'unpaid' || bill.status === 'partially_paid';

export default function ParentFees({
    portal,
    students,
    schoolBank,
    canUpload,
    transferAccountConfigured,
}: Props) {
    const [childFilter, setChildFilter] = useState('');
    const [selected, setSelected] = useState<Record<number, string>>({});
    const [open, setOpen] = useState(false);

    const form = useForm<{
        payment_date: string;
        origin_bank: string;
        proof: File | null;
        notes: string;
    }>({
        payment_date: new Date().toLocaleDateString('en-CA'),
        origin_bank: '',
        proof: null,
        notes: '',
    });

    const bills = useMemo(
        () =>
            portal.bills.filter(
                (bill) =>
                    childFilter === '' ||
                    String(bill.student_id) === childFilter,
            ),
        [portal.bills, childFilter],
    );

    const selectedBills = portal.bills.filter((bill) => bill.id in selected);
    const totalCents = selectedBills.reduce(
        (sum, bill) => sum + toCents(selected[bill.id]),
        0,
    );
    const errors: Record<string, string | undefined> = form.errors;
    const uploadEnabled = canUpload && transferAccountConfigured;

    const toggle = (bill: BillRow, checked: boolean) =>
        setSelected((current) => {
            const next = { ...current };

            if (checked) {
                next[bill.id] = bill.remaining;
            } else {
                delete next[bill.id];
            }

            return next;
        });

    const submit = () => {
        form.transform((data) => ({
            ...data,
            amount: (totalCents / 100).toFixed(2),
            allocations: selectedBills.map((bill) => ({
                bill_id: bill.id,
                amount: selected[bill.id],
            })),
        }));
        form.post(FeeController.store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                setSelected({});
                form.reset('origin_bank', 'proof', 'notes');
            },
        });
    };

    return (
        <>
            <Head title="Biaya Sekolah" />

            <div className="max-w-5xl space-y-6 p-4">
                <Heading
                    title="Biaya Sekolah"
                    description={
                        canUpload
                            ? 'Tagihan, status pembayaran, dan unggah bukti transfer.'
                            : 'Tagihan dan status pembayaran.'
                    }
                />

                <div className="rounded-lg border p-4 text-sm">
                    <p className="font-medium">Rekening Sekolah</p>
                    {schoolBank.bank_account_number ? (
                        <p className="text-muted-foreground mt-1">
                            {schoolBank.bank_name} -{' '}
                            <span className="text-foreground font-semibold tabular-nums">
                                {schoolBank.bank_account_number}
                            </span>{' '}
                            a.n. {schoolBank.bank_account_holder}
                        </p>
                    ) : (
                        <p className="text-muted-foreground mt-1">
                            Rekening sekolah belum diatur.
                        </p>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    {[
                        ['Belum Dibayar', portal.summary.unpaid],
                        ['Menunggu Verifikasi', portal.summary.pending],
                        ['Lunas', portal.summary.paid],
                    ].map(([label, value]) => (
                        <div key={label} className="rounded-lg border p-4">
                            <p className="text-muted-foreground text-sm">
                                {label}
                            </p>
                            <p className="text-xl font-semibold tabular-nums">
                                {formatRupiah(value)}
                            </p>
                        </div>
                    ))}
                </div>

                {students.length === 0 && (
                    <p className="text-muted-foreground text-sm">
                        Belum ada anak yang terhubung dengan akun ini.
                    </p>
                )}

                {students.length > 0 && (
                    <div className="space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h2 className="text-lg font-medium">Tagihan</h2>
                            <div className="flex items-center gap-2">
                                {students.length > 1 && (
                                    <select
                                        aria-label="Filter anak"
                                        value={childFilter}
                                        onChange={(event) =>
                                            setChildFilter(event.target.value)
                                        }
                                        className={selectClass}
                                    >
                                        <option value="">Semua anak</option>
                                        {students.map((student) => (
                                            <option
                                                key={student.id}
                                                value={student.id}
                                            >
                                                {student.full_name}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                {canUpload && (
                                    <Button
                                        type="button"
                                        disabled={
                                            !uploadEnabled ||
                                            selectedBills.length === 0
                                        }
                                        onClick={() => setOpen(true)}
                                    >
                                        Unggah Bukti Transfer
                                    </Button>
                                )}
                            </div>
                        </div>

                        {canUpload && !transferAccountConfigured && (
                            <p className="text-muted-foreground text-sm">
                                Unggah bukti transfer belum tersedia: rekening
                                tujuan transfer belum diatur oleh sekolah.
                            </p>
                        )}

                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        {canUpload && (
                                            <th className="w-10 px-4 py-3" />
                                        )}
                                        <th className="px-4 py-3">Siswa</th>
                                        <th className="px-4 py-3">Tagihan</th>
                                        <th className="px-4 py-3">
                                            Jatuh Tempo
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Tagihan
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Dibayar
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Sisa
                                        </th>
                                        <th className="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {bills.map((bill) => (
                                        <tr key={bill.id} className="border-t">
                                            {canUpload && (
                                                <td className="px-4 py-3">
                                                    {isOpen(bill) && (
                                                        <Checkbox
                                                            aria-label={`Pilih ${bill.title} ${bill.student_name}`}
                                                            disabled={
                                                                !uploadEnabled
                                                            }
                                                            checked={
                                                                bill.id in
                                                                selected
                                                            }
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                toggle(
                                                                    bill,
                                                                    checked ===
                                                                        true,
                                                                )
                                                            }
                                                        />
                                                    )}
                                                </td>
                                            )}
                                            <td className="px-4 py-3">
                                                {bill.student_name}
                                                <div className="text-muted-foreground text-xs">
                                                    {bill.class_name ?? '-'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {bill.title}
                                                <div className="text-muted-foreground text-xs">
                                                    {bill.fee_type_name} ·{' '}
                                                    {bill.period_key}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {bill.due_date}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {formatRupiah(bill.net_amount)}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {formatRupiah(bill.paid_amount)}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {formatRupiah(bill.remaining)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-1">
                                                    <Badge
                                                        variant={statusVariant(
                                                            bill.status,
                                                        )}
                                                    >
                                                        {bill.status_label}
                                                    </Badge>
                                                    {bill.has_pending && (
                                                        <Badge variant="outline">
                                                            Menunggu verifikasi
                                                        </Badge>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}

                                    {bills.length === 0 && (
                                        <tr className="border-t">
                                            <td
                                                colSpan={canUpload ? 8 : 7}
                                                className="text-muted-foreground px-4 py-8 text-center"
                                            >
                                                Belum ada tagihan.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                <div className="space-y-3">
                    <h2 className="text-lg font-medium">Riwayat Pembayaran</h2>
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3">Tanggal</th>
                                    <th className="px-4 py-3">Bank Asal</th>
                                    <th className="px-4 py-3 text-right">
                                        Jumlah
                                    </th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {portal.payments.map((payment) => (
                                    <tr key={payment.id} className="border-t">
                                        <td className="px-4 py-3">
                                            {payment.payment_date}
                                        </td>
                                        <td className="px-4 py-3">
                                            {payment.origin_bank ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {formatRupiah(payment.amount)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={statusVariant(
                                                    payment.status,
                                                )}
                                            >
                                                {payment.status_label}
                                            </Badge>
                                            {payment.status === 'rejected' &&
                                                payment.reject_reason && (
                                                    <div className="text-muted-foreground mt-1 text-xs">
                                                        {payment.reject_reason}
                                                    </div>
                                                )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {payment.can_view_receipt && (
                                                <a
                                                    href={ReceiptController.show.url(
                                                        { payment: payment.id },
                                                    )}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="font-medium underline"
                                                >
                                                    Cetak Kuitansi
                                                </a>
                                            )}
                                        </td>
                                    </tr>
                                ))}

                                {portal.payments.length === 0 && (
                                    <tr className="border-t">
                                        <td
                                            colSpan={5}
                                            className="text-muted-foreground px-4 py-8 text-center"
                                        >
                                            Belum ada pembayaran.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {canUpload && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent className="max-h-[90dvh] grid-rows-[auto_auto_minmax(0,1fr)_auto]">
                        <DialogTitle>Unggah Bukti Transfer</DialogTitle>
                        <DialogDescription>
                            Pembayaran akan diverifikasi oleh sekolah sebelum
                            tagihan dinyatakan lunas.
                        </DialogDescription>

                        <div className="space-y-4 overflow-y-auto pr-1">
                            <div className="space-y-2">
                                {selectedBills.map((bill) => (
                                    <div
                                        key={bill.id}
                                        className="grid grid-cols-[1fr_9rem] items-center gap-3 text-sm"
                                    >
                                        <span>
                                            {bill.title} - {bill.student_name}
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · sisa{' '}
                                                {formatRupiah(bill.remaining)}
                                            </span>
                                        </span>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={bill.remaining}
                                            step="0.01"
                                            aria-label={`Jumlah ${bill.title} ${bill.student_name}`}
                                            value={selected[bill.id]}
                                            onChange={(event) =>
                                                setSelected((current) => ({
                                                    ...current,
                                                    [bill.id]:
                                                        event.target.value,
                                                }))
                                            }
                                        />
                                    </div>
                                ))}
                                <InputError
                                    message={
                                        errors.allocations ?? errors.amount
                                    }
                                />
                                <div className="flex justify-between rounded-lg border p-3 text-sm">
                                    <span>Total transfer</span>
                                    <span className="font-semibold tabular-nums">
                                        {formatRupiah(String(totalCents / 100))}
                                    </span>
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="payment_date">
                                    Tanggal Transfer
                                </Label>
                                <Input
                                    id="payment_date"
                                    type="date"
                                    value={form.data.payment_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'payment_date',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.payment_date}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="origin_bank">Bank Asal</Label>
                                <Input
                                    id="origin_bank"
                                    maxLength={100}
                                    value={form.data.origin_bank}
                                    onChange={(event) =>
                                        form.setData(
                                            'origin_bank',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.origin_bank} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="proof">
                                    Bukti Transfer (JPG, PNG, PDF, maks. 5 MB)
                                </Label>
                                <Input
                                    id="proof"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    onChange={(event) =>
                                        form.setData(
                                            'proof',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={form.errors.proof} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Catatan</Label>
                                <Input
                                    id="notes"
                                    maxLength={255}
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.notes} />
                            </div>

                            <InputError message={errors.transfer} />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="button"
                                disabled={
                                    form.processing ||
                                    totalCents <= 0 ||
                                    form.data.proof === null
                                }
                                onClick={submit}
                            >
                                {form.processing && <Spinner />}
                                Kirim
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}
