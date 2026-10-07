import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
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

type CashLine = {
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

type CashAccountOption = {
    id: number;
    code: string;
    name: string;
    label: string;
};

type Props = {
    report: {
        account: { id: number; code: string; name: string } | null;
        cash_accounts: CashAccountOption[];
        opening_balance: string;
        period_debits: string;
        period_credits: string;
        closing_balance: string;
        lines: CashLine[];
        from: string | null;
        to: string | null;
    };
};

export default function CashBookReportPage({ report }: Props) {
    const [accountId, setAccountId] = useState<string>(
        report.account ? String(report.account.id) : ''
    );
    const [from, setFrom] = useState(report.from ?? '');
    const [to, setTo] = useState(report.to ?? '');

    const applyFilter = () => {
        router.get(
            '/finance/reports/cash-book',
            {
                account_id: accountId,
                from,
                to,
            },
            { preserveState: true }
        );
    };

    return (
        <>
            <Head title="Buku Kas & Bank" />

            <div className="space-y-6 p-4 max-w-5xl mx-auto">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <Heading
                        title="Buku Kas & Bank (Cash Book)"
                        description="Pencatatan arus kas masuk (penerimaan) dan kas keluar (pengeluaran) per akun kas/bank."
                    />

                    {report.account && (
                        <Button variant="outline" asChild>
                            <a
                                href={`/finance/reports/cash-book?format=csv&account_id=${accountId}&from=${from}&to=${to}`}
                                download
                            >
                                <Download className="h-4 w-4 mr-2" /> Ekspor CSV
                            </a>
                        </Button>
                    )}
                </div>

                {/* Filter */}
                <div className="grid grid-cols-1 sm:grid-cols-4 items-end gap-4 p-4 rounded-lg border bg-card">
                    <div className="space-y-1">
                        <Label htmlFor="cash_account">Pilih Rekening Kas/Bank</Label>
                        <Select value={accountId} onValueChange={setAccountId}>
                            <SelectTrigger id="cash_account">
                                <SelectValue placeholder="Pilih rekening" />
                            </SelectTrigger>
                            <SelectContent>
                                {report.cash_accounts.map((acc) => (
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

                {/* Ledger Card */}
                {!report.account ? (
                    <div className="p-8 text-center text-muted-foreground border rounded-lg bg-card">
                        Belum ada akun kas atau bank yang dipilih.
                    </div>
                ) : (
                    <div className="rounded-lg border bg-card overflow-hidden">
                        <div className="p-4 bg-muted/40 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <span className="font-mono font-bold text-base mr-2">{report.account.code}</span>
                                <span className="font-semibold text-base">{report.account.name}</span>
                            </div>

                            <div className="text-xs font-mono space-x-4">
                                <span>Saldo Awal: <strong>Rp {Number(report.opening_balance).toLocaleString('id-ID')}</strong></span>
                                <span>Saldo Akhir: <strong>Rp {Number(report.closing_balance).toLocaleString('id-ID')}</strong></span>
                            </div>
                        </div>

                        <div className="overflow-x-auto p-2">
                            <table className="w-full text-xs">
                                <thead className="bg-muted/30 text-left">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Tanggal</th>
                                        <th className="px-3 py-2 font-medium">No. Jurnal</th>
                                        <th className="px-3 py-2 font-medium">Keterangan</th>
                                        <th className="px-3 py-2 font-medium text-right text-emerald-700">Masuk / Debit (Rp)</th>
                                        <th className="px-3 py-2 font-medium text-right text-rose-700">Keluar / Kredit (Rp)</th>
                                        <th className="px-3 py-2 font-medium text-right font-bold">Saldo Kas (Rp)</th>
                                        <th className="px-3 py-2 font-medium">Memo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr className="border-t bg-muted/10 italic text-muted-foreground">
                                        <td className="px-3 py-1.5">{report.from ?? '-'}</td>
                                        <td className="px-3 py-1.5">-</td>
                                        <td className="px-3 py-1.5 font-medium">SALDO AWAL KAS</td>
                                        <td className="px-3 py-1.5 text-right">-</td>
                                        <td className="px-3 py-1.5 text-right">-</td>
                                        <td className="px-3 py-1.5 font-mono text-right font-medium">
                                            {Number(report.opening_balance).toLocaleString('id-ID')}
                                        </td>
                                        <td className="px-3 py-1.5">-</td>
                                    </tr>

                                    {report.lines.length === 0 ? (
                                        <tr>
                                            <td colSpan={7} className="px-3 py-6 text-center text-muted-foreground italic">
                                                Tidak ada mutasi kas pada periode ini.
                                            </td>
                                        </tr>
                                    ) : (
                                        report.lines.map((line) => (
                                            <tr key={line.id} className="border-t hover:bg-muted/20">
                                                <td className="px-3 py-1.5 whitespace-nowrap">{line.entry_date}</td>
                                                <td className="px-3 py-1.5 font-mono text-primary">{line.number}</td>
                                                <td className="px-3 py-1.5 max-w-xs truncate">{line.description}</td>
                                                <td className="px-3 py-1.5 font-mono text-right text-emerald-700">
                                                    {Number(line.debit) > 0 ? Number(line.debit).toLocaleString('id-ID') : '-'}
                                                </td>
                                                <td className="px-3 py-1.5 font-mono text-right text-rose-700">
                                                    {Number(line.credit) > 0 ? Number(line.credit).toLocaleString('id-ID') : '-'}
                                                </td>
                                                <td className="px-3 py-1.5 font-mono text-right font-semibold">
                                                    {Number(line.running_balance).toLocaleString('id-ID')}
                                                </td>
                                                <td className="px-3 py-1.5 text-muted-foreground">{line.memo ?? '-'}</td>
                                            </tr>
                                        ))
                                    )}

                                    <tr className="border-t-2 bg-muted/40 font-semibold font-mono">
                                        <td colSpan={3} className="px-3 py-2 text-right">TOTAL PENERIMAAN / PENGELUARAN & SALDO AKHIR:</td>
                                        <td className="px-3 py-2 text-right text-emerald-700">
                                            {Number(report.period_debits).toLocaleString('id-ID')}
                                        </td>
                                        <td className="px-3 py-2 text-right text-rose-700">
                                            {Number(report.period_credits).toLocaleString('id-ID')}
                                        </td>
                                        <td className="px-3 py-2 text-right text-emerald-700 font-bold">
                                            {Number(report.closing_balance).toLocaleString('id-ID')}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

CashBookReportPage.layout = {
    breadcrumbs: [
        { title: 'Keuangan', href: '/finance/accounts' },
        { title: 'Buku Kas & Bank', href: '#' },
    ],
};
