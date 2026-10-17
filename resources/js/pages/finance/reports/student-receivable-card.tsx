import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Download } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type StudentSummary = {
    id: number;
    name: string;
    student_number: string | null;
    class_name: string | null;
};

type Props = {
    report: {
        as_of: string;
        term: string;
        results: StudentSummary[];
        student: StudentSummary | null;
        bills: {
            id: number;
            title: string;
            period_key: string;
            due_date: string;
            net_amount: string;
            status: string;
            closed_on: string | null;
            remaining_amount: string;
            allocations: {
                receipt_number: string | null;
                payment_date: string;
                amount: string;
            }[];
        }[];
        ledger: {
            date: string;
            number: string | null;
            description: string;
            source: string;
            bill_title: string | null;
            debit: string;
            credit: string;
            balance: string;
        }[];
        balance: string;
        subledger: string;
        is_balanced: boolean;
    };
};

const rupiah = (value: string) => Number(value).toLocaleString('id-ID');

export default function StudentReceivableCardPage({ report }: Props) {
    const [term, setTerm] = useState(report.term);
    const [asOf, setAsOf] = useState(report.as_of);
    const { student } = report;

    const go = (params: Record<string, string | number>) => {
        router.get('/finance/reports/student-receivable-card', params, {
            preserveState: true,
        });
    };

    return (
        <>
            <Head title="Kartu Piutang Siswa" />

            <div className="mx-auto max-w-5xl space-y-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title="Kartu Piutang Siswa"
                        description="Riwayat piutang satu siswa dengan saldo berjalan dari buku besar."
                    />

                    {student && (
                        <Button variant="outline" asChild>
                            <a
                                href={`/finance/reports/student-receivable-card?format=csv&student_id=${student.id}&as_of=${report.as_of}`}
                                download
                            >
                                <Download className="mr-2 h-4 w-4" /> Ekspor CSV
                            </a>
                        </Button>
                    )}
                </div>

                <div className="bg-card space-y-3 rounded-lg border p-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="q">Cari siswa (nama / NIS)</Label>
                            <Input
                                id="q"
                                value={term}
                                onChange={(e) => setTerm(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' &&
                                    go({ q: term, as_of: asOf })
                                }
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="as_of">Posisi Per Tanggal</Label>
                            <Input
                                id="as_of"
                                type="date"
                                value={asOf}
                                onChange={(e) => setAsOf(e.target.value)}
                            />
                        </div>
                        <Button
                            onClick={() =>
                                go(
                                    student && term === ''
                                        ? {
                                              student_id: student.id,
                                              as_of: asOf,
                                          }
                                        : { q: term, as_of: asOf },
                                )
                            }
                        >
                            {student && term === '' ? 'Tampilkan' : 'Cari'}
                        </Button>
                    </div>

                    {report.results.length > 0 && (
                        <ul className="divide-y rounded-md border text-sm">
                            {report.results.map((result) => (
                                <li key={result.id}>
                                    <Link
                                        href={`/finance/reports/student-receivable-card?student_id=${result.id}&as_of=${asOf}`}
                                        className="hover:bg-muted/40 flex justify-between px-3 py-2"
                                    >
                                        <span>
                                            {result.name}
                                            {result.student_number && (
                                                <span className="text-muted-foreground ml-2 text-xs">
                                                    {result.student_number}
                                                </span>
                                            )}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {result.class_name ?? '-'}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}

                    {report.term.length >= 2 && report.results.length === 0 && (
                        <p className="text-muted-foreground text-sm">
                            Siswa tidak ditemukan.
                        </p>
                    )}
                </div>

                {student === null ? (
                    <p className="text-muted-foreground text-center text-sm">
                        Cari dan pilih siswa untuk melihat kartu piutang.
                    </p>
                ) : (
                    <>
                        <div className="bg-card flex flex-col justify-between gap-3 rounded-lg border p-4 sm:flex-row sm:items-center">
                            <div>
                                <div className="font-semibold">
                                    {student.name}
                                </div>
                                <div className="text-muted-foreground text-xs">
                                    {student.student_number ?? '-'} ·{' '}
                                    {student.class_name ?? '-'}
                                </div>
                            </div>
                            <div className="text-sm">
                                <div>
                                    Saldo buku besar:{' '}
                                    <span className="font-mono">
                                        Rp {rupiah(report.balance)}
                                    </span>
                                </div>
                                <div>
                                    Saldo subledger:{' '}
                                    <span className="font-mono">
                                        Rp {rupiah(report.subledger)}
                                    </span>
                                </div>
                            </div>
                            {report.is_balanced ? (
                                <div className="flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-600 dark:bg-emerald-950/40">
                                    <CheckCircle2 className="mr-1.5 h-4 w-4" />{' '}
                                    Seimbang
                                </div>
                            ) : (
                                <div className="text-destructive bg-destructive/10 border-destructive/20 flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold">
                                    <AlertCircle className="mr-1.5 h-4 w-4" />
                                    Tidak sesuai dengan subledger
                                </div>
                            )}
                        </div>

                        <div className="bg-card overflow-hidden rounded-lg border">
                            <div className="border-b px-4 py-3 text-sm font-medium">
                                Tagihan
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
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
                                                Netto (Rp)
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Sisa (Rp)
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Status
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Pembayaran
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.bills.map((bill) => (
                                            <tr
                                                key={bill.id}
                                                className="hover:bg-muted/20 border-t align-top"
                                            >
                                                <td className="px-4 py-2">
                                                    {bill.title}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {bill.period_key}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {bill.due_date}
                                                </td>
                                                <td className="px-4 py-2 text-right font-mono text-xs">
                                                    {rupiah(bill.net_amount)}
                                                </td>
                                                <td className="px-4 py-2 text-right font-mono text-xs">
                                                    {rupiah(
                                                        bill.remaining_amount,
                                                    )}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Badge variant="outline">
                                                        {bill.status}
                                                    </Badge>
                                                    {bill.closed_on && (
                                                        <div className="text-muted-foreground mt-1 text-xs">
                                                            {bill.closed_on}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-4 py-2 text-xs">
                                                    {bill.allocations.map(
                                                        (allocation, index) => (
                                                            <div key={index}>
                                                                {allocation.receipt_number ??
                                                                    '-'}{' '}
                                                                ·{' '}
                                                                {
                                                                    allocation.payment_date
                                                                }{' '}
                                                                · Rp{' '}
                                                                {rupiah(
                                                                    allocation.amount,
                                                                )}
                                                            </div>
                                                        ),
                                                    )}
                                                </td>
                                            </tr>
                                        ))}

                                        {report.bills.length === 0 && (
                                            <tr className="border-t">
                                                <td
                                                    colSpan={7}
                                                    className="text-muted-foreground px-4 py-6 text-center"
                                                >
                                                    Belum ada tagihan.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="bg-card overflow-hidden rounded-lg border">
                            <div className="border-b px-4 py-3 text-sm font-medium">
                                Buku piutang
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">
                                                Tanggal
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Keterangan
                                            </th>
                                            <th className="px-4 py-3 font-medium">
                                                Tagihan
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Debit (Rp)
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Kredit (Rp)
                                            </th>
                                            <th className="px-4 py-3 text-right font-medium">
                                                Saldo (Rp)
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.ledger.map((line, index) => (
                                            <tr
                                                key={index}
                                                className="hover:bg-muted/20 border-t"
                                            >
                                                <td className="px-4 py-2">
                                                    {line.date}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <div>{line.source}</div>
                                                    <div className="text-muted-foreground text-xs">
                                                        {line.number}{' '}
                                                        {line.description}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2">
                                                    {line.bill_title ?? '-'}
                                                </td>
                                                <td className="px-4 py-2 text-right font-mono text-xs">
                                                    {rupiah(line.debit)}
                                                </td>
                                                <td className="px-4 py-2 text-right font-mono text-xs">
                                                    {rupiah(line.credit)}
                                                </td>
                                                <td className="px-4 py-2 text-right font-mono text-xs">
                                                    {rupiah(line.balance)}
                                                </td>
                                            </tr>
                                        ))}

                                        {report.ledger.length === 0 && (
                                            <tr className="border-t">
                                                <td
                                                    colSpan={6}
                                                    className="text-muted-foreground px-4 py-6 text-center"
                                                >
                                                    Belum ada transaksi piutang.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

StudentReceivableCardPage.layout = {
    breadcrumbs: [
        { title: 'Keuangan', href: '/finance/accounts' },
        { title: 'Kartu Piutang Siswa', href: '#' },
    ],
};
