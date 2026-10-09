import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Check,
    FolderTree,
    Pencil,
    Plus,
    RotateCcw,
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

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

const TYPE_PREFIX_MAP: Record<string, string> = {
    asset: '1',
    liability: '2',
    net_assets: '3',
    revenue: '4',
    expense: '5',
};

function generateSuggestedAccountCode(
    accounts: AccountRow[],
    type: string,
    parentId: string | number
): string {
    const typePrefix = TYPE_PREFIX_MAP[type] ?? '1';

    const existingCodes = new Set(accounts.map((a) => a.code.trim()));

    if (parentId && parentId !== 'none') {
        const parent = accounts.find((a) => a.id === Number(parentId));
        if (parent) {
            const siblings = accounts.filter((a) => a.parent_id === parent.id);
            const parentParts = parent.code.split('-');

            if (parentParts.length === 2 && !isNaN(Number(parentParts[1]))) {
                const pPrefix = parentParts[0];
                const pNum = Number(parentParts[1]);

                if (siblings.length > 0) {
                    const siblingNums = siblings
                        .map((s) => s.code.split('-'))
                        .filter((parts) => parts.length === 2 && parts[0] === pPrefix && !isNaN(Number(parts[1])))
                        .map((parts) => Number(parts[1]));

                    if (siblingNums.length > 0) {
                        const maxSibling = Math.max(...siblingNums);
                        const step = (maxSibling - pNum) % 10 === 0 && maxSibling % 10 === 0 ? 10 : 1;
                        let nextNum = maxSibling + step;
                        let candidate = `${pPrefix}-${nextNum}`;
                        while (existingCodes.has(candidate)) {
                            nextNum += step;
                            candidate = `${pPrefix}-${nextNum}`;
                        }
                        return candidate;
                    }
                }

                let step = 10;
                if (pNum % 100 === 0) {
                    step = 10;
                } else if (pNum % 10 === 0) {
                    step = 1;
                } else {
                    step = 1;
                }

                let nextNum = pNum + step;
                let candidate = `${pPrefix}-${nextNum}`;
                while (existingCodes.has(candidate)) {
                    nextNum += step;
                    candidate = `${pPrefix}-${nextNum}`;
                }
                return candidate;
            } else {
                let idx = siblings.length + 1;
                let candidate = `${parent.code}.${idx}`;
                while (existingCodes.has(candidate)) {
                    idx++;
                    candidate = `${parent.code}.${idx}`;
                }
                return candidate;
            }
        }
    }

    const rootAccountsOfSameType = accounts.filter((a) => a.type === type && !a.parent_id);
    const rootNums = rootAccountsOfSameType
        .map((a) => a.code.split('-'))
        .filter((parts) => parts.length === 2 && parts[0] === typePrefix && !isNaN(Number(parts[1])))
        .map((parts) => Number(parts[1]));

    if (rootNums.length > 0) {
        const maxRoot = Math.max(...rootNums);
        const step = maxRoot % 100 === 0 ? 100 : 10;
        let nextNum = maxRoot + step;
        let candidate = `${typePrefix}-${nextNum}`;
        while (existingCodes.has(candidate)) {
            nextNum += step;
            candidate = `${typePrefix}-${nextNum}`;
        }
        return candidate;
    }

    let baseNum = 1000;
    let candidate = `${typePrefix}-${baseNum}`;
    while (existingCodes.has(candidate)) {
        baseNum += 100;
        candidate = `${typePrefix}-${baseNum}`;
    }
    return candidate;
}
export default function AccountsIndex({ accounts, types, restrictions }: Props) {
    const [selectedType, setSelectedType] = useState<string>('all');
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingAccount, setEditingAccount] = useState<AccountRow | null>(null);
    const [deletingAccount, setDeletingAccount] = useState<AccountRow | null>(null);
    const [isCodeTouched, setIsCodeTouched] = useState(false);
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
        setIsCodeTouched(false);
        const initialCode = generateSuggestedAccountCode(accounts, 'asset', '');
        form.setData({
            code: initialCode,
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

    const handleClassificationChange = (newType: string) => {
        let newParentId = form.data.parent_id;
        if (newParentId && newParentId !== 'none') {
            const parentAcc = accounts.find((a) => a.id === Number(newParentId));
            if (!parentAcc || parentAcc.type !== newType) {
                newParentId = '';
            }
        }

        const newCode = isCodeTouched
            ? form.data.code
            : generateSuggestedAccountCode(accounts, newType, newParentId);

        form.setData({
            ...form.data,
            type: newType,
            parent_id: newParentId,
            restriction: newType === 'net_assets' ? form.data.restriction : '',
            is_cash: newType === 'asset' ? form.data.is_cash : false,
            code: newCode,
        });
    };

    const handleParentChange = (val: string) => {
        const newParentId = val === 'none' ? '' : Number(val);
        const newCode = isCodeTouched
            ? form.data.code
            : generateSuggestedAccountCode(accounts, form.data.type, newParentId);

        form.setData({
            ...form.data,
            parent_id: newParentId,
            code: newCode,
        });
    };

    const handleResetCode = () => {
        setIsCodeTouched(false);
        const suggested = generateSuggestedAccountCode(accounts, form.data.type, form.data.parent_id);
        form.setData('code', suggested);
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

    const handleEditClassificationChange = (newType: string) => {
        let newParentId = form.data.parent_id;
        if (newParentId && newParentId !== 'none') {
            const parentAcc = accounts.find((a) => a.id === Number(newParentId));
            if (!parentAcc || parentAcc.type !== newType) {
                newParentId = '';
            }
        }

        form.setData({
            ...form.data,
            type: newType,
            parent_id: newParentId,
            restriction: newType === 'net_assets' ? form.data.restriction : '',
            is_cash: newType === 'asset' ? form.data.is_cash : false,
        });
    };

    const handleEditParentChange = (val: string) => {
        const newParentId = val === 'none' ? '' : Number(val);
        form.setData('parent_id', newParentId);
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

    const createParentCandidates = accounts.filter(
        (acc) => acc.type === form.data.type && !acc.has_posted_lines
    );

    const editParentCandidates = accounts.filter(
        (acc) =>
            acc.type === form.data.type &&
            !acc.has_posted_lines &&
            (!editingAccount || (acc.id !== editingAccount.id && acc.parent_id !== editingAccount.id))
    );

    const isEditingLocked = Boolean(editingAccount?.has_posted_lines);

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
                <DialogContent className="sm:max-w-lg max-h-[90vh] flex flex-col p-0 gap-0 overflow-hidden">
                    <DialogHeader className="p-6 pb-4 border-b shrink-0 bg-background sticky top-0 z-10 pr-12">
                        <DialogTitle>Tambah Akun Baru</DialogTitle>
                        <DialogDescription>
                            Isi parameter akun untuk ditambahkan ke bagan akun (Chart of Accounts).
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleCreateSubmit} className="flex flex-col flex-1 min-h-0 overflow-hidden">
                        <div className="flex-1 overflow-y-auto p-6 space-y-4">
                        {/* 1. Klasifikasi */}
                        <div className="space-y-2">
                            <Label htmlFor="create-type">
                                Klasifikasi <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={form.data.type}
                                onValueChange={handleClassificationChange}
                            >
                                <SelectTrigger id="create-type">
                                    <SelectValue placeholder="Pilih klasifikasi" />
                                </SelectTrigger>
                                <SelectContent>
                                    {types.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            {t.label} ({t.normal_balance === 'debit' ? 'Normal: Debit' : 'Normal: Kredit'})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.type && (
                                <p className="text-xs text-destructive">{form.errors.type}</p>
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

                        {/* 2. Akun Induk (Filtered dari Klasifikasi) */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="create-parent">Akun Induk</Label>
                                <span className="text-[11px] text-muted-foreground">Opsional</span>
                            </div>
                            <Select
                                value={form.data.parent_id ? String(form.data.parent_id) : 'none'}
                                onValueChange={handleParentChange}
                            >
                                <SelectTrigger id="create-parent">
                                    <SelectValue placeholder="Tanpa induk (tingkat utama)" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Tanpa induk (Tingkat Utama)</SelectItem>
                                    {createParentCandidates.map((parent) => (
                                        <SelectItem key={parent.id} value={String(parent.id)}>
                                            {parent.code} - {parent.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-[11px] text-muted-foreground">
                                {createParentCandidates.length === 0
                                    ? 'Belum ada akun induk yang tersedia untuk klasifikasi ini.'
                                    : 'Hanya menampilkan akun dengan klasifikasi yang sama.'}
                            </p>
                            {form.errors.parent_id && (
                                <p className="text-xs text-destructive">{form.errors.parent_id}</p>
                            )}
                        </div>

                        {/* 3. Kode Akun (Otomatis & Prefilled, Editable) */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="create-code">
                                    Kode Akun <span className="text-destructive">*</span>
                                </Label>
                                {isCodeTouched && (
                                    <button
                                        type="button"
                                        onClick={handleResetCode}
                                        className="text-[11px] text-primary hover:underline inline-flex items-center gap-1"
                                    >
                                        <RotateCcw className="h-3 w-3" />
                                        Reset saran kode
                                    </button>
                                )}
                            </div>
                            <Input
                                id="create-code"
                                placeholder="Contoh: 1-1400"
                                value={form.data.code}
                                onChange={(e) => {
                                    setIsCodeTouched(true);
                                    form.setData('code', e.target.value);
                                }}
                                required
                            />
                            <p className="text-[11px] text-muted-foreground">
                                {isCodeTouched
                                    ? 'Kode telah diedit manual.'
                                    : 'Disarankan otomatis berdasarkan klasifikasi & induk (tetap dapat diedit).'}
                            </p>
                            {form.errors.code && (
                                <p className="text-xs text-destructive">{form.errors.code}</p>
                            )}
                        </div>

                        {/* 4. Nama Akun (*) */}
                        <div className="space-y-2">
                            <Label htmlFor="create-name">
                                Nama Akun <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="create-name"
                                placeholder="Contoh: Kas Operasional Sekolah"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                required
                            />
                            {form.errors.name && (
                                <p className="text-xs text-destructive">{form.errors.name}</p>
                            )}
                        </div>

                        {/* Karakteristik Akun: 5, 6, 7 */}
                        <div className="space-y-2.5 pt-2">
                            <Label className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                Karakteristik & Perilaku Akun
                            </Label>

                            <div className="space-y-2">
                                {/* 5. Akun Contra */}
                                <div className="flex items-start space-x-3 rounded-lg border p-3 bg-muted/20 hover:bg-muted/30 transition-colors">
                                    <Checkbox
                                        id="create-contra"
                                        checked={form.data.is_contra}
                                        onCheckedChange={(checked) => form.setData('is_contra', checked === true)}
                                        className="mt-0.5"
                                    />
                                    <div className="flex-1 space-y-1">
                                        <div className="flex items-center gap-1.5">
                                            <Label htmlFor="create-contra" className="cursor-pointer font-medium text-sm">
                                                Akun Contra
                                            </Label>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="text-muted-foreground hover:text-foreground inline-flex items-center justify-center rounded-full p-0.5 focus:outline-none"
                                                        aria-label="Penjelasan Akun Contra"
                                                    >
                                                        <AlertCircle className="h-4 w-4" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent side="top" className="max-w-xs space-y-1.5 p-3 text-xs leading-relaxed">
                                                    <p className="font-semibold text-foreground">Akun Contra</p>
                                                    <p className="text-muted-foreground">
                                                        Akun yang mengurangi saldo kelompok akun utamanya dan memiliki saldo normal berlawanan (misal: Aset normalnya di Debit, tetapi akun contra Aset bersaldo normal Kredit).
                                                    </p>
                                                    <div className="border-t border-border pt-1.5 text-[11px] space-y-1">
                                                        <span className="font-medium text-foreground">Contoh:</span>
                                                        <ul className="list-disc list-inside space-y-0.5 text-muted-foreground">
                                                            <li>Akumulasi Penyusutan Gedung</li>
                                                            <li>Cadangan Kerugian Piutang SPP</li>
                                                            <li>Potongan &amp; Beasiswa Siswa</li>
                                                        </ul>
                                                    </div>
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Mengurangi saldo kelompok akun utama (saldo normal berlawanan).
                                        </p>
                                    </div>
                                </div>

                                {/* 6. Kas / Bank (Khusus Aset) */}
                                {form.data.type === 'asset' && (
                                    <div className="flex items-start space-x-3 rounded-lg border p-3 bg-muted/20 hover:bg-muted/30 transition-colors">
                                        <Checkbox
                                            id="create-cash"
                                            checked={form.data.is_cash}
                                            onCheckedChange={(checked) => form.setData('is_cash', checked === true)}
                                            className="mt-0.5"
                                        />
                                        <div className="flex-1 space-y-1">
                                            <div className="flex items-center gap-1.5">
                                                <Label htmlFor="create-cash" className="cursor-pointer font-medium text-sm">
                                                    Kas / Bank
                                                </Label>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <button
                                                            type="button"
                                                            className="text-muted-foreground hover:text-foreground inline-flex items-center justify-center rounded-full p-0.5 focus:outline-none"
                                                            aria-label="Penjelasan Kas / Bank"
                                                        >
                                                            <AlertCircle className="h-4 w-4" />
                                                        </button>
                                                    </TooltipTrigger>
                                                    <TooltipContent side="top" className="max-w-xs space-y-1.5 p-3 text-xs leading-relaxed">
                                                        <p className="font-semibold text-foreground">Kas / Bank</p>
                                                        <p className="text-muted-foreground">
                                                            Menandai akun likuid berupa uang tunai fisik atau rekening bank aktif yayasan/sekolah. Menjadi acuan transaksi pada Buku Kas (Cash Book) &amp; Laporan Arus Kas.
                                                        </p>
                                                        <div className="border-t border-border pt-1.5 text-[11px] space-y-1">
                                                            <span className="font-medium text-foreground">Contoh:</span>
                                                            <ul className="list-disc list-inside space-y-0.5 text-muted-foreground">
                                                                <li>Kas Operasional Tata Usaha</li>
                                                                <li>Kas Kecil (Petty Cash) Bendahara</li>
                                                                <li>Bank BSI Giro Operasional</li>
                                                                <li>Bank Mandiri Penampung SPP</li>
                                                            </ul>
                                                        </div>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Akun kas tunai fisik atau rekening bank aktif untuk Buku Kas &amp; Arus Kas.
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {/* 7. Postable */}
                                <div className="flex items-start space-x-3 rounded-lg border p-3 bg-muted/20 hover:bg-muted/30 transition-colors">
                                    <Checkbox
                                        id="create-postable"
                                        checked={form.data.is_postable}
                                        onCheckedChange={(checked) => form.setData('is_postable', checked === true)}
                                        className="mt-0.5"
                                    />
                                    <div className="flex-1 space-y-1">
                                        <div className="flex items-center gap-1.5">
                                            <Label htmlFor="create-postable" className="cursor-pointer font-medium text-sm">
                                                Postable (Dapat Menerima Jurnal)
                                            </Label>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="text-muted-foreground hover:text-foreground inline-flex items-center justify-center rounded-full p-0.5 focus:outline-none"
                                                        aria-label="Penjelasan Postable"
                                                    >
                                                        <AlertCircle className="h-4 w-4" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent side="top" className="max-w-xs space-y-1.5 p-3 text-xs leading-relaxed">
                                                    <p className="font-semibold text-foreground">Postable (Akun Posting)</p>
                                                    <p className="text-muted-foreground">
                                                        Menentukan apakah akun dapat dipilih langsung saat mencatat transaksi jurnal umum. Jika tidak dicentang (Non-Postable), akun hanya berfungsi sebagai penampung hierarki/kategori (Header).
                                                    </p>
                                                    <div className="border-t border-border pt-1.5 text-[11px] space-y-1">
                                                        <span className="font-medium text-foreground">Contoh:</span>
                                                        <ul className="list-disc list-inside space-y-0.5 text-muted-foreground">
                                                            <li><strong>Postable:</strong> Kas Tunai, Beban ATK &amp; Fotokopi (dapat dijurnal)</li>
                                                            <li><strong>Non-Postable:</strong> ASET LANCAR, BEBAN OPERASIONAL (hanya judul hierarki)</li>
                                                        </ul>
                                                    </div>
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Dapat dipilih langsung pada transaksi jurnal harian.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>

                        <DialogFooter className="p-6 pt-3 border-t shrink-0 bg-background">
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
                <DialogContent className="sm:max-w-lg max-h-[90vh] flex flex-col p-0 gap-0 overflow-hidden">
                    <DialogHeader className="p-6 pb-4 border-b shrink-0 bg-background sticky top-0 z-10 pr-12">
                        <DialogTitle>Edit Akun</DialogTitle>
                        <DialogDescription>
                            {isEditingLocked
                                ? 'Akun ini telah memiliki transaksi jurnal. Struktur akun terkunci; hanya nama dan status aktif yang dapat diubah.'
                                : 'Perbarui parameter akun pada bagan akun.'}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleEditSubmit} className="flex flex-col flex-1 min-h-0 overflow-hidden">
                        <div className="flex-1 overflow-y-auto p-6 space-y-4">
                            {isEditingLocked && (
                                <div className="flex items-center gap-2 p-3 text-xs bg-amber-50 text-amber-800 dark:bg-amber-950/30 dark:text-amber-300 rounded border border-amber-200">
                                    <AlertCircle className="h-4 w-4 shrink-0" />
                                    <span>Struktur akun terkunci karena telah memiliki jurnal posted. Hanya nama dan status aktif yang dapat diubah.</span>
                                </div>
                            )}
                        {/* 1. Klasifikasi */}
                        <div className="space-y-2">
                            <Label htmlFor="edit-type">
                                Klasifikasi <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={form.data.type}
                                disabled={isEditingLocked}
                                onValueChange={handleEditClassificationChange}
                            >
                                <SelectTrigger id="edit-type">
                                    <SelectValue placeholder="Pilih klasifikasi" />
                                </SelectTrigger>
                                <SelectContent>
                                    {types.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            {t.label} ({t.normal_balance === 'debit' ? 'Normal: Debit' : 'Normal: Kredit'})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.type && (
                                <p className="text-xs text-destructive">{form.errors.type}</p>
                            )}
                        </div>

                        {form.data.type === 'net_assets' && (
                            <div className="space-y-2">
                                <Label htmlFor="edit-restriction">Pembatasan (ISAK 35)</Label>
                                <Select
                                    value={form.data.restriction}
                                    disabled={isEditingLocked}
                                    onValueChange={(val) => form.setData('restriction', val)}
                                >
                                    <SelectTrigger id="edit-restriction">
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

                        {/* 2. Akun Induk (Filtered dari Klasifikasi) */}
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="edit-parent">Akun Induk</Label>
                                <span className="text-[11px] text-muted-foreground">Opsional</span>
                            </div>
                            <Select
                                value={form.data.parent_id ? String(form.data.parent_id) : 'none'}
                                disabled={isEditingLocked}
                                onValueChange={handleEditParentChange}
                            >
                                <SelectTrigger id="edit-parent">
                                    <SelectValue placeholder="Tanpa induk (tingkat utama)" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Tanpa induk (Tingkat Utama)</SelectItem>
                                    {editParentCandidates.map((parent) => (
                                        <SelectItem key={parent.id} value={String(parent.id)}>
                                            {parent.code} - {parent.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-[11px] text-muted-foreground">
                                {editParentCandidates.length === 0
                                    ? 'Belum ada akun induk yang tersedia untuk klasifikasi ini.'
                                    : 'Hanya menampilkan akun dengan klasifikasi yang sama.'}
                            </p>
                            {form.errors.parent_id && (
                                <p className="text-xs text-destructive">{form.errors.parent_id}</p>
                            )}
                        </div>

                        {/* 3. Kode Akun */}
                        <div className="space-y-2">
                            <Label htmlFor="edit-code">
                                Kode Akun <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="edit-code"
                                placeholder="Contoh: 1-1400"
                                value={form.data.code}
                                disabled={isEditingLocked}
                                onChange={(e) => form.setData('code', e.target.value)}
                                required
                            />
                            {form.errors.code && (
                                <p className="text-xs text-destructive">{form.errors.code}</p>
                            )}
                        </div>

                        {/* 4. Nama Akun (*) */}
                        <div className="space-y-2">
                            <Label htmlFor="edit-name">
                                Nama Akun <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="edit-name"
                                placeholder="Contoh: Kas Operasional Sekolah"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                required
                            />
                            {form.errors.name && (
                                <p className="text-xs text-destructive">{form.errors.name}</p>
                            )}
                        </div>

                        {/* Karakteristik Akun: 5, 6, 7 & Status Aktif */}
                        <div className="space-y-2.5 pt-2">
                            <Label className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                Atribut & Perilaku Akun
                            </Label>

                            <div className="space-y-2">
                                {/* 5. Akun Contra */}
                                <div className="flex items-start space-x-3 rounded-lg border p-3 bg-muted/20 hover:bg-muted/30 transition-colors">
                                    <Checkbox
                                        id="edit-contra"
                                        checked={form.data.is_contra}
                                        disabled={isEditingLocked}
                                        onCheckedChange={(checked) => form.setData('is_contra', checked === true)}
                                        className="mt-0.5"
                                    />
                                    <div className="flex-1 space-y-1">
                                        <div className="flex items-center gap-1.5">
                                            <Label htmlFor="edit-contra" className="cursor-pointer font-medium text-sm">
                                                Akun Contra
                                            </Label>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="text-muted-foreground hover:text-foreground inline-flex items-center justify-center rounded-full p-0.5 focus:outline-none"
                                                        aria-label="Penjelasan Akun Contra"
                                                    >
                                                        <AlertCircle className="h-4 w-4" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent side="top" className="max-w-xs space-y-1.5 p-3 text-xs leading-relaxed">
                                                    <p className="font-semibold text-foreground">Akun Contra</p>
                                                    <p className="text-muted-foreground">
                                                        Akun yang mengurangi saldo kelompok akun utamanya dan memiliki saldo normal berlawanan (misal: Aset normalnya di Debit, tetapi akun contra Aset bersaldo normal Kredit).
                                                    </p>
                                                    <div className="border-t border-border pt-1.5 text-[11px] space-y-1">
                                                        <span className="font-medium text-foreground">Contoh:</span>
                                                        <ul className="list-disc list-inside space-y-0.5 text-muted-foreground">
                                                            <li>Akumulasi Penyusutan Gedung</li>
                                                            <li>Cadangan Kerugian Piutang SPP</li>
                                                            <li>Potongan &amp; Beasiswa Siswa</li>
                                                        </ul>
                                                    </div>
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Mengurangi saldo kelompok akun utama (saldo normal berlawanan).
                                        </p>
                                    </div>
                                </div>

                                {/* 6. Kas / Bank (Khusus Aset) */}
                                {form.data.type === 'asset' && (
                                    <div className="flex items-start space-x-3 rounded-lg border p-3 bg-muted/20 hover:bg-muted/30 transition-colors">
                                        <Checkbox
                                            id="edit-cash"
                                            checked={form.data.is_cash}
                                            disabled={isEditingLocked}
                                            onCheckedChange={(checked) => form.setData('is_cash', checked === true)}
                                            className="mt-0.5"
                                        />
                                        <div className="flex-1 space-y-1">
                                            <div className="flex items-center gap-1.5">
                                                <Label htmlFor="edit-cash" className="cursor-pointer font-medium text-sm">
                                                    Kas / Bank
                                                </Label>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <button
                                                            type="button"
                                                            className="text-muted-foreground hover:text-foreground inline-flex items-center justify-center rounded-full p-0.5 focus:outline-none"
                                                            aria-label="Penjelasan Kas / Bank"
                                                        >
                                                            <AlertCircle className="h-4 w-4" />
                                                        </button>
                                                    </TooltipTrigger>
                                                    <TooltipContent side="top" className="max-w-xs space-y-1.5 p-3 text-xs leading-relaxed">
                                                        <p className="font-semibold text-foreground">Kas / Bank</p>
                                                        <p className="text-muted-foreground">
                                                            Menandai akun likuid berupa uang tunai fisik atau rekening bank aktif yayasan/sekolah. Menjadi acuan transaksi pada Buku Kas (Cash Book) &amp; Laporan Arus Kas.
                                                        </p>
                                                        <div className="border-t border-border pt-1.5 text-[11px] space-y-1">
                                                            <span className="font-medium text-foreground">Contoh:</span>
                                                            <ul className="list-disc list-inside space-y-0.5 text-muted-foreground">
                                                                <li>Kas Operasional Tata Usaha</li>
                                                                <li>Kas Kecil (Petty Cash) Bendahara</li>
                                                                <li>Bank BSI Giro Operasional</li>
                                                                <li>Bank Mandiri Penampung SPP</li>
                                                            </ul>
                                                        </div>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Akun kas tunai fisik atau rekening bank aktif untuk Buku Kas &amp; Arus Kas.
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {/* 7. Postable */}
                                <div className="flex items-start space-x-3 rounded-lg border p-3 bg-muted/20 hover:bg-muted/30 transition-colors">
                                    <Checkbox
                                        id="edit-postable"
                                        checked={form.data.is_postable}
                                        disabled={isEditingLocked || (editingAccount?.children_count ?? 0) > 0}
                                        onCheckedChange={(checked) => form.setData('is_postable', checked === true)}
                                        className="mt-0.5"
                                    />
                                    <div className="flex-1 space-y-1">
                                        <div className="flex items-center gap-1.5">
                                            <Label htmlFor="edit-postable" className="cursor-pointer font-medium text-sm">
                                                Postable (Dapat Menerima Jurnal)
                                            </Label>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        type="button"
                                                        className="text-muted-foreground hover:text-foreground inline-flex items-center justify-center rounded-full p-0.5 focus:outline-none"
                                                        aria-label="Penjelasan Postable"
                                                    >
                                                        <AlertCircle className="h-4 w-4" />
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent side="top" className="max-w-xs space-y-1.5 p-3 text-xs leading-relaxed">
                                                    <p className="font-semibold text-foreground">Postable (Akun Posting)</p>
                                                    <p className="text-muted-foreground">
                                                        Menentukan apakah akun dapat dipilih langsung saat mencatat transaksi jurnal umum. Jika tidak dicentang (Non-Postable), akun hanya berfungsi sebagai penampung hierarki/kategori (Header).
                                                    </p>
                                                    <div className="border-t border-border pt-1.5 text-[11px] space-y-1">
                                                        <span className="font-medium text-foreground">Contoh:</span>
                                                        <ul className="list-disc list-inside space-y-0.5 text-muted-foreground">
                                                            <li><strong>Postable:</strong> Kas Tunai, Beban ATK &amp; Fotokopi (dapat dijurnal)</li>
                                                            <li><strong>Non-Postable:</strong> ASET LANCAR, BEBAN OPERASIONAL (hanya judul hierarki)</li>
                                                        </ul>
                                                    </div>
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {(editingAccount?.children_count ?? 0) > 0
                                                ? 'Akun ini memiliki sub-akun/anak, sehingga otomatis menjadi Non-Postable (Header).'
                                                : 'Dapat dipilih langsung pada transaksi jurnal harian.'}
                                        </p>
                                    </div>
                                </div>

                                {/* 8. Status Aktif */}
                                <div className="flex items-start space-x-3 rounded-lg border p-3 bg-muted/20 hover:bg-muted/30 transition-colors">
                                    <Checkbox
                                        id="edit-active"
                                        checked={form.data.is_active}
                                        onCheckedChange={(checked) => form.setData('is_active', checked === true)}
                                        className="mt-0.5"
                                    />
                                    <div className="flex-1 space-y-1">
                                        <Label htmlFor="edit-active" className="cursor-pointer font-medium text-sm">
                                            Akun Aktif
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            Akun aktif dapat dipilih pada transaksi dan laporan. Nonaktifkan jika akun tidak lagi digunakan.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>

                        <DialogFooter className="p-6 pt-3 border-t shrink-0 bg-background">
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
