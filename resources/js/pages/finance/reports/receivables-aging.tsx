import { Head, Link, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Buckets = {
    current: string;
    d1_30: string;
    d31_60: string;
    d61_90: string;
    d90_plus: string;
    total: string;
};

type StudentRow = Buckets & {
    student_id: number;
    student_name: string;
    student_number: string | null;
};

type NeedsReviewRow = {
    student_id: number;
    student_name: string;
    student_number: string | null;
    bill_id: number;
    title: string;
    due_date: string;
    paid_amount: string;
    remaining_amount: string;
};

type Props = {
    report: {
        as_of: string;
        class_id: number | null;
        class_options: { id: number; name: string }[];
        classes: {
            class_id: number | null;
            name: string;
            students: StudentRow[];
            subtotals: Buckets;
        }[];
        totals: Buckets;
        needs_review: NeedsReviewRow[];
    };
};

const rupiah = (value: string) => Number(value).toLocaleString('id-ID');

const columns: { key: keyof Buckets; label: string }[] = [
    { key: 'current', label: 'Belum jatuh tempo' },
    { key: 'd1_30', label: '1-30 hari' },
    { key: 'd31_60', label: '31-60 hari' },
    { key: 'd61_90', label: '61-90 hari' },
    { key: 'd90_plus', label: '> 90 hari' },
    { key: 'total', label: 'Total' },
];

const selectClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm';

export default function ReceivablesAgingReportPage({ report }: Props) {
    const [asOf, setAsOf] = useState(report.as_of);
    const [classId, setClassId] = useState(
        report.class_id === null ? '' : String(report.class_id),
    );

    const applyFilter = () => {
        router.get(
            '/finance/reports/receivables-aging',
            { as_of: asOf, class_id: classId || undefined },
            { preserveState: true },
        );
    };

    const csvQuery = new URLSearchParams({ format: 'csv', as_of: asOf });

    if (classId) {
        csvQuery.set('class_id', classId);
    }

    const cells = (row: Buckets, bold = false) =>
        columns.map(({ key }) => (
            <td
                key={key}
                className={`px-4 py-2 text-right font-mono text-xs ${bold ? 'font-semibold' : ''}`}
            >
                {rupiah(row[key])}
            </td>
        ));

    return (
        <>
            <Head title="Umur Piutang" />

            <div className="mx-auto max-w-6xl space-y-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title="Umur Piutang"
                        description="Sisa tagihan terbuka menurut lama keterlambatan, dikelompokkan per kelas pada tanggal posisi."
                    />

                    <Button variant="outline" asChild>
                        <a
                            href={`/finance/reports/receivables-aging?${csvQuery.toString()}`}
                            download
                        >
                            <Download className="mr-2 h-4 w-4" /> Ekspor CSV
                        </a>
                    </Button>
                </div>

                <div className="bg-card flex flex-wrap items-end gap-3 rounded-lg border p-4">
                    <div className="space-y-1">
                        <Label htmlFor="as_of">Posisi Per Tanggal</Label>
                        <Input
                            id="as_of"
                            type="date"
                            value={asOf}
                            onChange={(e) => setAsOf(e.target.value)}
                        />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="class_id">Kelas</Label>
                        <select
                            id="class_id"
                            className={selectClass}
                            value={classId}
                            onChange={(e) => setClassId(e.target.value)}
                        >
                            <option value="">Semua kelas</option>
                            {report.class_options.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button onClick={applyFilter}>Tampilkan</Button>
                </div>

                <div className="bg-card overflow-hidden rounded-lg border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Kelas / Siswa
                                    </th>
                                    {columns.map(({ key, label }) => (
                                        <th
                                            key={key}
                                            className="px-4 py-3 text-right font-medium"
                                        >
                                            {label} (Rp)
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {report.classes.map((group) => [
                                    <tr
                                        key={`class-${group.class_id ?? 'alumni'}`}
                                        className="bg-muted/30 border-t font-semibold"
                                    >
                                        <td className="px-4 py-2">
                                            {group.name}
                                        </td>
                                        {cells(group.subtotals, true)}
                                    </tr>,
                                    ...group.students.map((student) => (
                                        <tr
                                            key={`${group.class_id}-${student.student_id}`}
                                            className="hover:bg-muted/20 border-t"
                                        >
                                            <td className="py-2 pr-4 pl-8">
                                                <Link
                                                    href={`/finance/reports/student-receivable-card?student_id=${student.student_id}&as_of=${report.as_of}`}
                                                    className="hover:underline"
                                                >
                                                    {student.student_name}
                                                </Link>
                                                {student.student_number && (
                                                    <span className="text-muted-foreground ml-2 text-xs">
                                                        {student.student_number}
                                                    </span>
                                                )}
                                            </td>
                                            {cells(student)}
                                        </tr>
                                    )),
                                ])}

                                {report.classes.length === 0 && (
                                    <tr className="border-t">
                                        <td
                                            colSpan={7}
                                            className="text-muted-foreground px-4 py-8 text-center"
                                        >
                                            Tidak ada piutang terbuka.
                                        </td>
                                    </tr>
                                )}

                                <tr className="border-t-2 font-bold">
                                    <td className="px-4 py-3">Total</td>
                                    {cells(report.totals, true)}
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="bg-card overflow-hidden rounded-lg border">
                    <div className="border-b px-4 py-3 text-sm font-medium">
                        Perlu ditinjau ({report.needs_review.length})
                        <p className="text-muted-foreground text-xs font-normal">
                            Siswa tanpa kelas aktif yang masih memiliki tagihan
                            dibayar sebagian (keadaan saat ini).
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Siswa
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Tagihan
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Jatuh Tempo
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Dibayar (Rp)
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Sisa (Rp)
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {report.needs_review.map((row) => (
                                    <tr
                                        key={row.bill_id}
                                        className="hover:bg-muted/20 border-t"
                                    >
                                        <td className="px-4 py-2">
                                            <Link
                                                href={`/finance/reports/student-receivable-card?student_id=${row.student_id}`}
                                                className="hover:underline"
                                            >
                                                {row.student_name}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-2">
                                            {row.title}
                                        </td>
                                        <td className="px-4 py-2">
                                            {row.due_date}
                                        </td>
                                        <td className="px-4 py-2 text-right font-mono text-xs">
                                            {rupiah(row.paid_amount)}
                                        </td>
                                        <td className="px-4 py-2 text-right font-mono text-xs">
                                            {rupiah(row.remaining_amount)}
                                        </td>
                                    </tr>
                                ))}

                                {report.needs_review.length === 0 && (
                                    <tr className="border-t">
                                        <td
                                            colSpan={5}
                                            className="text-muted-foreground px-4 py-6 text-center"
                                        >
                                            Tidak ada yang perlu ditinjau.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

ReceivablesAgingReportPage.layout = {
    breadcrumbs: [
        { title: 'Keuangan', href: '/finance/accounts' },
        { title: 'Umur Piutang', href: '#' },
    ],
};
