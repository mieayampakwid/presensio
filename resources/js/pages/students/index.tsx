import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-react';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import StudentImportController from '@/actions/App/Http/Controllers/Students/StudentImportController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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

type StudentRow = {
    id: number;
    full_name: string;
    student_number: string | null;
    school_class: { id: number; name: string } | null;
};

type Paginator = {
    data: StudentRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    students: Paginator;
    filters: { search: string };
};

export default function StudentsIndex({ students, filters }: Props) {
    const destroy = (student: StudentRow) => {
        router.delete(
            StudentController.destroy({ student: student.id }).url,
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Students" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Students"
                        description="Master data for enrollment, guardians, and RFID cards."
                    />

                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={StudentImportController.create().url}>
                                Import students
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={StudentController.create().url}>
                                Create student
                            </Link>
                        </Button>
                    </div>
                </div>

                <form className="max-w-sm">
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search by name, NIS, or guardian…"
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
                                    Student number
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Class
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {students.data.map((student) => (
                                <tr key={student.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {student.full_name}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {student.student_number ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {student.school_class ? (
                                            <Badge variant="secondary">
                                                {student.school_class.name}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
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
                                                    href={StudentController.edit(
                                                        {
                                                            student:
                                                                student.id,
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
                                                        Delete student
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        Are you sure you want
                                                        to delete "
                                                        {student.full_name}"?
                                                        Their guardian links
                                                        are removed and any
                                                        RFID card returns to
                                                        the spare pool.
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
                                                                        student,
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

                            {students.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        No students found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        Page {students.current_page} of {students.last_page}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!students.prev_page_url}
                        >
                            <Link href={students.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                Previous
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!students.next_page_url}
                        >
                            <Link href={students.next_page_url ?? '#'}>
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

StudentsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Students',
            href: StudentController.index().url,
        },
    ],
};
