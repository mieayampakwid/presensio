import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-react';
import { type ChangeEvent } from 'react';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type ClassRow = {
    id: number;
    name: string;
    teacher?: { id: number; name: string } | null;
};

type YearOption = {
    id: number;
    name: string;
    is_active: boolean;
};

type Paginator = {
    data: ClassRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    classes: Paginator;
    years: YearOption[];
    filters: { search: string; year_id: number | null };
};

export default function ClassesIndex({ classes, years, filters }: Props) {
    const destroy = (schoolClass: ClassRow) => {
        router.delete(
            SchoolClassController.destroy({ school_class: schoolClass.id }).url,
            { preserveScroll: true },
        );
    };

    const submitFilters = (
        event: ChangeEvent<HTMLInputElement | HTMLSelectElement>,
    ) => {
        event.currentTarget.form?.requestSubmit();
    };

    return (
        <>
            <Head title="Classes" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Classes"
                        description="Year-scoped class instances; homeroom teachers link to master data."
                    />

                    <Button asChild>
                        <Link href={SchoolClassController.create().url}>
                            Create class
                        </Link>
                    </Button>
                </div>

                <form className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="year_id">Academic year</Label>
                        <select
                            id="year_id"
                            name="year_id"
                            defaultValue={filters.year_id ?? ''}
                            className="border-input dark:bg-input/30 flex h-9 w-64 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none md:text-sm"
                            onChange={submitFilters}
                        >
                            {years.map((year) => (
                                <option key={year.id} value={year.id}>
                                    {year.name}
                                    {year.is_active ? ' — active' : ''}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Search by class or teacher name…"
                            onChange={submitFilters}
                        />
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Homeroom teacher
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {classes.data.map((schoolClass) => (
                                <tr
                                    key={schoolClass.id}
                                    className="border-t"
                                >
                                    <td className="px-4 py-3 font-medium">
                                        {schoolClass.name}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {schoolClass.teacher?.name ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <Link
                                                    href={SchoolClassController.edit(
                                                        {
                                                            school_class:
                                                                schoolClass.id,
                                                        },
                                                    ).url}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        Edit
                                                    </span>
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
                                                        <span className="sr-only">
                                                            Delete
                                                        </span>
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogTitle>
                                                        Delete class
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        Are you sure you want
                                                        to delete "
                                                        {schoolClass.name}"?
                                                        Classes with enrolled
                                                        students cannot be
                                                        deleted.
                                                    </DialogDescription>
                                                    <DialogFooter className="gap-2">
                                                        <DialogClose asChild>
                                                            <Button variant="secondary">
                                                                Cancel
                                                            </Button>
                                                        </DialogClose>
                                                        <DialogClose asChild>
                                                            <Button
                                                                variant="destructive"
                                                                onClick={() =>
                                                                    destroy(
                                                                        schoolClass,
                                                                    )
                                                                }
                                                            >
                                                                Delete
                                                            </Button>
                                                        </DialogClose>
                                                    </DialogFooter>
                                                </DialogContent>
                                            </Dialog>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {classes.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={3}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        No classes found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        Page {classes.current_page} of {classes.last_page}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!classes.prev_page_url}
                        >
                            <Link href={classes.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                Previous
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!classes.next_page_url}
                        >
                            <Link href={classes.next_page_url ?? '#'}>
                                Next
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

ClassesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Classes',
            href: SchoolClassController.index().url,
        },
    ],
};
