import { Form, Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-react';
import { useState, type ChangeEvent } from 'react';
import DiscountController from '@/actions/App/Http/Controllers/Fees/DiscountController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';

type DiscountRow = {
    id: number;
    student_id: number;
    student_name: string;
    student_number: string | null;
    fee_type_id: number;
    fee_type_name: string;
    academic_year_id: number;
    academic_year_name: string;
    percent: string | null;
    fixed_amount: string | null;
    reason: string;
};

type Paginator = {
    data: DiscountRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    discounts: Paginator;
    filters: { search: string };
    feeTypes: { id: number; name: string; code: string }[];
    academicYears: { id: number; name: string }[];
    students: {
        id: number;
        full_name: string;
        student_number: string | null;
    }[];
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

export default function DiscountsIndex({
    discounts,
    filters,
    feeTypes,
    academicYears,
    students,
    canManage,
}: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editing, setEditing] = useState<DiscountRow | null>(null);
    const [deleting, setDeleting] = useState<DiscountRow | null>(null);

    const submitFilters = (event: ChangeEvent<HTMLInputElement>) => {
        event.currentTarget.form?.requestSubmit();
    };

    const renderForm = (discount: DiscountRow | null, onDone: () => void) => (
        <Form
            key={discount?.id ?? 'create'}
            {...(discount
                ? DiscountController.update.form({ discount: discount.id })
                : DiscountController.store.form())}
            onSuccess={onDone}
            className="flex flex-1 flex-col justify-between space-y-4 overflow-y-auto px-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="student_id">Siswa</Label>
                            <select
                                id="student_id"
                                name="student_id"
                                required
                                defaultValue={discount?.student_id ?? ''}
                                className={selectClass}
                            >
                                <option value="" disabled>
                                    Pilih siswa...
                                </option>
                                {students.map((student) => (
                                    <option key={student.id} value={student.id}>
                                        {student.full_name}
                                        {student.student_number
                                            ? ` (${student.student_number})`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.student_id} />
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="fee_type_id">Jenis Biaya</Label>
                                <select
                                    id="fee_type_id"
                                    name="fee_type_id"
                                    required
                                    defaultValue={discount?.fee_type_id ?? ''}
                                    className={selectClass}
                                >
                                    <option value="" disabled>
                                        Pilih jenis biaya...
                                    </option>
                                    {feeTypes.map((feeType) => (
                                        <option
                                            key={feeType.id}
                                            value={feeType.id}
                                        >
                                            {feeType.code} - {feeType.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.fee_type_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="academic_year_id">
                                    Tahun Ajaran
                                </Label>
                                <select
                                    id="academic_year_id"
                                    name="academic_year_id"
                                    required
                                    defaultValue={
                                        discount?.academic_year_id ?? ''
                                    }
                                    className={selectClass}
                                >
                                    <option value="" disabled>
                                        Pilih tahun ajaran...
                                    </option>
                                    {academicYears.map((year) => (
                                        <option key={year.id} value={year.id}>
                                            {year.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.academic_year_id} />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="percent">Persentase (%)</Label>
                                <Input
                                    id="percent"
                                    name="percent"
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="0.01"
                                    defaultValue={discount?.percent ?? ''}
                                />
                                <InputError message={errors.percent} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="fixed_amount">
                                    Nominal Tetap (Rp)
                                </Label>
                                <Input
                                    id="fixed_amount"
                                    name="fixed_amount"
                                    type="number"
                                    min={1}
                                    step="0.01"
                                    defaultValue={discount?.fixed_amount ?? ''}
                                />
                                <InputError message={errors.fixed_amount} />
                            </div>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            Isi salah satu: persentase atau nominal tetap.
                        </p>

                        <div className="grid gap-2">
                            <Label htmlFor="reason">Alasan</Label>
                            <Input
                                id="reason"
                                name="reason"
                                required
                                maxLength={255}
                                defaultValue={discount?.reason}
                                placeholder="mis. Beasiswa prestasi, saudara kandung, yatim"
                            />
                            <InputError message={errors.reason} />
                        </div>
                    </div>

                    <SheetFooter className="px-0">
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Simpan
                        </Button>
                    </SheetFooter>
                </>
            )}
        </Form>
    );

    return (
        <>
            <Head title="Diskon & Beasiswa" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Diskon & Beasiswa"
                        description="Potongan biaya per siswa. Perubahan hanya berlaku untuk tagihan yang dibuat setelahnya."
                    />
                    {canManage && (
                        <Button onClick={() => setIsCreateOpen(true)}>
                            Tambah Diskon
                        </Button>
                    )}
                </div>

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
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Siswa</th>
                                <th className="px-4 py-3 font-medium">
                                    Jenis Biaya
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Tahun Ajaran
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Potongan
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Alasan
                                </th>
                                {canManage && (
                                    <th className="px-4 py-3 text-right font-medium">
                                        Aksi
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {discounts.data.map((discount) => (
                                <tr key={discount.id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {discount.student_name}
                                        </div>
                                        {discount.student_number && (
                                            <div className="text-muted-foreground text-xs">
                                                {discount.student_number}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {discount.fee_type_name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {discount.academic_year_name}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {discount.percent !== null
                                            ? `${Number(discount.percent)}%`
                                            : formatRupiah(
                                                  discount.fixed_amount ?? '0',
                                              )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {discount.reason}
                                    </td>
                                    {canManage && (
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setEditing(discount)
                                                    }
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        Ubah
                                                    </span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() =>
                                                        setDeleting(discount)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        Hapus
                                                    </span>
                                                </Button>
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}

                            {discounts.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={canManage ? 6 : 5}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        Belum ada diskon.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        Halaman {discounts.current_page} dari{' '}
                        {discounts.last_page}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!discounts.prev_page_url}
                        >
                            <Link href={discounts.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                Sebelumnya
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!discounts.next_page_url}
                        >
                            <Link href={discounts.next_page_url ?? '#'}>
                                Berikutnya
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>Hapus Diskon</DialogTitle>
                    <DialogDescription>
                        Hapus diskon {deleting?.fee_type_name} untuk{' '}
                        {deleting?.student_name}? Tagihan yang sudah dibuat
                        tidak berubah.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(null)}
                        >
                            Batal
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (deleting) {
                                    router.delete(
                                        DiscountController.destroy.url({
                                            discount: deleting.id,
                                        }),
                                        { preserveScroll: true },
                                    );
                                    setDeleting(null);
                                }
                            }}
                        >
                            Hapus
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Sheet open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <SheetContent side="right" className="sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Tambah Diskon</SheetTitle>
                        <SheetDescription>
                            Satu diskon per siswa, jenis biaya, dan tahun
                            ajaran.
                        </SheetDescription>
                    </SheetHeader>
                    {renderForm(null, () => setIsCreateOpen(false))}
                </SheetContent>
            </Sheet>

            <Sheet
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <SheetContent side="right" className="sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Ubah Diskon</SheetTitle>
                        <SheetDescription>
                            Perubahan hanya berlaku untuk tagihan berikutnya.
                        </SheetDescription>
                    </SheetHeader>
                    {editing && renderForm(editing, () => setEditing(null))}
                </SheetContent>
            </Sheet>
        </>
    );
}
