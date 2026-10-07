import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Line = {
    id: number;
    account_code: string;
    account_name: string;
    debit: string;
    credit: string;
    student_name: string | null;
    memo: string | null;
};

type Entry = {
    id: number;
    number: string;
    entry_date: string;
    description: string;
    source_type: string;
    source_type_label: string;
    author: string | null;
    total_debit: string;
    total_credit: string;
    lines: Line[];
};

type Props = {
    report: {
        entries: Entry[];
        total_debit: string;
        total_credit: string;
        from: string | null;
        to: string | null;
    };
};

export default function GeneralJournalReportPage({ report }: Props) {
    const [from, setFrom] = useState(report.from ?? '');
    const [to, setTo] = useState(report.to ?? '');

    const applyFilter = () => {
        router.get('/finance/reports/general-journal', { from, to }, { preserveState: true });
    };

    return (
        <>
            <Head title="Laporan Jurnal Umum" />

            <div className="space-y-6 p-4 max-w-6xl mx-auto">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <Heading
                        title="Jurnal Umum (General Journal)"
                        description="Daftar seluruh ayat jurnal posted secara kronologis dengan debit dan kredit."
                    />

                    <Button variant="outline" asChild>
                        <a
                            href={`/finance/reports/general-journal?format=csv&from=${from}&to=${to}`}
                            download
                        >
                            <Download className="h-4 w-4 mr-2" /> Ekspor CSV
                        </a>
                    </Button>
                </div>

                {/* Filter */}
                <div className="flex flex-wrap items-end gap-4 p-4 rounded-lg border bg-card">
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

                {/* Table */}
                <div className="rounded-lg border bg-card overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Tanggal</th>
                                    <th className="px-4 py-3 font-medium">No. Jurnal & Deskripsi</th>
                                    <th className="px-4 py-3 font-medium">Akun</th>
                                    <th className="px-4 py-3 font-medium text-right">Debit (Rp)</th>
                                    <th className="px-4 py-3 font-medium text-right">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {report.entries.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                                            Tidak ada transaksi pada periode ini.
                                        </td>
                                    </tr>
                                ) : (
                                    report.entries.map((entry) => (
                                        <React.Fragment key={entry.id}>
                                            <tr className="border-t bg-muted/10 font-medium">
                                                <td className="px-4 py-2 whitespace-nowrap text-xs text-muted-foreground">
                                                    {entry.entry_date}
                                                </td>
                                                <td colSpan={4} className="px-4 py-2">
                                                    <span className="font-mono text-primary mr-2">
                                                        {entry.number}
                                                    </span>
                                                    <span>{entry.description}</span>
                                                </td>
                                            </tr>
                                            {entry.lines.map((line) => (
                                                <tr key={line.id} className="border-t border-muted/30">
                                                    <td></td>
                                                    <td className="px-4 py-1.5 text-xs text-muted-foreground">
                                                        {line.memo && <span>↳ {line.memo}</span>}
                                                    </td>
                                                    <td className="px-4 py-1.5">
                                                        <span className="font-mono text-xs mr-2 text-muted-foreground">
                                                            {line.account_code}
                                                        </span>
                                                        <span>{line.account_name}</span>
                                                    </td>
                                                    <td className="px-4 py-1.5 font-mono text-right text-xs">
                                                        {Number(line.debit) > 0
                                                            ? Number(line.debit).toLocaleString('id-ID')
                                                            : '-'}
                                                    </td>
                                                    <td className="px-4 py-1.5 font-mono text-right text-xs">
                                                        {Number(line.credit) > 0
                                                            ? Number(line.credit).toLocaleString('id-ID')
                                                            : '-'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </React.Fragment>
                                    ))
                                )}
                            </tbody>
                            <tfoot className="border-t-2 bg-muted/50 font-mono font-bold text-sm">
                                <tr>
                                    <td colSpan={3} className="px-4 py-3 text-right">
                                        TOTAL:
                                    </td>
                                    <td className="px-4 py-3 text-right text-blue-700 dark:text-blue-400">
                                        Rp {Number(report.total_debit).toLocaleString('id-ID')}
                                    </td>
                                    <td className="px-4 py-3 text-right text-amber-700 dark:text-amber-400">
                                        Rp {Number(report.total_credit).toLocaleString('id-ID')}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

GeneralJournalReportPage.layout = {
    breadcrumbs: [
        { title: 'Keuangan', href: '/finance/accounts' },
        { title: 'Laporan Jurnal Umum', href: '#' },
    ],
};
