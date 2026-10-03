import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-react';
import { type ChangeEvent } from 'react';
import SubjectController from '@/actions/App/Http/Controllers/Subjects/SubjectController';
import Heading from '@/components/heading';
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
import { Label } from '@/components/ui/label';

type SubjectRow = {
    id: number;
    name: string;
    code: string;
    group: string;
    sort_order: number;
    description: string | null;
    is_active: boolean;
};

type GroupOption = {
    value: string;
    label: string;
};

type Paginator = {
    data: SubjectRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    subjects: Paginator;
    filters: { search: string };
    groups: GroupOption[];
};

export default function SubjectsIndex({ subjects, filters, groups }: Props) {
    const destroy = (subject: SubjectRow) => {
        router.delete(
            SubjectController.destroy.url({ subject: subject.id }),
            { preserveScroll: true },
        );
    };

    const submitFilters = (
        event: ChangeEvent<HTMLInputElement>,
    ) => {
        event.currentTarget.form?.requestSubmit();
    };

    const groupMap: Record<string, string> = Object.fromEntries(
        groups.map((g) => [g.value, g.label]),
    );
    return (
        <>
            <Head title="Subjects" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Subjects"
                        description="Katalog mata pelajaran sekolah untuk kurikulum, penugasan guru, dan rapor."
                    />

                    <Button asChild>
                        <Link href={SubjectController.create().url}>
                            Create Subject
                        </Link>
                    </Button>
                </div>

                <form className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search</Label>
                        <Input
                            id="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Cari kode atau nama..."
                            onChange={submitFilters}
                            className="w-64"
                        />
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">Code</th>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Group</th>
                                <th className="px-4 py-3 font-medium">Order</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {subjects.data.map((subject) => (
                                <tr key={subject.id} className="border-t">
                                    <td className="px-4 py-3 font-mono font-medium">
                                        {subject.code}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {subject.name}
                                        </div>
                                        {subject.description && (
                                            <div className="text-muted-foreground text-xs">
                                                {subject.description}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {groupMap[subject.group] ?? subject.group}
                                    </td>
                                    <td className="px-4 py-3">
                                        {subject.sort_order}
                                    </td>
                                    <td className="px-4 py-3">
                                        {subject.is_active ? (
                                            <span className="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-950/50 dark:text-green-400">
                                                Active
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600 ring-1 ring-inset ring-neutral-500/20 dark:bg-neutral-800 dark:text-neutral-400">
                                                Inactive
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
                                                    href={
                                                        SubjectController.edit.url({
                                                            subject: subject.id,
                                                        })
                                                    }
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
                                                        className="text-destructive hover:text-destructive"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                        <span className="sr-only">
                                                            Delete
                                                        </span>
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent>
                                                    <DialogTitle>
                                                        Delete Subject
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        Are you sure you want to delete {subject.name} ({subject.code})? This action cannot be undone.
                                                    </DialogDescription>
                                                    <DialogFooter className="gap-2">
                                                        <DialogClose asChild>
                                                            <Button variant="outline">
                                                                Cancel
                                                            </Button>
                                                        </DialogClose>
                                                        <DialogClose asChild>
                                                            <Button
                                                                variant="destructive"
                                                                onClick={() =>
                                                                    destroy(subject)
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

                            {subjects.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={6}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        No subjects found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        Page {subjects.current_page} of {subjects.last_page}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!subjects.prev_page_url}
                        >
                            <Link href={subjects.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                Previous
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!subjects.next_page_url}
                        >
                            <Link href={subjects.next_page_url ?? '#'}>
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

SubjectsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Subjects',
            href: SubjectController.index().url,
        },
    ],
};
