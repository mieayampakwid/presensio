import { Head, router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Download } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type AccountRow = {
    id: number;
    code: string;
    name: string;
    type: string;
    normal_balance: string;
    sum_debit: string;
    sum_credit: string;
    debit_balance: string;
    credit_balance: string;
};

type Props = {
    report: {
        accounts: AccountRow[];
        total_debit: string;
        total_credit: string;
        difference: string;
        is_balanced: boolean;
        as_of: string | null;
    };
};

export default function TrialBalanceReportPage({ report }: Props) {
    const [asOf, setAsOf] = useState(report.as_of ?? '');

    const applyFilter = () => {
        router.get('/finance/reports/trial-balance', { as_of: asOf }, { preserveState: true });
    };

    return (
        <>
            <Head title="Neraca Saldo (Trial Balance)" />

            <div className="space-y-6 p-4 max-w-5xl mx-auto">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <Heading
                        title="Neraca Saldo (Trial Balance)"
                        description="Daftar saldo debit dan kredit seluruh akun postable untuk pembuktian keseimbangan buku besar."
                    />

                    <Button variant="outline" asChild>
                        <a href={`/finance/reports/trial-balance?format=csv&as_of=${asOf}`} download>
                            <Download className="h-4 w-4 mr-2" /> Ekspor CSV
                        </a>
                    </Button>
                </div>

                {/* Filter and Status */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-lg border bg-card">
                    <div className="flex items-end gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="as_of">Posisi Per Tanggal</Label>
                            <Input
                                id="as_of"
                                type="date"
                                value={asOf}
                                onChange={(e) => setAsOf(e.target.value)}
                            />
                        </div>
                        <Button onClick={applyFilter}>Tampilkan</Button>
                    </div>

                    <div>
                        {report.is_balanced ? (
                            <div className="flex items-center text-xs text-emerald-600 font-semibold bg-emerald-50 dark:bg-emerald-950/40 px-3 py-1.5 rounded-full border border-emerald-200">
                                <CheckCircle2 className="h-4 w-4 mr-1.5" /> Neraca Saldo Seimbang (Balanced)
                            </div>
                        ) : (
                            <div className="flex items-center text-xs text-destructive font-semibold bg-destructive/10 px-3 py-1.5 rounded-full border border-destructive/20">
                                <AlertCircle className="h-4 w-4 mr-1.5" />
                                Selisih: Rp {Number(report.difference).toLocaleString('id-ID')}
                            </div>
                        )}
                    </div>
                </div>

                {/* Table */}
                <div className="rounded-lg border bg-card overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Kode Akun</th>
                                    <th className="px-4 py-3 font-medium">Nama Akun</th>
                                    <th className="px-4 py-3 font-medium">Klasifikasi</th>
                                    <th className="px-4 py-3 font-medium">Saldo Normal</th>
                                    <th className="px-4 py-3 font-medium text-right">Saldo Debit (Rp)</th>
                                    <th className="px-4 py-3 font-medium text-right">Saldo Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {report.accounts.map((acc) => (
                                    <tr key={acc.id} className="border-t hover:bg-muted/20">
                                        <td className="px-4 py-2 font-mono font-medium">{acc.code}</td>
                                        <td className="px-4 py-2 font-medium">{acc.name}</td>
                                        <td className="px-4 py-2">
                                            <Badge variant="outline" className="text-xs uppercase">
                                                {acc.type}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-2 text-xs font-mono uppercase">
                                            {acc.normal_balance}
                                        </td>
                                        <td className="px-4 py-2 font-mono text-right text-xs">
                                            {Number(acc.debit_balance) > 0
                                                ? Number(acc.debit_balance).toLocaleString('id-ID')
                                                : '-'}
                                        </td>
                                        <td className="px-4 py-2 font-mono text-right text-xs">
                                            {Number(acc.credit_balance) > 0
                                                ? Number(acc.credit_balance).toLocaleString('id-ID')
                                                : '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t-2 bg-muted/60 font-mono font-bold text-sm">
                                <tr>
                                    <td colSpan={4} className="px-4 py-3 text-right">
                                        TOTAL KESEIMBANGAN:
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

TrialBalanceReportPage.layout = {
    breadcrumbs: [
        { title: 'Keuangan', href: '/finance/accounts' },
        { title: 'Neraca Saldo', href: '#' },
    ],
};
