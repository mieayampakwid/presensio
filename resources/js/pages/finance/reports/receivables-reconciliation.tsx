import { Head, router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Download } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ReconciliationRow = {
    account_id: number | null;
    code: string | null;
    name: string | null;
    gl_balance: string;
    subledger_balance: string;
    difference: string;
    is_balanced: boolean;
};

type Mismatch = {
    bill_id: number;
    student_name: string | null;
    title: string;
    account_code: string;
    kind: string;
    expected: string;
    actual: string;
};

type Props = {
    report: {
        as_of: string;
        receivables: ReconciliationRow[];
        prepaid: ReconciliationRow;
        total_difference: string;
        is_balanced: boolean;
        mismatches: Mismatch[];
        mismatch_count: number;
    };
};

const rupiah = (value: string) => Number(value).toLocaleString('id-ID');

export default function ReceivablesReconciliationReportPage({ report }: Props) {
    const [asOf, setAsOf] = useState(report.as_of);

    const applyFilter = () => {
        router.get(
            '/finance/reports/receivables-reconciliation',
            { as_of: asOf },
            { preserveState: true },
        );
    };

    const rows: { label: string; row: ReconciliationRow }[] = [
        ...report.receivables.map((row) => ({
            label: 'Piutang',
            row,
        })),
        { label: 'Pendapatan diterima di muka', row: report.prepaid },
    ];

    return (
        <>
            <Head title="Rekonsiliasi Piutang" />

            <div className="mx-auto max-w-5xl space-y-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title="Rekonsiliasi Piutang"
                        description="Perbandingan saldo buku besar dengan subledger tagihan untuk akun piutang dan pendapatan diterima di muka."
                    />

                    <Button variant="outline" asChild>
                        <a
                            href={`/finance/reports/receivables-reconciliation?format=csv&as_of=${asOf}`}
                            download
                        >
                            <Download className="mr-2 h-4 w-4" /> Ekspor CSV
                        </a>
                    </Button>
                </div>

                <div className="bg-card flex flex-col justify-between gap-4 rounded-lg border p-4 sm:flex-row sm:items-center">
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

                    {report.is_balanced ? (
                        <div className="flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-600 dark:bg-emerald-950/40">
                            <CheckCircle2 className="mr-1.5 h-4 w-4" /> Seimbang
                        </div>
                    ) : (
                        <div className="text-destructive bg-destructive/10 border-destructive/20 flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold">
                            <AlertCircle className="mr-1.5 h-4 w-4" />
                            Selisih: Rp {rupiah(report.total_difference)}
                        </div>
                    )}
                </div>

                <div className="bg-card overflow-hidden rounded-lg border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Akun
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Jenis
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Buku Besar (Rp)
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Subledger (Rp)
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Selisih (Rp)
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map(({ label, row }) => (
                                    <tr
                                        key={`${label}-${row.account_id}`}
                                        className="hover:bg-muted/20 border-t"
                                    >
                                        <td className="px-4 py-2 font-medium">
                                            <span className="font-mono">
                                                {row.code ?? '-'}
                                            </span>{' '}
                                            {row.name}
                                        </td>
                                        <td className="px-4 py-2">{label}</td>
                                        <td className="px-4 py-2 text-right font-mono text-xs">
                                            {rupiah(row.gl_balance)}
                                        </td>
                                        <td className="px-4 py-2 text-right font-mono text-xs">
                                            {rupiah(row.subledger_balance)}
                                        </td>
                                        <td className="px-4 py-2 text-right font-mono text-xs">
                                            {rupiah(row.difference)}
                                        </td>
                                        <td className="px-4 py-2">
                                            <Badge
                                                variant={
                                                    row.is_balanced
                                                        ? 'outline'
                                                        : 'destructive'
                                                }
                                            >
                                                {row.is_balanced
                                                    ? 'Seimbang'
                                                    : 'Selisih'}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {report.mismatch_count > 0 && (
                    <div className="bg-card overflow-hidden rounded-lg border">
                        <div className="border-b px-4 py-3 text-sm font-medium">
                            Tagihan tidak sesuai ({report.mismatch_count}
                            {report.mismatch_count > report.mismatches.length &&
                                `, menampilkan ${report.mismatches.length} pertama`}
                            )
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">
                                            Tagihan
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Siswa
                                        </th>
                                        <th className="px-4 py-3 font-medium">
                                            Akun
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Seharusnya (Rp)
                                        </th>
                                        <th className="px-4 py-3 text-right font-medium">
                                            Buku Besar (Rp)
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.mismatches.map(
                                        (mismatch, index) => (
                                            <tr
                                                key={`${mismatch.bill_id}-${mismatch.kind}-${index}`}
                                                className="hover:bg-muted/20 border-t"
                                            >
                                                <td className="px-4 py-2">
                                                    #{mismatch.bill_id}{' '}
                                                    {mismatch.title}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {mismatch.student_name ??
                                                        '-'}
                                                </td>
                                                <td className="px-4 py-2 font-mono text-xs">
                                                    {mismatch.account_code}
                                                </td>
                                                <td className="px-4 py-2 text-right font-mono text-xs">
                                                    {rupiah(mismatch.expected)}
                                                </td>
                                                <td className="px-4 py-2 text-right font-mono text-xs">
                                                    {rupiah(mismatch.actual)}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

ReceivablesReconciliationReportPage.layout = {
    breadcrumbs: [
        { title: 'Keuangan', href: '/finance/accounts' },
        { title: 'Rekonsiliasi Piutang', href: '#' },
    ],
};
