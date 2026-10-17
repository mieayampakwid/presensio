import { Form, Head, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import FeeTypeController from '@/actions/App/Http/Controllers/Fees/FeeTypeController';
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

type FeeTypeRow = {
    id: number;
    name: string;
    code: string;
    type: string;
    type_label: string;
    default_amount: string;
    academic_year_id: number | null;
    description: string | null;
    receivable_account_id: number;
    revenue_account_id: number;
    discount_account_id: number;
    is_active: boolean;
    is_in_use: boolean;
};

type KindOption = { value: string; label: string };
type YearOption = { id: number; name: string };
type AccountOption = { id: number; code: string; name: string };

type Props = {
    feeTypes: FeeTypeRow[];
    kinds: KindOption[];
    academicYears: YearOption[];
    receivableAccounts: AccountOption[];
    revenueAccounts: AccountOption[];
    discountAccounts: AccountOption[];
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

export default function FeeTypesIndex({
    feeTypes,
    kinds,
    academicYears,
    receivableAccounts,
    revenueAccounts,
    discountAccounts,
    canManage,
}: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editing, setEditing] = useState<FeeTypeRow | null>(null);
    const [deleting, setDeleting] = useState<FeeTypeRow | null>(null);

    const accountSelect = (
        name: string,
        label: string,
        options: AccountOption[],
        value: number | undefined,
        error: string | undefined,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <select
                id={name}
                name={name}
                required
                defaultValue={value ?? ''}
                className={selectClass}
            >
                <option value="" disabled>
                    Pilih akun...
                </option>
                {options.map((account) => (
                    <option key={account.id} value={account.id}>
                        {account.code} - {account.name}
                    </option>
                ))}
            </select>
            <InputError message={error} />
        </div>
    );

    const renderForm = (feeType: FeeTypeRow | null, onDone: () => void) => (
        <Form
            key={feeType?.id ?? 'create'}
            {...(feeType
                ? FeeTypeController.update.form({ fee_type: feeType.id })
                : FeeTypeController.store.form())}
            onSuccess={onDone}
            className="flex flex-1 flex-col justify-between space-y-4 overflow-y-auto px-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="code">Kode</Label>
                                <Input
                                    id="code"
                                    name="code"
                                    required
                                    autoComplete="off"
                                    maxLength={30}
                                    defaultValue={feeType?.code}
                                    placeholder="mis. SPP"
                                    className="uppercase"
                                />
                                <InputError message={errors.code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoComplete="off"
                                    maxLength={255}
                                    defaultValue={feeType?.name}
                                    placeholder="mis. SPP Bulanan"
                                />
                                <InputError message={errors.name} />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="type">Jenis</Label>
                                <select
                                    id="type"
                                    name="type"
                                    required
                                    defaultValue={
                                        feeType?.type ?? kinds[0]?.value
                                    }
                                    className={selectClass}
                                >
                                    {kinds.map((kind) => (
                                        <option
                                            key={kind.value}
                                            value={kind.value}
                                        >
                                            {kind.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.type} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="default_amount">
                                    Nominal Default (Rp)
                                </Label>
                                <Input
                                    id="default_amount"
                                    name="default_amount"
                                    type="number"
                                    min={1}
                                    step="0.01"
                                    required
                                    defaultValue={feeType?.default_amount}
                                />
                                <InputError message={errors.default_amount} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="academic_year_id">
                                Tahun Ajaran (opsional)
                            </Label>
                            <select
                                id="academic_year_id"
                                name="academic_year_id"
                                defaultValue={feeType?.academic_year_id ?? ''}
                                className={selectClass}
                            >
                                <option value="">Semua tahun ajaran</option>
                                {academicYears.map((year) => (
                                    <option key={year.id} value={year.id}>
                                        {year.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.academic_year_id} />
                        </div>

                        {accountSelect(
                            'receivable_account_id',
                            'Akun Piutang',
                            receivableAccounts,
                            feeType?.receivable_account_id,
                            errors.receivable_account_id,
                        )}
                        {accountSelect(
                            'revenue_account_id',
                            'Akun Pendapatan',
                            revenueAccounts,
                            feeType?.revenue_account_id,
                            errors.revenue_account_id,
                        )}
                        {accountSelect(
                            'discount_account_id',
                            'Akun Diskon (kontra pendapatan)',
                            discountAccounts,
                            feeType?.discount_account_id,
                            errors.discount_account_id,
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="description">
                                Deskripsi (opsional)
                            </Label>
                            <textarea
                                id="description"
                                name="description"
                                rows={3}
                                defaultValue={feeType?.description ?? ''}
                                className="border-input placeholder:text-muted-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm"
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0" />
                            <input
                                type="checkbox"
                                id="is_active"
                                name="is_active"
                                value="1"
                                defaultChecked={feeType?.is_active ?? true}
                                className="text-primary focus:ring-primary h-4 w-4 rounded border-gray-300"
                            />
                            <Label
                                htmlFor="is_active"
                                className="cursor-pointer"
                            >
                                Aktif
                            </Label>
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
            <Head title="Jenis Biaya" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Jenis Biaya"
                        description="Katalog biaya sekolah beserta pemetaan akun piutang, pendapatan, dan diskon."
                    />
                    {canManage && (
                        <Button onClick={() => setIsCreateOpen(true)}>
                            Tambah Jenis Biaya
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Kode</th>
                                <th className="px-4 py-3 font-medium">Nama</th>
                                <th className="px-4 py-3 font-medium">Jenis</th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Nominal Default
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
                            {feeTypes.map((feeType) => (
                                <tr key={feeType.id} className="border-t">
                                    <td className="px-4 py-3 font-mono font-medium">
                                        {feeType.code}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {feeType.name}
                                        </div>
                                        {feeType.description && (
                                            <div className="text-muted-foreground text-xs">
                                                {feeType.description}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {feeType.type_label}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {formatRupiah(feeType.default_amount)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {feeType.is_active
                                            ? 'Aktif'
                                            : 'Nonaktif'}
                                    </td>
                                    {canManage && (
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setEditing(feeType)
                                                    }
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        Ubah
                                                    </span>
                                                </Button>
                                                {!feeType.is_in_use && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() =>
                                                            setDeleting(feeType)
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                        <span className="sr-only">
                                                            Hapus
                                                        </span>
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}

                            {feeTypes.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={canManage ? 6 : 5}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        Belum ada jenis biaya.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>Hapus Jenis Biaya</DialogTitle>
                    <DialogDescription>
                        Hapus {deleting?.name} ({deleting?.code})? Tindakan ini
                        tidak dapat dibatalkan.
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
                                        FeeTypeController.destroy.url({
                                            fee_type: deleting.id,
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
                        <SheetTitle>Tambah Jenis Biaya</SheetTitle>
                        <SheetDescription>
                            Tentukan nominal default dan pemetaan tiga akun.
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
                        <SheetTitle>Ubah Jenis Biaya</SheetTitle>
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
