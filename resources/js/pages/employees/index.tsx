import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState, type ChangeEvent } from 'react';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';

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
    shift_name: string | null;
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
    const [deleting, setDeleting] = useState<EmployeeRow | null>(null);
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('employees.index.title'),
                href: EmployeeController.index().url,
            },
        ],
    });

    const destroy = (employee: EmployeeRow) => {
        router.delete(
            EmployeeController.destroy({ employee: employee.id }).url,
            {
                preserveScroll: true,
            },
        );
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
            <Head title={t('employees.index.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title={t('employees.index.title')}
                        description={t('employees.index.description')}
                    />

                    <Button asChild>
                        <Link href={EmployeeController.create().url}>
                            <Plus className="mr-2 h-4 w-4" />
                            {t('employees.index.create')}
                        </Link>
                    </Button>
                </div>

                <div className="max-w-sm">
                    <Input
                        defaultValue={filters.search}
                        placeholder={t('employees.index.search_placeholder')}
                        onChange={submitFilters}
                    />
                </div>

                <div className="rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('employees.index.columns.name')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('employees.index.columns.contact')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('employees.index.columns.position')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('employees.index.columns.account')}
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    {t('employees.index.columns.actions')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {employees.data.map((emp) => (
                                <tr key={emp.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        <div className="flex flex-col">
                                            <span className="font-semibold">
                                                {emp.name}
                                            </span>
                                            <span className="text-muted-foreground text-xs">
                                                {emp.employee_number ?? '—'}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {emp.phone_number ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col items-start gap-1">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <span>
                                                    {emp.position ?? '—'}
                                                </span>
                                                {emp.is_teacher && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-xs"
                                                    >
                                                        {t(
                                                            'employees.index.teacher',
                                                        )}
                                                    </Badge>
                                                )}
                                                {!emp.is_active && (
                                                    <Badge
                                                        variant="destructive"
                                                        className="text-xs"
                                                    >
                                                        {t(
                                                            'employees.index.inactive',
                                                        )}
                                                    </Badge>
                                                )}
                                            </div>
                                            <span className="text-muted-foreground text-xs">
                                                {t(
                                                    `employees.employment_type.${emp.employment_type}`,
                                                )}
                                            </span>
                                            {emp.shift_name && (
                                                <span className="text-muted-foreground text-xs">
                                                    {t(
                                                        'employees.index.shift',
                                                        {
                                                            name: emp.shift_name,
                                                        },
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {emp.user ? (
                                            <span className="text-foreground font-mono text-xs">
                                                @{emp.user.username}
                                            </span>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <Link
                                                    href={
                                                        EmployeeController.edit(
                                                            {
                                                                employee:
                                                                    emp.id,
                                                            },
                                                        ).url
                                                    }
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        {t('common.edit')}
                                                    </span>
                                                </Link>
                                            </Button>

                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                onClick={() => setDeleting(emp)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                                <span className="sr-only">
                                                    {t('common.delete')}
                                                </span>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {employees.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={5}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('employees.index.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {t('common.page_of', {
                            current: employees.current_page,
                            last: employees.last_page,
                        })}
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
                                {t('common.previous')}
                            </Link>
                        </Button>

                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!employees.next_page_url}
                        >
                            <Link href={employees.next_page_url ?? '#'}>
                                {t('common.next')}
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        {t('employees.index.delete_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('employees.index.delete_description', {
                            name: deleting?.name ?? '',
                        })}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setDeleting(null)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (deleting) {
                                    destroy(deleting);
                                    setDeleting(null);
                                }
                            }}
                        >
                            {t('common.delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
