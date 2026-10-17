import { Head, router, useForm, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import CashierController from '@/actions/App/Http/Controllers/Fees/CashierController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type StudentSummary = {
    id: number;
    full_name: string;
    student_number: string | null;
    class_name: string | null;
};

type OpenBill = {
    id: number;
    title: string;
    due_date: string;
    remaining: string;
};

type SelectedStudent = StudentSummary & { bills: OpenBill[] };

type Props = {
    term: string;
    results: StudentSummary[];
    selectedStudents: SelectedStudent[];
    siblings: StudentSummary[];
    cashAccounts: { id: number; code: string; name: string }[];
    defaultAccountId: number | null;
};

const selectClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

const rupiah = (value: number) => `Rp ${value.toLocaleString('id-ID')}`;

// Work in cents so decimal strings never drift.
const toCents = (value: string) => Math.round((parseFloat(value) || 0) * 100);

const fromCents = (cents: number) => (cents / 100).toFixed(2);

export default function Cashier({
    term,
    results,
    selectedStudents,
    siblings,
    cashAccounts,
    defaultAccountId,
}: Props) {
    const { flash } = usePage<{
        flash: { payment?: { id: number; receipt_number: string } };
    }>().props;
    const [search, setSearch] = useState(term);
    const [allocations, setAllocations] = useState<Record<number, string>>({});

    const form = useForm({
        amount_received: '',
        deposit_account_id: defaultAccountId ? String(defaultAccountId) : '',
        notes: '',
    });

    const bills = useMemo(
        () =>
            selectedStudents
                .flatMap((student) =>
                    student.bills.map((bill) => ({ ...bill, student })),
                )
                .sort(
                    (a, b) =>
                        a.due_date.localeCompare(b.due_date) || a.id - b.id,
                ),
        [selectedStudents],
    );

    // Oldest due first, never more than a bill's remaining amount.
    const autoAllocate = (received: string) => {
        let left = toCents(received);
        const next: Record<number, string> = {};

        for (const bill of bills) {
            const part = Math.min(left, toCents(bill.remaining));

            if (part > 0) {
                next[bill.id] = fromCents(part);
                left -= part;
            }
        }

        setAllocations(next);
    };

    const billsKey = bills.map((bill) => bill.id).join(',');

    useEffect(() => {
        // Re-split when the selection changes.
        autoAllocate(form.data.amount_received);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [billsKey]);

    const allocatedCents = Object.values(allocations).reduce(
        (sum, value) => sum + toCents(value),
        0,
    );
    const receivedCents = toCents(form.data.amount_received);
    const balanced = receivedCents > 0 && allocatedCents === receivedCents;

    const visit = (
        students: number[],
        q: string,
        only: string[] = ['term', 'results', 'selectedStudents', 'siblings'],
    ) =>
        router.get(
            CashierController.index.url(),
            { q: q || undefined, students },
            { only, preserveState: true, preserveScroll: true, replace: true },
        );

    const errors: Record<string, string | undefined> = form.errors;
    const selectedIds = selectedStudents.map((student) => student.id);

    const submit = () => {
        form.transform((data) => ({
            ...data,
            student_ids: selectedIds,
            allocations: Object.entries(allocations).map(
                ([bill_id, amount]) => ({ bill_id: Number(bill_id), amount }),
            ),
        }));
        form.post(CashierController.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData('amount_received', '');
                form.setData('notes', '');
                setAllocations({});
            },
        });
    };

    return (
        <>
            <Head title="Kasir" />

            <div className="max-w-3xl space-y-6 p-4">
                <Heading
                    title="Kasir"
                    description="Terima pembayaran tunai untuk satu atau beberapa bersaudara."
                />

                {flash?.payment && (
                    <div className="rounded-lg border border-green-600/40 p-4 text-sm">
                        Pembayaran tercatat. Nomor kuitansi{' '}
                        <span className="font-semibold">
                            {flash.payment.receipt_number}
                        </span>
                        .
                        {/* Task 7: "Cetak Kuitansi" link for payment {flash.payment.id}. */}
                    </div>
                )}

                <form
                    className="flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit(selectedIds, search, ['term', 'results']);
                    }}
                >
                    <Input
                        placeholder="Cari nama atau NIS (min. 2 huruf)..."
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Button type="submit" variant="outline">
                        Cari
                    </Button>
                </form>

                {results.length > 0 && (
                    <ul className="divide-y rounded-lg border">
                        {results.map((student) => (
                            <li
                                key={student.id}
                                className="flex items-center justify-between p-3 text-sm"
                            >
                                <span>
                                    {student.full_name}
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {student.student_number ?? '-'} ·{' '}
                                        {student.class_name ?? '-'}
                                    </span>
                                </span>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    disabled={selectedIds.includes(student.id)}
                                    onClick={() =>
                                        visit(
                                            [...selectedIds, student.id],
                                            search,
                                        )
                                    }
                                >
                                    Pilih
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}

                {siblings.length > 0 && (
                    <div className="space-y-2">
                        <p className="text-muted-foreground text-sm">
                            Saudara (wali sama), dapat dibayar bersamaan:
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {siblings.map((sibling) => (
                                <Button
                                    key={sibling.id}
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        visit(
                                            [...selectedIds, sibling.id],
                                            search,
                                        )
                                    }
                                >
                                    + {sibling.full_name}
                                    {sibling.class_name &&
                                        ` (${sibling.class_name})`}
                                </Button>
                            ))}
                        </div>
                    </div>
                )}

                {selectedStudents.map((student) => (
                    <div
                        key={student.id}
                        className="space-y-2 rounded-lg border p-4"
                    >
                        <div className="flex items-center justify-between">
                            <div className="text-sm font-medium">
                                {student.full_name}
                                <span className="text-muted-foreground font-normal">
                                    {' '}
                                    · {student.student_number ?? '-'} ·{' '}
                                    {student.class_name ?? '-'}
                                </span>
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label={`Lepas ${student.full_name}`}
                                onClick={() =>
                                    visit(
                                        selectedIds.filter(
                                            (id) => id !== student.id,
                                        ),
                                        search,
                                    )
                                }
                            >
                                <X />
                            </Button>
                        </div>
                        {student.bills.length === 0 && (
                            <p className="text-muted-foreground text-sm">
                                Tidak ada tagihan terbuka.
                            </p>
                        )}
                        {student.bills.map((bill) => (
                            <div
                                key={bill.id}
                                className="grid grid-cols-[1fr_auto_10rem] items-center gap-3 text-sm"
                            >
                                <span>
                                    {bill.title}
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · jatuh tempo {bill.due_date}
                                    </span>
                                </span>
                                <span className="text-muted-foreground tabular-nums">
                                    Sisa {rupiah(toCents(bill.remaining) / 100)}
                                </span>
                                <Input
                                    type="number"
                                    min={0}
                                    max={bill.remaining}
                                    step="0.01"
                                    aria-label={`Alokasi ${bill.title}`}
                                    value={allocations[bill.id] ?? ''}
                                    onChange={(event) =>
                                        setAllocations((current) => {
                                            const next = { ...current };

                                            if (event.target.value === '') {
                                                delete next[bill.id];
                                            } else {
                                                next[bill.id] =
                                                    event.target.value;
                                            }

                                            return next;
                                        })
                                    }
                                />
                            </div>
                        ))}
                    </div>
                ))}

                {selectedStudents.length > 0 && (
                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="amount_received">
                                Jumlah Diterima (Rp)
                            </Label>
                            <Input
                                id="amount_received"
                                type="number"
                                min={1}
                                step="0.01"
                                value={form.data.amount_received}
                                onChange={(event) => {
                                    form.setData(
                                        'amount_received',
                                        event.target.value,
                                    );
                                    autoAllocate(event.target.value);
                                }}
                            />
                            <InputError message={form.errors.amount_received} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="deposit_account_id">
                                Akun Penerima
                            </Label>
                            <select
                                id="deposit_account_id"
                                value={form.data.deposit_account_id}
                                onChange={(event) =>
                                    form.setData(
                                        'deposit_account_id',
                                        event.target.value,
                                    )
                                }
                                className={selectClass}
                            >
                                {cashAccounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.code} - {account.name}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                message={form.errors.deposit_account_id}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">Catatan</Label>
                            <Input
                                id="notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                            <InputError message={form.errors.notes} />
                        </div>

                        <InputError
                            message={errors.allocations ?? errors.student_ids}
                        />

                        <div className="flex items-center justify-between rounded-lg border p-4 text-sm">
                            <span>
                                Teralokasi{' '}
                                <span className="font-semibold tabular-nums">
                                    {rupiah(allocatedCents / 100)}
                                </span>{' '}
                                dari diterima{' '}
                                <span className="font-semibold tabular-nums">
                                    {rupiah(receivedCents / 100)}
                                </span>
                            </span>
                            <Button
                                type="button"
                                disabled={form.processing || !balanced}
                                onClick={submit}
                            >
                                {form.processing && <Spinner />}
                                Simpan Pembayaran
                            </Button>
                        </div>
                        {receivedCents > 0 && !balanced && (
                            <p className="text-destructive text-sm">
                                Total alokasi harus sama dengan jumlah diterima.
                            </p>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
