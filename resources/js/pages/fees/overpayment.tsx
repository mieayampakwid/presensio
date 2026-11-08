import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import OverpaymentController from '@/actions/App/Http/Controllers/Fees/OverpaymentController';
import VerificationController from '@/actions/App/Http/Controllers/Fees/VerificationController';
import ManualJournalController from '@/actions/App/Http/Controllers/Finance/ManualJournalController';
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
import { useTranslation } from '@/hooks/use-translation';
import type { CashAccount, OverpaymentRow } from './overpayments';

type Entry = { id: number; number: string; entry_date: string };
type Detail = OverpaymentRow & {
    bank_mutation_date: string;
    reason_label: string;
    note: string | null;
    related_payment_id: number | null;
    liability_account: CashAccount & { is_active: boolean };
    deposit_account: CashAccount;
    refund_account: CashAccount | null;
    receipt_entry: Entry;
    refund_entry: Entry | null;
    cancellation_entry: Entry | null;
    refund_date: string | null;
    bank_reference: string | null;
    requested_cancel_date: string | null;
    cancel_reason: string | null;
    has_refund_proof: boolean;
    recorded_by: string;
    refunded_by: string | null;
    cancelled_by: string | null;
    duplicate_overpayment_ids: number[];
    duplicate_payment_ids: number[];
};
const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';
export default function Overpayment({
    overpayment: record,
    cashAccounts,
    canManage,
    today,
}: {
    overpayment: Detail;
    cashAccounts: CashAccount[];
    canManage: boolean;
    today: string;
}) {
    const { t, locale } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('fees.common.fees'), href: '/fees/bills' },
            { title: t('fees.overpayments.title'), href: '/fees/overpayments' },
            { title: `#${record.id}` },
        ],
    });

    const rupiah = (amount: string | number | null) =>
        new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 2,
        }).format(Number(amount ?? 0));

    const [operation, setOperation] = useState<'refund' | 'cancel' | null>(
        null,
    );
    const refund = useForm({
        operation: '',
        refund_date: today,
        refund_account_id: String(record.deposit_account.id),
        bank_fee: '0',
        bank_reference: '',
        proof: null as File | null,
    });
    const cancel = useForm({
        operation: '',
        date: today,
        reason: '',
    });
    const heldCents = Math.round(Number(record.amount) * 100);
    const feeCents = Math.round(Number(refund.data.bank_fee || '0') * 100);
    const netCents = heldCents - feeCents;
    const validFee = Number.isFinite(feeCents) && feeCents >= 0 && netCents > 0;
    const submitRefund = (e: FormEvent) => {
        e.preventDefault();
        refund.post(OverpaymentController.refund.url(record.id), {
            onSuccess: () => {
                setOperation(null);
                refund.reset();
            },
        });
    };
    const submitCancel = (e: FormEvent) => {
        e.preventDefault();
        cancel.post(OverpaymentController.cancel.url(record.id), {
            onSuccess: () => {
                setOperation(null);
                cancel.reset();
            },
        });
    };
    return (
        <>
            <Head title={`${t('fees.overpayments.title')} #${record.id}`} />
            <div className="mx-auto max-w-4xl space-y-6 p-4">
                <Link
                    className="text-sm underline"
                    href={OverpaymentController.index()}
                >
                    {t('fees.common.back')}
                </Link>
                <Heading
                    title={`${t('fees.overpayments.title')} #${record.id}`}
                    description={`${record.guardian_name}${record.student_name ? ` / ${record.student_name}` : ''}`}
                />
                <Badge variant="secondary">{record.status_label}</Badge>
                {!record.liability_account.is_active && (
                    <p
                        role="alert"
                        className="text-destructive rounded-lg border p-4"
                    >
                        {t('fees.overpayments.liability_inactive_warning', {
                            code: record.liability_account.code,
                            defaultValue: `Akun Titipan Wali asli tidak aktif. Minta admin mengaktifkan kembali akun ${record.liability_account.code} sebelum memproses.`,
                        })}
                    </p>
                )}
                {(record.duplicate_overpayment_ids.length > 0 ||
                    record.duplicate_payment_ids.length > 0) && (
                    <div
                        role="status"
                        className="space-y-2 rounded-lg border p-4 text-sm"
                    >
                        <p>
                            {t('fees.overpayments.duplicate_warning', {
                                defaultValue:
                                    'Bukti ini juga digunakan pada catatan lain. Periksa apakah ini bagian transfer gabungan atau penerimaan ganda.',
                            })}
                        </p>
                        <div className="flex flex-wrap gap-3">
                            {record.duplicate_overpayment_ids.map((id) => (
                                <Link
                                    key={id}
                                    className="underline"
                                    href={OverpaymentController.show(id)}
                                >
                                    {'Kelebihan #'}
                                    {id}
                                </Link>
                            ))}
                            {record.duplicate_payment_ids.map((id) => (
                                <a
                                    key={id}
                                    className="underline"
                                    href={VerificationController.proof.url(id)}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    {'Bukti pembayaran #'}
                                    {id}
                                </a>
                            ))}
                        </div>
                    </div>
                )}
                <section className="rounded-lg border p-5">
                    <h2 className="mb-4 font-semibold">
                        {t('fees.common.receipt')}
                    </h2>
                    <dl className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        {Object.entries({
                            'Nilai titipan': rupiah(record.amount),
                            'Tanggal mutasi bank': record.bank_mutation_date,
                            'Tanggal posting': record.posting_date,
                            'Akun penerimaan': `${record.deposit_account.code} — ${record.deposit_account.name}`,
                            'Akun Titipan Wali': `${record.liability_account.code} — ${record.liability_account.name}`,
                            Alasan: record.reason_label,
                            'Dicatat oleh': record.recorded_by,
                            'Pembayaran terkait': record.related_payment_id
                                ? `#${record.related_payment_id}`
                                : '—',
                        }).map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="mt-1 font-medium">{value}</dd>
                            </div>
                        ))}
                    </dl>
                    {record.note && (
                        <p className="mt-4 text-sm whitespace-pre-wrap">
                            {record.note}
                        </p>
                    )}
                    <div className="mt-5 flex flex-wrap gap-4 text-sm">
                        <a
                            className="underline"
                            href={OverpaymentController.proof.url({
                                overpayment: record.id,
                                kind: 'receipt',
                            })}
                            target="_blank"
                            rel="noreferrer"
                        >
                            {t('fees.overpayments.table.view_proof')}
                        </a>
                        <Link
                            className="underline"
                            href={ManualJournalController.show(
                                record.receipt_entry.id,
                            )}
                        >
                            {'Jurnal '}
                            {record.receipt_entry.number}
                        </Link>
                    </div>
                </section>
                {record.refund_entry && (
                    <section className="space-y-4 rounded-lg border p-5">
                        <h2 className="font-semibold">
                            {t('fees.overpayments.record_refund')}
                        </h2>
                        <dl className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                            {Object.entries({
                                'Tanggal refund': record.refund_date,
                                'Nilai titipan': rupiah(record.amount),
                                'Biaya bank ditanggung wali': rupiah(
                                    record.bank_fee,
                                ),
                                'Diterima wali': rupiah(record.net_refund),
                                'Total keluar dari bank': rupiah(
                                    record.bank_total,
                                ),
                                'Referensi bank': record.bank_reference,
                                'Dicatat oleh': record.refunded_by,
                                'Akun pembayaran': record.refund_account?.name,
                            }).map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="mt-1 font-medium">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        <div className="flex gap-4 text-sm">
                            <Link
                                className="underline"
                                href={ManualJournalController.show(
                                    record.refund_entry.id,
                                )}
                            >
                                {'Jurnal '}
                                {record.refund_entry.number}
                            </Link>
                            {record.has_refund_proof && (
                                <a
                                    className="underline"
                                    href={OverpaymentController.proof.url({
                                        overpayment: record.id,
                                        kind: 'refund',
                                    })}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    {t('fees.overpayments.table.view_proof')}
                                </a>
                            )}
                        </div>
                    </section>
                )}
                {record.cancellation_entry && (
                    <section className="space-y-3 rounded-lg border p-5">
                        <h2 className="font-semibold">
                            {t('fees.payments.void_dialog.title')}
                        </h2>
                        <p className="text-sm">
                            {'Tanggal diminta: '}
                            {record.requested_cancel_date}
                            {'. Tanggal efektif: '}
                            {record.cancellation_entry.entry_date.slice(0, 10)}
                            {'. Oleh '}
                            {record.cancelled_by}
                            {'.'}
                        </p>
                        <p className="text-sm whitespace-pre-wrap">
                            {record.cancel_reason}
                        </p>
                        <Link
                            className="text-sm underline"
                            href={ManualJournalController.show(
                                record.cancellation_entry.id,
                            )}
                        >
                            {'Jurnal '}
                            {record.cancellation_entry.number}
                        </Link>
                    </section>
                )}
                {canManage && record.status === 'held' && (
                    <div className="flex flex-wrap gap-3">
                        <Button onClick={() => setOperation('refund')}>
                            {t('fees.overpayments.record_refund')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setOperation('cancel')}
                        >
                            {t('fees.payments.table.void')}
                        </Button>
                    </div>
                )}
            </div>
            <Dialog
                open={operation !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setOperation(null);
                    }
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {operation === 'refund'
                                ? 'Konfirmasi pengembalian'
                                : 'Batalkan penerimaan'}
                        </DialogTitle>
                        <DialogDescription>
                            {operation === 'refund'
                                ? 'Catat setelah transfer dilakukan. Seluruh titipan diselesaikan; biaya bank ditanggung wali.'
                                : 'Gunakan hanya untuk penerimaan yang salah dicatat. Jurnal asli akan dibalik.'}
                        </DialogDescription>
                    </DialogHeader>
                    {operation === 'refund' ? (
                        <form onSubmit={submitRefund} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="refund_date">
                                    {t('fees.common.date')}
                                </Label>
                                <Input
                                    id="refund_date"
                                    required
                                    type="date"
                                    min={record.posting_date}
                                    value={refund.data.refund_date}
                                    onChange={(e) =>
                                        refund.setData(
                                            'refund_date',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={refund.errors.refund_date}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="refund_account_id">
                                    {t(
                                        'fees.verifications.verify_dialog.receiving_account',
                                    )}
                                </Label>
                                <select
                                    id="refund_account_id"
                                    required
                                    className={selectClass}
                                    value={refund.data.refund_account_id}
                                    onChange={(e) =>
                                        refund.setData(
                                            'refund_account_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('fees.cashier.select_cash_account')}
                                    </option>
                                    {cashAccounts.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.code} — {a.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={refund.errors.refund_account_id}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="bank_fee">
                                    {t('fees.overpayments.bank_fee', {
                                        defaultValue:
                                            'Biaya bank ditanggung wali (Rp)',
                                    })}
                                </Label>
                                <Input
                                    id="bank_fee"
                                    required
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    max={(heldCents - 1) / 100}
                                    value={refund.data.bank_fee}
                                    onChange={(e) =>
                                        refund.setData(
                                            'bank_fee',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError message={refund.errors.bank_fee} />
                            </div>
                            <dl className="bg-muted space-y-2 rounded-md p-4 text-sm">
                                {Object.entries({
                                    'Nilai titipan': record.amount,
                                    'Biaya bank ditanggung wali':
                                        feeCents / 100,
                                    'Diterima wali': netCents / 100,
                                    'Total keluar dari bank': record.amount,
                                }).map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="flex justify-between gap-3"
                                    >
                                        <dt>{label}</dt>
                                        <dd className="font-medium tabular-nums">
                                            {rupiah(value)}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <div className="grid gap-2">
                                <Label htmlFor="bank_reference">
                                    {t('fees.overpayments.bank_reference', {
                                        defaultValue: 'Referensi bank',
                                    })}
                                </Label>
                                <Input
                                    id="bank_reference"
                                    required
                                    maxLength={255}
                                    value={refund.data.bank_reference}
                                    onChange={(e) =>
                                        refund.setData(
                                            'bank_reference',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={refund.errors.bank_reference}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="refund_proof">
                                    {t('fees.overpayments.refund_proof', {
                                        defaultValue:
                                            'Bukti refund (opsional, maks. 5 MB)',
                                    })}
                                </Label>
                                <Input
                                    id="refund_proof"
                                    type="file"
                                    accept="image/jpeg,image/png,application/pdf"
                                    onChange={(e) =>
                                        refund.setData(
                                            'proof',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={refund.errors.proof} />
                                <InputError message={refund.errors.operation} />
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setOperation(null)}
                                >
                                    {t('common.cancel')}
                                </Button>
                                <Button
                                    disabled={refund.processing || !validFee}
                                >
                                    {refund.processing
                                        ? 'Menyimpan…'
                                        : t('fees.common.save')}
                                </Button>
                            </DialogFooter>
                        </form>
                    ) : (
                        <form onSubmit={submitCancel} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="cancel_date">
                                    {t('fees.common.date')}
                                </Label>
                                <Input
                                    id="cancel_date"
                                    type="date"
                                    required
                                    min={record.posting_date}
                                    value={cancel.data.date}
                                    onChange={(e) =>
                                        cancel.setData('date', e.target.value)
                                    }
                                />
                                <InputError message={cancel.errors.date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="cancel_reason">
                                    {t('fees.payments.void_dialog.reason')}
                                </Label>
                                <Input
                                    id="cancel_reason"
                                    required
                                    value={cancel.data.reason}
                                    onChange={(e) =>
                                        cancel.setData('reason', e.target.value)
                                    }
                                />
                                <InputError message={cancel.errors.reason} />
                                <InputError message={cancel.errors.operation} />
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setOperation(null)}
                                >
                                    {t('fees.common.back')}
                                </Button>
                                <Button
                                    variant="destructive"
                                    disabled={cancel.processing}
                                >
                                    {cancel.processing
                                        ? 'Menyimpan…'
                                        : t(
                                              'fees.payments.void_dialog.confirm',
                                          )}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
