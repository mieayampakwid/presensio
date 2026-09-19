import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-react';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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

type Paginator = {
    data: ClassRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    classes: Paginator;
    filters: { search: string };
};

export default function ClassesIndex({ classes, filters }: Props) {
    const destroy = (schoolClass: ClassRow) => {
        router.delete(
            SchoolClassController.destroy({ school_class: schoolClass.id }).url,
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Classes" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Classes"
                        description="One active roster per student; homeroom teachers link to master data."
                    />

                    <Button asChild>
                        <Link href={SchoolClassController.create().url}>
                            Create class
                        </Link>
                    </Button>
                </div>

                <form className="max-w-sm">
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search by class or teacher name…"
                    />
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
