import { Head, Link, router } from '@inertiajs/react';
import {
    Calendar,
    ChevronLeft,
    ChevronRight,
    FileText,
    Paperclip,
    Plus,
    RotateCcw,
} from 'lucide-react';
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

type JournalRow = {
    id: number;
    number: string | null;
    entry_date: string;
    description: string;
    status: 'draft' | 'posted';
    status_label: string;
    source_type: string;
    source_type_label: string;
    reverses_entry_id: number | null;
    total_debit: string;
    total_credit: string;
    lines_count: number;
    has_attachment: boolean;
    author: string | null;
    posted_at: string | null;
};

type Paginator = {
    data: JournalRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
    total: number;
};

type Props = {
    journals: Paginator;
    filters: {
        status: string;
        search: string;
        from: string;
        to: string;
    };
};

export default function JournalsIndex({ journals, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status || 'all');
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    const applyFilters = (newFilters: Partial<typeof filters>) => {
        router.get(
            '/finance/journals',
            {
                search,
                status: status === 'all' ? '' : status,
                from,
                to,
                ...newFilters,
            },
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title="Jurnal Manual" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title="Jurnal Manual (General Journal)"
                        description="Daftar ayat jurnal umum, pencatatan beban operasional, dan saldo awal buku besar."
                    />

                    <Button asChild>
                        <Link href="/finance/journals/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Buat Jurnal Baru
                        </Link>
                    </Button>
                </div>

                <div className="bg-card grid grid-cols-1 gap-4 rounded-lg border p-4 sm:grid-cols-4">
                    <div className="space-y-1">
                        <Label htmlFor="search">Pencarian</Label>
                        <Input
                            id="search"
                            placeholder="Nomor atau deskripsi..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) =>
                                e.key === 'Enter' && applyFilters({ search })
                            }
                        />
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="filter-status">Status</Label>
                        <Select
                            value={status}
                            onValueChange={(val) => {
                                setStatus(val);
                                applyFilters({
                                    status: val === 'all' ? '' : val,
                                });
                            }}
                        >
                            <SelectTrigger id="filter-status">
                                <SelectValue placeholder="Semua status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Semua Status
                                </SelectItem>
                                <SelectItem value="posted">
                                    Diposting
                                </SelectItem>
                                <SelectItem value="draft">Draf</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="from">Dari Tanggal</Label>
                        <Input
                            id="from"
                            type="date"
                            value={from}
                            onChange={(e) => {
                                setFrom(e.target.value);
                                applyFilters({ from: e.target.value });
                            }}
                        />
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="to">Sampai Tanggal</Label>
                        <Input
                            id="to"
                            type="date"
                            value={to}
                            onChange={(e) => {
                                setTo(e.target.value);
                                applyFilters({ to: e.target.value });
                            }}
                        />
                    </div>
                </div>

                <div className="bg-card overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Nomor Jurnal
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Tanggal
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Deskripsi
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Sumber
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Total Nominal
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {journals.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        Tidak ada ayat jurnal ditemukan.
                                    </td>
                                </tr>
                            ) : (
                                journals.data.map((journal) => (
                                    <tr
                                        key={journal.id}
                                        className="hover:bg-muted/20 border-t"
                                    >
                                        <td className="px-4 py-3 font-mono font-medium">
                                            {journal.number ? (
                                                <Link
                                                    href={`/finance/journals/${journal.id}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {journal.number}
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground italic">
                                                    (Draf)
                                                </span>
                                            )}
                                            {journal.reverses_entry_id && (
                                                <Badge
                                                    variant="outline"
                                                    className="ml-2 border-amber-300 text-xs text-amber-600"
                                                >
                                                    Pembalik
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {journal.entry_date}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="max-w-md truncate font-medium">
                                                {journal.description}
                                            </div>
                                            <div className="text-muted-foreground mt-0.5 flex items-center gap-2 text-xs">
                                                <span>
                                                    {journal.lines_count} baris
                                                </span>
                                                {journal.author && (
                                                    <span>
                                                        Oleh: {journal.author}
                                                    </span>
                                                )}
                                                {journal.has_attachment && (
                                                    <span className="text-primary flex items-center">
                                                        <Paperclip className="mr-0.5 h-3 w-3" />{' '}
                                                        Lampiran
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-xs">
                                            <Badge variant="outline">
                                                {journal.source_type_label}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs">
                                            Rp{' '}
                                            {Number(
                                                journal.total_debit,
                                            ).toLocaleString('id-ID')}
                                        </td>
                                        <td className="px-4 py-3">
                                            {journal.status === 'posted' ? (
                                                <Badge
                                                    variant="default"
                                                    className="bg-emerald-600"
                                                >
                                                    Diposting
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    Draf
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={`/finance/journals/${journal.id}`}
                                                >
                                                    Detail
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>

                    {/* Pagination */}
                    <div className="flex items-center justify-between border-t p-4">
                        <span className="text-muted-foreground text-xs">
                            Menampilkan halaman {journals.current_page} dari{' '}
                            {journals.last_page} ({journals.total} total)
                        </span>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                disabled={!journals.prev_page_url}
                            >
                                <Link href={journals.prev_page_url ?? '#'}>
                                    <ChevronLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                disabled={!journals.next_page_url}
                            >
                                <Link href={journals.next_page_url ?? '#'}>
                                    <ChevronRight className="h-4 w-4" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

JournalsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Keuangan',
            href: '/finance/accounts',
        },
        {
            title: 'Jurnal Manual',
            href: '/finance/journals',
        },
    ],
};
