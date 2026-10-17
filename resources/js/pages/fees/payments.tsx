import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ChangeEvent } from 'react';
import ReceiptController from '@/actions/App/Http/Controllers/Fees/ReceiptController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PaymentRow = {
    id: number;
    receipt_number: string | null;
    payment_date: string;
    students: string[];
    amount: string;
    method_label: string;
    status: string;
    status_label: string;
    has_receipt: boolean;
};

type Paginator = {
    data: PaymentRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    payments: Paginator;
    filters: {
        date_from: string;
        date_to: string;
        method: string;
        status: string;
    };
    methods: { value: string; label: string }[];
    statuses: { value: string; label: string }[];
    canManage: boolean;
};

const selectClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

const formatRupiah = (value: string) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));

const statusVariant = (status: string) => {
    if (status === 'verified') {
        return 'default' as const;
    }

    if (status === 'rejected' || status === 'void') {
        return 'destructive' as const;
    }

    return 'secondary' as const;
};

export default function PaymentsIndex({
    payments,
    filters,
    methods,
    statuses,
}: Props) {
    const submitFilters = (
        event: ChangeEvent<HTMLInputElement | HTMLSelectElement>,
    ) => {
        event.currentTarget.form?.requestSubmit();
    };

    return (
        <>
            <Head title="Pembayaran" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Pembayaran"
                    description="Daftar pembayaran beserta kuitansinya."
                />

                <form className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="date_from">Dari Tanggal</Label>
                        <Input
                            id="date_from"
                            name="date_from"
                            type="date"
                            defaultValue={filters.date_from}
                            onChange={submitFilters}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="date_to">Sampai Tanggal</Label>
                        <Input
                            id="date_to"
                            name="date_to"
                            type="date"
                            defaultValue={filters.date_to}
                            onChange={submitFilters}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="method">Metode</Label>
                        <select
                            id="method"
                            name="method"
                            defaultValue={filters.method}
                            onChange={submitFilters}
                            className={selectClass}
                        >
                            <option value="">Semua</option>
                            {methods.map((method) => (
                                <option key={method.value} value={method.value}>
                                    {method.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <select
                            id="status"
                            name="status"
                            defaultValue={filters.status}
                            onChange={submitFilters}
                            className={selectClass}
                        >
                            <option value="">Semua</option>
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    No. Kuitansi
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Tanggal
                                </th>
                                <th className="px-4 py-3 font-medium">Siswa</th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Jumlah
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Metode
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {payments.data.map((payment) => (
                                <tr key={payment.id} className="border-t">
                                    <td className="px-4 py-3">
                                        {payment.receipt_number ?? '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {payment.payment_date}
                                    </td>
                                    <td className="px-4 py-3">
                                        {payment.students.join(', ')}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {formatRupiah(payment.amount)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {payment.method_label}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={statusVariant(
                                                payment.status,
                                            )}
                                        >
                                            {payment.status_label}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            {payment.has_receipt && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <a
                                                        href={ReceiptController.show.url(
                                                            {
                                                                payment:
                                                                    payment.id,
                                                            },
                                                        )}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        Kuitansi
                                                    </a>
                                                </Button>
                                            )}
                                            {/* Task 5: void button (canManage, verified payments only) */}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {payments.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={7}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        Belum ada pembayaran.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        Halaman {payments.current_page} dari{' '}
                        {payments.last_page}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!payments.prev_page_url}
                        >
                            <Link href={payments.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                Sebelumnya
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!payments.next_page_url}
                        >
                            <Link href={payments.next_page_url ?? '#'}>
                                Berikutnya
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
