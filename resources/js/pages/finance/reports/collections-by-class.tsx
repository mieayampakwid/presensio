import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    report: {
        from: string;
        to: string;
        classes: {
            class_id: number | null;
            name: string;
            paying_students: number;
            collected: string;
            arrears: string;
        }[];
        totals: { paying_students: number; collected: string; arrears: string };
    };
};

const rupiah = (value: string) => Number(value).toLocaleString('id-ID');

export default function CollectionsByClassReportPage({ report }: Props) {
    const [from, setFrom] = useState(report.from);
    const [to, setTo] = useState(report.to);

    const applyFilter = () => {
        router.get(
            '/finance/reports/collections-by-class',
            { from, to },
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title="Penerimaan per Kelas" />

            <div className="mx-auto max-w-4xl space-y-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title="Penerimaan per Kelas"
                        description="Pembayaran terverifikasi pada periode dibandingkan dengan piutang terbuka per kelas pada tanggal akhir."
                    />

                    <Button variant="outline" asChild>
                        <a
                            href={`/finance/reports/collections-by-class?format=csv&from=${from}&to=${to}`}
                            download
                        >
                            <Download className="mr-2 h-4 w-4" /> Ekspor CSV
                        </a>
                    </Button>
                </div>

                <div className="bg-card flex flex-wrap items-end gap-3 rounded-lg border p-4">
                    <div className="space-y-1">
                        <Label htmlFor="from">Dari</Label>
                        <Input
                            id="from"
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="to">Sampai</Label>
                        <Input
                            id="to"
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </div>
                    <Button onClick={applyFilter}>Tampilkan</Button>
                </div>

                <div className="bg-card overflow-hidden rounded-lg border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Kelas
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Siswa Membayar
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Terkumpul (Rp)
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium">
                                        Piutang (Rp)
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {report.classes.map((row) => (
                                    <tr
                                        key={row.class_id ?? 'alumni'}
                                        className="hover:bg-muted/20 border-t"
                                    >
                                        <td className="px-4 py-2">
                                            {row.name}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {row.paying_students}
                                        </td>
                                        <td className="px-4 py-2 text-right font-mono text-xs">
                                            {rupiah(row.collected)}
                                        </td>
                                        <td className="px-4 py-2 text-right font-mono text-xs">
                                            {rupiah(row.arrears)}
                                        </td>
                                    </tr>
                                ))}

                                {report.classes.length === 0 && (
                                    <tr className="border-t">
                                        <td
                                            colSpan={4}
                                            className="text-muted-foreground px-4 py-8 text-center"
                                        >
                                            Tidak ada data pada periode ini.
                                        </td>
                                    </tr>
                                )}

                                <tr className="border-t-2 font-bold">
                                    <td className="px-4 py-3">Total</td>
                                    <td className="px-4 py-3 text-right">
                                        {report.totals.paying_students}
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono text-xs">
                                        {rupiah(report.totals.collected)}
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono text-xs">
                                        {rupiah(report.totals.arrears)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

CollectionsByClassReportPage.layout = {
    breadcrumbs: [
        { title: 'Keuangan', href: '/finance/accounts' },
        { title: 'Penerimaan per Kelas', href: '#' },
    ],
};
