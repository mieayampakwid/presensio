import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Check,
    FolderTree,
    Pencil,
    Plus,
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';

type AccountRow = {
    id: number;
    code: string;
    name: string;
    type: string;
    type_label: string;
    normal_balance: string;
    is_contra: boolean;
    restriction: string | null;
    restriction_label: string | null;
    parent_id: number | null;
    parent: { id: number; code: string; name: string } | null;
    is_postable: boolean;
    is_cash: boolean;
    is_active: boolean;
    has_posted_lines: boolean;
    journal_lines_count: number;
    children_count: number;
};

type TypeOption = {
    value: string;
    label: string;
    normal_balance: string;
};

type RestrictionOption = {
    value: string;
    label: string;
};

type Props = {
    accounts: AccountRow[];
    types: TypeOption[];
    restrictions: RestrictionOption[];
};

export default function AccountsIndex({ accounts, types, restrictions }: Props) {
    const [selectedType, setSelectedType] = useState<string>('all');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingAccount, setEditingAccount] = useState<AccountRow | null>(null);
    const [deletingAccount, setDeletingAccount] = useState<AccountRow | null>(null);

    const form = useForm({
        code: '',
        name: '',
        type: 'asset',
        is_contra: false,
        restriction: '' as string,
        parent_id: '' as string | number,
        is_postable: true,
        is_cash: false,
        is_active: true,
    });

    const openCreateModal = () => {
        form.reset();
        form.clearErrors();
        form.setData({
            code: '',
            name: '',
            type: 'asset',
            is_contra: false,
            restriction: '',
            parent_id: '',
            is_postable: true,
            is_cash: false,
            is_active: true,
        });
        setIsCreateOpen(true);
    };

    const openEditModal = (account: AccountRow) => {
        form.reset();
        form.clearErrors();
        form.setData({
            code: account.code,
            name: account.name,
            type: account.type,
            is_contra: account.is_contra,
            restriction: account.restriction ?? '',
            parent_id: account.parent_id ?? '',
            is_postable: account.is_postable,
            is_cash: account.is_cash,
            is_active: account.is_active,
        });
        setEditingAccount(account);
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/finance/accounts', {
            preserveScroll: true,
            onSuccess: () => {
                setIsCreateOpen(false);
                form.reset();
            },
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingAccount) return;
        form.put(`/finance/accounts/${editingAccount.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingAccount(null);
                form.reset();
            },
        });
    };

    const handleDeleteSubmit = () => {
        if (!deletingAccount) return;
        router.delete(`/finance/accounts/${deletingAccount.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeletingAccount(null),
        });
    };

    const filteredAccounts = selectedType === 'all'
        ? accounts
        : accounts.filter((acc) => acc.type === selectedType);

    const parentCandidates = accounts.filter(
        (acc) => !acc.has_posted_lines && (!editingAccount || acc.id !== editingAccount.id)
    );

    return (
        <>
            <Head title="Bagan Akun" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <Heading
                        title="Bagan Akun (Chart of Accounts)"
                        description="Kelola akun buku besar, saldo normal, dan hierarki pelaporan keuangan."
                    />

                    <Button onClick={openCreateModal}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Akun
                    </Button>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button
                        variant={selectedType === 'all' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setSelectedType('all')}
                    >
                        Semua ({accounts.length})
                    </Button>
                    {types.map((type) => {
                        const count = accounts.filter((a) => a.type === type.value).length;
                        return (
                            <Button
                                key={type.value}
                                variant={selectedType === type.value ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => setSelectedType(type.value)}
                            >
                                {type.label} ({count})
                            </Button>
                        );
                    })}
                </div>

                <div className="overflow-x-auto rounded-lg border bg-card">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Kode</th>
                                <th className="px-4 py-3 font-medium">Nama Akun</th>
                                <th className="px-4 py-3 font-medium">Klasifikasi</th>
                                <th className="px-4 py-3 font-medium">Saldo Normal</th>
                                <th className="px-4 py-3 font-medium">Karakteristik</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredAccounts.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-muted-foreground">
                                        Belum ada akun dalam kategori ini.
                                    </td>
                                </tr>
                            ) : (
                                filteredAccounts.map((account) => (
                                    <tr key={account.id} className="border-t hover:bg-muted/20">
                                        <td className="px-4 py-3 font-mono font-medium">
                                            {account.code}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                {account.parent_id && (
                                                    <span className="text-muted-foreground text-xs font-mono">↳</span>
                                                )}
                                                <span className="font-medium">{account.name}</span>
                                            </div>
                                            {account.parent && (
                                                <div className="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                                                    <FolderTree className="h-3 w-3" />
                                                    {account.parent.code} - {account.parent.name}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant="outline">{account.type_label}</Badge>
                                            {account.restriction_label && (
                                                <span className="ml-1 text-xs text-muted-foreground">
                                                    ({account.restriction_label})
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`text-xs font-medium uppercase px-2 py-0.5 rounded ${
                                                    account.normal_balance === 'debit'
                                                        ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
                                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                                                }`}
                                            >
                                                {account.normal_balance}
                                            </span>
                                            {account.is_contra && (
                                                <span className="ml-1 text-xs text-destructive font-medium">
                                                    (Contra)
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-1">
                                                {account.is_cash && (
                                                    <Badge variant="secondary" className="text-xs">
                                                        Kas/Bank
                                                    </Badge>
                                                )}
                                                {account.is_postable ? (
                                                    <Badge variant="outline" className="text-xs text-green-700 border-green-300">
                                                        Postable
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline" className="text-xs text-muted-foreground">
                                                        Grup
                                                    </Badge>
                                                )}
                                                {account.has_posted_lines && (
                                                    <Badge variant="default" className="text-xs bg-slate-700">
                                                        Ada Transaksi
                                                    </Badge>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {account.is_active ? (
                                                <span className="inline-flex items-center text-xs text-emerald-600 font-medium">
                                                    <Check className="h-3.5 w-3.5 mr-1" /> Aktif
                                                </span>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">Nonaktif</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => openEditModal(account)}
                                                    title="Edit akun"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </Button>

                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={account.journal_lines_count > 0 || account.children_count > 0}
                                                    onClick={() => setDeletingAccount(account)}
                                                    className="text-destructive hover:text-destructive"
                                                    title={
                                                        account.journal_lines_count > 0
                                                            ? 'Akun bertransaksi tidak dapat dihapus'
                                                            : account.children_count > 0
                                                            ? 'Akun dengan sub-akun tidak dapat dihapus'
                                                            : 'Hapus akun'
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Create Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Tambah Akun Baru</DialogTitle>
                        <DialogDescription>
                            Isi parameter akun untuk ditambahkan ke bagan akun.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleCreateSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="create-code">Kode Akun</Label>
                                <Input
                                    id="create-code"
                                    placeholder="Contoh: 1-1400"
                                    value={form.data.code}
                                    onChange={(e) => form.setData('code', e.target.value)}
                                    required
                                />
                                {form.errors.code && (
                                    <p className="text-xs text-destructive">{form.errors.code}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="create-type">Klasifikasi</Label>
                                <Select
                                    value={form.data.type}
                                    onValueChange={(val) => {
                                        form.setData('type', val);
                                        if (val !== 'net_assets') {
                                            form.setData('restriction', '');
                                        }
                                    }}
                                >
                                    <SelectTrigger id="create-type">
                                        <SelectValue placeholder="Pilih tipe" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {types.map((t) => (
                                            <SelectItem key={t.value} value={t.value}>
                                                {t.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.type && (
                                    <p className="text-xs text-destructive">{form.errors.type}</p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-name">Nama Akun</Label>
                            <Input
                                id="create-name"
                                placeholder="Nama akun..."
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                required
                            />
                            {form.errors.name && (
                                <p className="text-xs text-destructive">{form.errors.name}</p>
                            )}
                        </div>

                        {form.data.type === 'net_assets' && (
                            <div className="space-y-2">
                                <Label htmlFor="create-restriction">Pembatasan (ISAK 35)</Label>
                                <Select
                                    value={form.data.restriction}
                                    onValueChange={(val) => form.setData('restriction', val)}
                                >
                                    <SelectTrigger id="create-restriction">
                                        <SelectValue placeholder="Pilih batasan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {restrictions.map((r) => (
                                            <SelectItem key={r.value} value={r.value}>
                                                {r.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.restriction && (
                                    <p className="text-xs text-destructive">{form.errors.restriction}</p>
                                )}
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor="create-parent">Akun Induk (Opsional)</Label>
                            <Select
                                value={form.data.parent_id ? String(form.data.parent_id) : 'none'}
                                onValueChange={(val) =>
                                    form.setData('parent_id', val === 'none' ? '' : Number(val))
                                }
                            >
                                <SelectTrigger id="create-parent">
                                    <SelectValue placeholder="Tanpa induk (tingkat utama)" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Tanpa induk</SelectItem>
                                    {parentCandidates.map((parent) => (
                                        <SelectItem key={parent.id} value={String(parent.id)}>
                                            {parent.code} - {parent.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.parent_id && (
                                <p className="text-xs text-destructive">{form.errors.parent_id}</p>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4 pt-2">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="create-contra"
                                    checked={form.data.is_contra}
                                    onCheckedChange={(checked) => form.setData('is_contra', checked === true)}
                                />
                                <Label htmlFor="create-contra" className="cursor-pointer">
                                    Akun Contra
                                </Label>
                            </div>

                            {form.data.type === 'asset' && (
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="create-cash"
                                        checked={form.data.is_cash}
                                        onCheckedChange={(checked) => form.setData('is_cash', checked === true)}
                                    />
                                    <Label htmlFor="create-cash" className="cursor-pointer">
                                        Kas / Bank
                                    </Label>
                                </div>
                            )}
                        </div>

                        <div className="flex items-center space-x-2 pt-2">
                            <Checkbox
                                id="create-postable"
                                checked={form.data.is_postable}
                                onCheckedChange={(checked) => form.setData('is_postable', checked === true)}
                            />
                            <Label htmlFor="create-postable" className="cursor-pointer">
                                Postable (Dapat menerima jurnal)
                            </Label>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsCreateOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Simpan Akun
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={editingAccount !== null} onOpenChange={(open) => !open && setEditingAccount(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit Akun</DialogTitle>
                        <DialogDescription>
                            {editingAccount?.has_posted_lines
                                ? 'Akun ini telah memiliki transaksi. Hanya nama dan status aktif yang dapat diubah.'
                                : 'Perbarui informasi akun.'}
                        </DialogDescription>
                    </DialogHeader>

                    {editingAccount?.has_posted_lines && (
                        <div className="flex items-center gap-2 p-3 text-xs bg-amber-50 text-amber-800 dark:bg-amber-950/30 dark:text-amber-300 rounded border border-amber-200">
                            <AlertCircle className="h-4 w-4 shrink-0" />
                            <span>Struktur akun terkunci karena telah memiliki jurnal posted.</span>
                        </div>
                    )}

                    <form onSubmit={handleEditSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="edit-code">Kode Akun</Label>
                                <Input
                                    id="edit-code"
                                    value={form.data.code}
                                    onChange={(e) => form.setData('code', e.target.value)}
                                    disabled={editingAccount?.has_posted_lines}
                                    required
                                />
                                {form.errors.code && (
                                    <p className="text-xs text-destructive">{form.errors.code}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="edit-type">Klasifikasi</Label>
                                <Select
                                    value={form.data.type}
                                    disabled={editingAccount?.has_posted_lines}
                                    onValueChange={(val) => {
                                        form.setData('type', val);
                                        if (val !== 'net_assets') {
                                            form.setData('restriction', '');
                                        }
                                    }}
                                >
                                    <SelectTrigger id="edit-type">
                                        <SelectValue placeholder="Pilih tipe" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {types.map((t) => (
                                            <SelectItem key={t.value} value={t.value}>
                                                {t.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.type && (
                                    <p className="text-xs text-destructive">{form.errors.type}</p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-name">Nama Akun</Label>
                            <Input
                                id="edit-name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                required
                            />
                            {form.errors.name && (
                                <p className="text-xs text-destructive">{form.errors.name}</p>
                            )}
                        </div>

                        <div className="flex items-center space-x-2 pt-2">
                            <Checkbox
                                id="edit-active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) => form.setData('is_active', checked === true)}
                            />
                            <Label htmlFor="edit-active" className="cursor-pointer">
                                Akun Aktif
                            </Label>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditingAccount(null)}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Simpan Perubahan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <Dialog open={deletingAccount !== null} onOpenChange={(open) => !open && setDeletingAccount(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Konfirmasi Hapus Akun</DialogTitle>
                        <DialogDescription>
                            Apakah Anda yakin ingin menghapus akun{' '}
                            <strong>{deletingAccount?.code} - {deletingAccount?.name}</strong>?
                            Tindakan ini tidak dapat dibatalkan.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeletingAccount(null)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleDeleteSubmit}
                        >
                            Hapus Akun
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AccountsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Keuangan',
            href: '/finance/accounts',
        },
        {
            title: 'Bagan Akun',
            href: '/finance/accounts',
        },
    ],
};
