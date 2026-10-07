import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type LedgerLine = {
    id: number;
    entry_date: string;
    number: string;
    description: string;
    debit: string;
    credit: string;
    running_balance: string;
    student_name: string | null;
    memo: string | null;
};

type AccountLedger = {
    id: number;
    code: string;
    name: string;
    type: string;
    normal_balance: string;
    is_contra: boolean;
    is_cash: boolean;
    opening_balance: string;
    period_debits: string;
    period_credits: string;
    closing_balance: string;
    lines: LedgerLine[];
};

type AccountOption = {
    id: number;
    code: string;
    name: string;
    label: string;
};

type Props = {
    report: {
        accounts: AccountLedger[];
        from: string | null;
        to: string | null;
        account_id: number | null;
    };
    account_options: AccountOption[];
};

export default function GeneralLedgerReportPage({ report, account_options }: Props) {
    const [accountId, setAccountId] = useState<string>(
        report.account_id ? String(report.account_id) : 'all'
    );
    const [from, setFrom] = useState(report.from ?? '');
    const [to, setTo] = useState(report.to ?? '');

    const applyFilter = () => {
        router.get(
            '/finance/reports/general-ledger',
            {
                account_id: accountId === 'all' ? '' : accountId,
                from,
                to,
            },
            { preserveState: true }
        );
    };

    return (
        <>
            <Head title="Buku Besar (General Ledger)" />

            <div className="space-y-6 p-4 max-w-6xl mx-auto">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <Heading
                        title="Buku Besar (General Ledger)"
                        description="Mutasi per akun perkiraan dengan saldo awal dan saldo berjalan."
                    />

                    <Button variant="outline" asChild>
                        <a
                            href={`/finance/reports/general-ledger?format=csv&account_id=${accountId === 'all' ? '' : accountId}&from=${from}&to=${to}`}
                            download
                        >
                            <Download className="h-4 w-4 mr-2" /> Ekspor CSV
                        </a>
                    </Button>
                </div>

                {/* Filter */}
                <div className="grid grid-cols-1 sm:grid-cols-4 items-end gap-4 p-4 rounded-lg border bg-card">
                    <div className="space-y-1">
                        <Label htmlFor="account_select">Pilih Akun</Label>
                        <Select value={accountId} onValueChange={setAccountId}>
                            <SelectTrigger id="account_select">
                                <SelectValue placeholder="Semua Akun" />
                            </SelectTrigger>
                            <SelectContent className="max-h-60">
                                <SelectItem value="all">Semua Akun</SelectItem>
                                {account_options.map((acc) => (
                                    <SelectItem key={acc.id} value={String(acc.id)}>
                                        {acc.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="from">Dari Tanggal</Label>
                        <Input
                            id="from"
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="to">Sampai Tanggal</Label>
                        <Input
                            id="to"
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </div>

                    <Button onClick={applyFilter}>Tampilkan</Button>
                </div>

                {/* Ledgers */}
                {report.accounts.length === 0 ? (
                    <div className="p-8 text-center text-muted-foreground border rounded-lg bg-card">
                        Tidak ada data buku besar untuk akun/periode ini.
                    </div>
                ) : (
                    report.accounts.map((acc) => (
                        <div key={acc.id} className="rounded-lg border bg-card overflow-hidden space-y-2">
                            <div className="p-4 bg-muted/40 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-mono font-bold text-base">{acc.code}</span>
                                        <span className="font-semibold text-base">{acc.name}</span>
                                        <Badge variant="outline">{acc.normal_balance.toUpperCase()}</Badge>
                                        {acc.is_contra && <Badge variant="secondary">Contra</Badge>}
                                    </div>
                                </div>

                                <div className="text-xs font-mono space-x-4">
                                    <span>Saldo Awal: <strong>Rp {Number(acc.opening_balance).toLocaleString('id-ID')}</strong></span>
                                    <span>Saldo Akhir: <strong>Rp {Number(acc.closing_balance).toLocaleString('id-ID')}</strong></span>
                                </div>
                            </div>

                            <div className="overflow-x-auto p-2">
                                <table className="w-full text-xs">
                                    <thead className="bg-muted/30 text-left">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">Tanggal</th>
                                            <th className="px-3 py-2 font-medium">No. Jurnal</th>
                                            <th className="px-3 py-2 font-medium">Keterangan</th>
                                            <th className="px-3 py-2 font-medium text-right">Debit (Rp)</th>
                                            <th className="px-3 py-2 font-medium text-right">Kredit (Rp)</th>
                                            <th className="px-3 py-2 font-medium text-right">Saldo Berjalan (Rp)</th>
                                            <th className="px-3 py-2 font-medium">Siswa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {/* Opening row */}
                                        <tr className="border-t bg-muted/10 italic text-muted-foreground">
                                            <td className="px-3 py-1.5">{report.from ?? '-'}</td>
                                            <td className="px-3 py-1.5">-</td>
                                            <td className="px-3 py-1.5 font-medium">SALDO AWAL</td>
                                            <td className="px-3 py-1.5 text-right">-</td>
                                            <td className="px-3 py-1.5 text-right">-</td>
                                            <td className="px-3 py-1.5 font-mono text-right font-medium">
                                                {Number(acc.opening_balance).toLocaleString('id-ID')}
                                            </td>
                                            <td className="px-3 py-1.5">-</td>
                                        </tr>

                                        {acc.lines.map((line) => (
                                            <tr key={line.id} className="border-t hover:bg-muted/20">
                                                <td className="px-3 py-1.5 whitespace-nowrap">{line.entry_date}</td>
                                                <td className="px-3 py-1.5 font-mono text-primary">{line.number}</td>
                                                <td className="px-3 py-1.5 max-w-xs truncate">{line.description}</td>
                                                <td className="px-3 py-1.5 font-mono text-right">
                                                    {Number(line.debit) > 0 ? Number(line.debit).toLocaleString('id-ID') : '-'}
                                                </td>
                                                <td className="px-3 py-1.5 font-mono text-right">
                                                    {Number(line.credit) > 0 ? Number(line.credit).toLocaleString('id-ID') : '-'}
                                                </td>
                                                <td className="px-3 py-1.5 font-mono text-right font-semibold">
                                                    {Number(line.running_balance).toLocaleString('id-ID')}
                                                </td>
                                                <td className="px-3 py-1.5 text-muted-foreground">{line.student_name ?? '-'}</td>
                                            </tr>
                                        ))}

                                        {/* Closing row */}
                                        <tr className="border-t-2 bg-muted/40 font-semibold font-mono">
                                            <td colSpan={3} className="px-3 py-2 text-right">TOTAL MUTASI & SALDO AKHIR:</td>
                                            <td className="px-3 py-2 text-right text-blue-700">
                                                {Number(acc.period_debits).toLocaleString('id-ID')}
                                            </td>
                                            <td className="px-3 py-2 text-right text-amber-700">
                                                {Number(acc.period_credits).toLocaleString('id-ID')}
                                            </td>
                                            <td className="px-3 py-2 text-right text-emerald-700">
                                                {Number(acc.closing_balance).toLocaleString('id-ID')}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </>
    );
}

GeneralLedgerReportPage.layout = {
    breadcrumbs: [
        { title: 'Keuangan', href: '/finance/accounts' },
        { title: 'Buku Besar', href: '#' },
    ],
};
