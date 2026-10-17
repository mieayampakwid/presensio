import { Form, Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState, type ChangeEvent } from 'react';
import BillController from '@/actions/App/Http/Controllers/Fees/BillController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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

type BillRow = {
    id: number;
    student_name: string;
    student_number: string | null;
    class_name: string | null;
    fee_type_name: string;
    title: string;
    period_key: string;
    due_date: string;
    amount: string;
    discount_amount: string;
    net_amount: string;
    paid_amount: string;
    max_discount: string;
    status: string;
    status_label: string;
    can_adjust_discount: boolean;
    can_cancel: boolean;
    can_write_off: boolean;
};

type Paginator = {
    data: BillRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    bills: Paginator;
    filters: {
        search: string;
        class_id: string;
        fee_type_id: string;
        period_key: string;
        status: string;
    };
    classes: { id: number; name: string }[];
    feeTypes: { id: number; name: string; code: string }[];
    statuses: { value: string; label: string }[];
    canManage: boolean;
};

type Action = { kind: 'discount' | 'cancel' | 'write-off'; bill: BillRow };

const selectClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

const formatRupiah = (value: string) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));

const statusVariant = (status: string) => {
    if (status === 'paid' || status === 'waived') {
        return 'default' as const;
    }

    if (status === 'cancelled' || status === 'written_off') {
        return 'destructive' as const;
    }

    return 'secondary' as const;
};

const dialogCopy = {
    discount: {
        title: 'Ubah Diskon',
        submit: 'Simpan',
        description:
            'Ubah diskon tagihan. Tagihan yang sudah diakui akan dijurnal ulang.',
    },
    cancel: {
        title: 'Batalkan Tagihan',
        submit: 'Batalkan Tagihan',
        description:
            'Tagihan tanpa pembayaran terverifikasi dibatalkan dan pengakuannya dibalik.',
    },
    'write-off': {
        title: 'Hapusbukukan Piutang',
        submit: 'Hapusbukukan',
        description: 'Sisa piutang tagihan dihapusbukukan sebagai beban.',
    },
};

export default function BillsIndex({
    bills,
    filters,
    classes,
    feeTypes,
    statuses,
    canManage,
}: Props) {
    const [action, setAction] = useState<Action | null>(null);

    const submitFilters = (
        event: ChangeEvent<HTMLInputElement | HTMLSelectElement>,
    ) => {
        event.currentTarget.form?.requestSubmit();
    };

    const renderActionForm = ({ kind, bill }: Action) => {
        const form =
            kind === 'discount'
                ? BillController.discount.form({ bill: bill.id })
                : kind === 'cancel'
                  ? BillController.cancel.form({ bill: bill.id })
                  : BillController.writeOff.form({ bill: bill.id });

        return (
            <Form
                key={`${kind}-${bill.id}`}
                {...form}
                onSuccess={() => setAction(null)}
                className="space-y-4"
            >
                {({ processing, errors }) => (
                    <>
                        {kind === 'discount' && (
                            <div className="grid gap-2">
                                <Label htmlFor="discount_amount">
                                    Diskon (Rp)
                                </Label>
                                <Input
                                    id="discount_amount"
                                    name="discount_amount"
                                    type="number"
                                    required
                                    min={0}
                                    max={bill.max_discount}
                                    step="0.01"
                                    defaultValue={bill.discount_amount}
                                />
                                <InputError message={errors.discount_amount} />
                            </div>
                        )}
                        <div className="grid gap-2">
                            <Label htmlFor="reason">Alasan</Label>
                            <Input
                                id="reason"
                                name="reason"
                                required
                                maxLength={255}
                            />
                            <InputError message={errors.reason} />
                        </div>
                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setAction(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                variant={
                                    kind === 'discount'
                                        ? 'default'
                                        : 'destructive'
                                }
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {dialogCopy[kind].submit}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </Form>
        );
    };

    return (
        <>
            <Head title="Tagihan" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Tagihan"
                    description="Daftar tagihan siswa beserta status pembayarannya."
                />

                <form className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="search">Cari</Label>
                        <Input
                            id="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Nama atau NIS siswa..."
                            onChange={submitFilters}
                            className="w-64"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="class_id">Kelas</Label>
                        <select
                            id="class_id"
                            name="class_id"
                            defaultValue={filters.class_id}
                            onChange={submitFilters}
                            className={selectClass}
                        >
                            <option value="">Semua</option>
                            {classes.map((schoolClass) => (
                                <option
                                    key={schoolClass.id}
                                    value={schoolClass.id}
                                >
                                    {schoolClass.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="fee_type_id">Jenis Biaya</Label>
                        <select
                            id="fee_type_id"
                            name="fee_type_id"
                            defaultValue={filters.fee_type_id}
                            onChange={submitFilters}
                            className={selectClass}
                        >
                            <option value="">Semua</option>
                            {feeTypes.map((feeType) => (
                                <option key={feeType.id} value={feeType.id}>
                                    {feeType.code} - {feeType.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="period_key">Periode</Label>
                        <Input
                            id="period_key"
                            name="period_key"
                            defaultValue={filters.period_key}
                            placeholder="2026-09"
                            onChange={submitFilters}
                            className="w-32"
                        />
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
                                <th className="px-4 py-3 font-medium">Siswa</th>
                                <th className="px-4 py-3 font-medium">Kelas</th>
                                <th className="px-4 py-3 font-medium">
                                    Tagihan
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Periode
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Jatuh Tempo
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Netto
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Dibayar
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                {canManage && (
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {bills.data.map((bill) => (
                                <tr key={bill.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {bill.student_name}
                                        </div>
                                        {bill.student_number && (
                                            <div className="text-muted-foreground text-xs">
                                                {bill.student_number}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {bill.class_name ?? '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div>{bill.title}</div>
                                        <div className="text-muted-foreground text-xs">
                                            {bill.fee_type_name}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {bill.period_key}
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
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={statusVariant(bill.status)}
                                        >
                                            {bill.status_label}
                                        </Badge>
                                    </td>
                                    {canManage && (
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                {bill.can_adjust_discount && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setAction({
                                                                kind: 'discount',
                                                                bill,
                                                            })
                                                        }
                                                    >
                                                        Diskon
                                                    </Button>
                                                )}
                                                {bill.can_cancel && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() =>
                                                            setAction({
                                                                kind: 'cancel',
                                                                bill,
                                                            })
                                                        }
                                                    >
                                                        Batalkan
                                                    </Button>
                                                )}
                                                {bill.can_write_off && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() =>
                                                            setAction({
                                                                kind: 'write-off',
                                                                bill,
                                                            })
                                                        }
                                                    >
                                                        Hapusbukukan
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}

                            {bills.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={canManage ? 9 : 8}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        Belum ada tagihan.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        Halaman {bills.current_page} dari {bills.last_page}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!bills.prev_page_url}
                        >
                            <Link href={bills.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                Sebelumnya
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!bills.next_page_url}
                        >
                            <Link href={bills.next_page_url ?? '#'}>
                                Berikutnya
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog
                open={action !== null}
                onOpenChange={(open) => !open && setAction(null)}
            >
                <DialogContent>
                    {action && (
                        <>
                            <DialogTitle>
                                {dialogCopy[action.kind].title}
                            </DialogTitle>
                            <DialogDescription>
                                {action.bill.title} - {action.bill.student_name}
                                . {dialogCopy[action.kind].description}
                            </DialogDescription>
                            {renderActionForm(action)}
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
