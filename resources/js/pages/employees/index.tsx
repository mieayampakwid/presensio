import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { ChangeEvent } from 'react';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type EmployeeRow = {
    id: number;
    name: string;
    employee_number: string | null;
    phone_number: string | null;
    employment_type: string;
    employment_type_label: string;
    position: string | null;
    working_days: number[] | null;
    is_active: boolean;
    is_teacher: boolean;
    user: { id: number; username: string } | null;
};

type Paginator = {
    data: EmployeeRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    employees: Paginator;
    filters: { search: string };
};

export default function EmployeesIndex({ employees, filters }: Props) {
    const destroy = (employee: EmployeeRow) => {
        router.delete(EmployeeController.destroy({ employee: employee.id }).url, {
            preserveScroll: true,
        });
    };

    const submitFilters = (e: ChangeEvent<HTMLInputElement>) => {
        const search = e.currentTarget.value;
        router.get(
            EmployeeController.index().url,
            { search },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Pegawai & Guru" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <Heading
                        title="Pegawai & Guru"
                        description="Data induk pendidik (guru) dan tenaga kependidikan (tendik)."
                    />

                    <Button asChild>
                        <Link href={EmployeeController.create().url}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Pegawai
                        </Link>
                    </Button>
                </div>

                <div className="max-w-sm">
                    <Input
                        defaultValue={filters.search}
                        placeholder="Cari berdasarkan nama, NIP, atau jabatan…"
                        onChange={submitFilters}
                    />
                </div>

                <div className="rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Nama / NIP</th>
                                <th className="px-4 py-3 text-left font-medium">Kontak</th>
                                <th className="px-4 py-3 text-left font-medium">Jabatan & Status</th>
                                <th className="px-4 py-3 text-left font-medium">Akun Login</th>
                                <th className="px-4 py-3 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {employees.data.map((emp) => (
                                <tr key={emp.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        <div className="flex flex-col">
                                            <span className="font-semibold">{emp.name}</span>
                                            <span className="text-xs text-muted-foreground">
                                                {emp.employee_number ?? '—'}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {emp.phone_number ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col gap-1 items-start">
                                            <div className="flex items-center gap-1.5 flex-wrap">
                                                <span>{emp.position ?? '—'}</span>
                                                {emp.is_teacher && (
                                                    <Badge variant="secondary" className="text-xs">
                                                        Guru
                                                    </Badge>
                                                )}
                                                {!emp.is_active && (
                                                    <Badge variant="destructive" className="text-xs">
                                                        Non-Aktif
                                                    </Badge>
                                                )}
                                            </div>
                                            <span className="text-xs text-muted-foreground">
                                                {emp.employment_type_label}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {emp.user ? (
                                            <span className="font-mono text-xs text-foreground">
                                                @{emp.user.username}
                                            </span>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="icon" asChild>
                                                <Link
                                                    href={
                                                        EmployeeController.edit({
                                                            employee: emp.id,
                                                        }).url
                                                    }
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                    <span className="sr-only">Edit</span>
                                                </Link>
                                            </Button>

                                            <Dialog>
                                                <DialogTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                        <span className="sr-only">Hapus</span>
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogTitle>Hapus Pegawai</DialogTitle>
                                                    <DialogDescription>
                                                        Apakah Anda yakin ingin menghapus "{emp.name}"?
                                                        Pegawai yang menjadi wali kelas, mengajar mata pelajaran,
                                                        atau terhubung ke akun login tidak dapat dihapus.
                                                    </DialogDescription>
                                                    <DialogFooter className="gap-2">
                                                        <DialogClose asChild>
                                                            <Button variant="secondary">Batal</Button>
                                                        </DialogClose>
                                                        <DialogClose asChild>
                                                            <Button
                                                                variant="destructive"
                                                                onClick={() => destroy(emp)}
                                                            >
                                                                Hapus
                                                            </Button>
                                                        </DialogClose>
                                                    </DialogFooter>
                                                </DialogContent>
                                            </Dialog>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {employees.data.length === 0 && (
                                <tr className="border-t">
                                    <td colSpan={5} className="text-muted-foreground px-4 py-8 text-center">
                                        Tidak ada data pegawai.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        Halaman {employees.current_page} dari {employees.last_page}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!employees.prev_page_url}
                        >
                            <Link href={employees.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                Sebelumnya
                            </Link>
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!employees.next_page_url}
                        >
                            <Link href={employees.next_page_url ?? '#'}>
                                Berikutnya
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

EmployeesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Pegawai',
            href: EmployeeController.index().url,
        },
    ],
};
