import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import VerificationController from '@/actions/App/Http/Controllers/Fees/VerificationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
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
import { useTranslation } from '@/hooks/use-translation';

type BillRow = {
    bill_id: number;
    student_name: string;
    title: string;
    period_key: string;
    due_date: string;
    net_amount: string;
    paid_amount: string;
    remaining: string;
};

type PendingPayment = {
    id: number;
    amount: string;
    payment_date: string;
    origin_bank: string | null;
    submitted_by: string;
    notes: string | null;
    deposit_account_id: number;
    has_proof: boolean;
    students: { id: number; full_name: string; class_name: string | null }[];
    allocations: (BillRow & { allocation_amount: string })[];
    duplicates: {
        id: number;
        receipt_number: string | null;
        status_label: string;
        amount: string;
        payment_date: string;
    }[];
    candidate_bills: BillRow[];
};

type Props = {
    payments: PendingPayment[];
    cashAccounts: { id: number; code: string; name: string }[];
    defaultAccountId: number | null;
    canManage: boolean;
};

const selectClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

export default function Verifications({
    payments,
    cashAccounts,
    defaultAccountId,
    canManage,
}: Props) {
    const { t, locale } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('fees.common.fees'), href: '/fees/bills' },
            { title: t('fees.verifications.title') },
        ],
    });

    const formatRupiah = (value: string | number) =>
        new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(Number(value));

    const [verifying, setVerifying] = useState<PendingPayment | null>(null);
    const [rejecting, setRejecting] = useState<PendingPayment | null>(null);
    const [reallocate, setReallocate] = useState(false);
    const [amounts, setAmounts] = useState<Record<number, string>>({});
    const [reason, setReason] = useState('');

    const openVerify = (payment: PendingPayment) => {
        setReallocate(false);
        setAmounts({});
        setVerifying(payment);
    };

    const openReject = (payment: PendingPayment) => {
        setReason('');
        setRejecting(payment);
    };

    const entries = Object.entries(amounts).filter(
        ([, amount]) => Number(amount) > 0,
    );
    const total = entries.reduce((sum, [, amount]) => sum + Number(amount), 0);
    const balanced =
        !reallocate ||
        (verifying !== null &&
            entries.length > 0 &&
            Math.round(total * 100) ===
                Math.round(Number(verifying.amount) * 100));

    const startReallocation = (payment: PendingPayment) => {
        setAmounts(
            Object.fromEntries(
                payment.allocations.map((row) => [
                    row.bill_id,
                    String(Number(row.allocation_amount)),
                ]),
            ),
        );
        setReallocate(true);
    };

    return (
        <>
            <Head title={t('fees.verifications.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('fees.verifications.title')}
                    description={t('fees.verifications.description')}
                />

                {payments.length === 0 && (
                    <p className="text-muted-foreground rounded-lg border px-4 py-8 text-center text-sm">
                        {t('fees.verifications.table.empty')}
                    </p>
                )}

                {payments.map((payment) => (
                    <div
                        key={payment.id}
                        className="space-y-4 rounded-lg border p-4"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="space-y-1">
                                <p className="text-lg font-semibold tabular-nums">
                                    {formatRupiah(payment.amount)}
                                </p>
                                <p className="text-sm">
                                    {payment.students
                                        .map(
                                            (student) =>
                                                student.full_name +
                                                (student.class_name
                                                    ? ` (${student.class_name})`
                                                    : ''),
                                        )
                                        .join(', ')}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {t('fees.parent.transfer_date')}{' '}
                                    {payment.payment_date}
                                    {payment.origin_bank &&
                                        ` - ${payment.origin_bank}`}{' '}
                                    - {payment.submitted_by}
                                </p>
                                {payment.notes && (
                                    <p className="text-muted-foreground text-sm">
                                        {t('fees.common.notes')}:{' '}
                                        {payment.notes}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                {payment.has_proof && (
                                    <a
                                        href={VerificationController.proof.url({
                                            payment: payment.id,
                                        })}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="flex items-center gap-2"
                                    >
                                        <img
                                            src={VerificationController.proof.url(
                                                { payment: payment.id },
                                            )}
                                            alt="Bukti transfer"
                                            className="h-12 w-12 rounded border object-cover"
                                            onError={(event) => {
                                                event.currentTarget.style.display =
                                                    'none';
                                            }}
                                        />
                                        <span className="text-sm underline">
                                            {t(
                                                'fees.overpayments.table.view_proof',
                                            )}
                                        </span>
                                    </a>
                                )}
                                {canManage && (
                                    <>
                                        <Button
                                            size="sm"
                                            onClick={() => openVerify(payment)}
                                        >
                                            {t(
                                                'fees.verifications.table.verify',
                                            )}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => openReject(payment)}
                                        >
                                            {t(
                                                'fees.verifications.table.reject',
                                            )}
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>

                        {payment.duplicates.length > 0 && (
                            <Alert variant="destructive">
                                <AlertDescription>
                                    {payment.duplicates.map((duplicate) => (
                                        <p key={duplicate.id}>
                                            {t(
                                                'fees.verifications.duplicate_proof',
                                                {
                                                    defaultValue:
                                                        'Bukti serupa dengan pembayaran #',
                                                },
                                            )}
                                            {duplicate.id} (
                                            {duplicate.receipt_number ?? '—'},{' '}
                                            {duplicate.status_label},{' '}
                                            {formatRupiah(duplicate.amount)},{' '}
                                            {duplicate.payment_date}).
                                        </p>
                                    ))}
                                </AlertDescription>
                            </Alert>
                        )}

                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            {t('fees.common.student')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('fees.bills.table.bill')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('fees.common.due_date')}
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            {t('fees.bills.table.remaining')}
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            {t('fees.cashier.allocated')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payment.allocations.map((row) => (
                                        <tr
                                            key={row.bill_id}
                                            className="border-t"
                                        >
                                            <td className="px-3 py-2">
                                                {row.student_name}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.title} ({row.period_key})
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.due_date}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {formatRupiah(row.remaining)}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {formatRupiah(
                                                    row.allocation_amount,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ))}
            </div>

            <Dialog
                open={verifying !== null}
                onOpenChange={(open) => !open && setVerifying(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    {verifying && (
                        <>
                            <DialogTitle>
                                {t('fees.verifications.verify_dialog.title')}
                            </DialogTitle>
                            <DialogDescription>
                                {formatRupiah(verifying.amount)} (
                                {verifying.submitted_by}).{' '}
                                {t('fees.cashier.journal_created')}
                            </DialogDescription>
                            <Form
                                key={verifying.id}
                                {...VerificationController.verify.form({
                                    payment: verifying.id,
                                })}
                                onSuccess={() => setVerifying(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="deposit_account_id">
                                                {t(
                                                    'fees.verifications.verify_dialog.receiving_account',
                                                )}
                                            </Label>
                                            <select
                                                id="deposit_account_id"
                                                name="deposit_account_id"
                                                defaultValue={
                                                    verifying.deposit_account_id ??
                                                    defaultAccountId ??
                                                    ''
                                                }
                                                className={selectClass}
                                            >
                                                {cashAccounts.map((account) => (
                                                    <option
                                                        key={account.id}
                                                        value={account.id}
                                                    >
                                                        {account.code} -{' '}
                                                        {account.name}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={
                                                    errors.deposit_account_id
                                                }
                                            />
                                        </div>

                                        {!reallocate ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    startReallocation(verifying)
                                                }
                                            >
                                                {t('fees.common.edit')}
                                            </Button>
                                        ) : (
                                            <div className="space-y-3">
                                                {verifying.candidate_bills.map(
                                                    (bill) => (
                                                        <div
                                                            key={bill.bill_id}
                                                            className="flex items-center justify-between gap-3 text-sm"
                                                        >
                                                            <div>
                                                                <p>
                                                                    {
                                                                        bill.student_name
                                                                    }{' '}
                                                                    -{' '}
                                                                    {bill.title}
                                                                </p>
                                                                <p className="text-muted-foreground">
                                                                    {t(
                                                                        'fees.bills.table.remaining',
                                                                    )}{' '}
                                                                    {formatRupiah(
                                                                        bill.remaining,
                                                                    )}
                                                                </p>
                                                            </div>
                                                            <Input
                                                                aria-label={`Alokasi ${bill.title} ${bill.student_name}`}
                                                                type="number"
                                                                min="0"
                                                                step="0.01"
                                                                className="w-40"
                                                                value={
                                                                    amounts[
                                                                        bill
                                                                            .bill_id
                                                                    ] ?? ''
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setAmounts({
                                                                        ...amounts,
                                                                        [bill.bill_id]:
                                                                            event
                                                                                .currentTarget
                                                                                .value,
                                                                    })
                                                                }
                                                            />
                                                        </div>
                                                    ),
                                                )}

                                                {entries.map(
                                                    (
                                                        [billId, amount],
                                                        index,
                                                    ) => (
                                                        <span key={billId}>
                                                            <input
                                                                type="hidden"
                                                                name={`allocations[${index}][bill_id]`}
                                                                value={billId}
                                                            />
                                                            <input
                                                                type="hidden"
                                                                name={`allocations[${index}][amount]`}
                                                                value={amount}
                                                            />
                                                        </span>
                                                    ),
                                                )}

                                                <p
                                                    className={
                                                        balanced
                                                            ? 'text-sm'
                                                            : 'text-destructive text-sm'
                                                    }
                                                >
                                                    {t(
                                                        'fees.common.allocated_of_received',
                                                        {
                                                            allocated:
                                                                formatRupiah(
                                                                    total,
                                                                ),
                                                            received:
                                                                formatRupiah(
                                                                    verifying.amount,
                                                                ),
                                                        },
                                                    )}
                                                </p>
                                                <InputError
                                                    message={errors.allocations}
                                                />
                                            </div>
                                        )}

                                        <DialogFooter className="gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setVerifying(null)
                                                }
                                            >
                                                {t('fees.common.cancel')}
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing || !balanced
                                                }
                                            >
                                                {processing && <Spinner />}
                                                {t(
                                                    'fees.verifications.table.verify',
                                                )}
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={rejecting !== null}
                onOpenChange={(open) => !open && setRejecting(null)}
            >
                <DialogContent>
                    {rejecting && (
                        <>
                            <DialogTitle>
                                {t('fees.verifications.reject_dialog.title')}
                            </DialogTitle>
                            <DialogDescription>
                                {formatRupiah(rejecting.amount)} (
                                {rejecting.submitted_by})
                            </DialogDescription>
                            <Form
                                key={rejecting.id}
                                {...VerificationController.reject.form({
                                    payment: rejecting.id,
                                })}
                                onSuccess={() => setRejecting(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="reason">
                                                {t(
                                                    'fees.verifications.reject_dialog.reason',
                                                )}
                                            </Label>
                                            <Input
                                                id="reason"
                                                name="reason"
                                                required
                                                placeholder={t(
                                                    'fees.verifications.reject_dialog.reason_placeholder',
                                                )}
                                                value={reason}
                                                onChange={(event) =>
                                                    setReason(
                                                        event.currentTarget
                                                            .value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.reason}
                                            />
                                        </div>
                                        <DialogFooter className="gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setRejecting(null)
                                                }
                                            >
                                                {t('fees.common.cancel')}
                                            </Button>
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                disabled={
                                                    processing ||
                                                    reason.trim() === ''
                                                }
                                            >
                                                {processing && <Spinner />}
                                                {t(
                                                    'fees.verifications.reject_dialog.confirm',
                                                )}
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
