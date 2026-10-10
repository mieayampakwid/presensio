import { Form, Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-react';
import { useState, type ChangeEvent } from 'react';
import SubjectController from '@/actions/App/Http/Controllers/Subjects/SubjectController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';

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
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingSubject, setEditingSubject] = useState<SubjectRow | null>(
        null,
    );
    const [deleting, setDeleting] = useState<SubjectRow | null>(null);
    const destroy = (subject: SubjectRow) => {
        router.delete(SubjectController.destroy.url({ subject: subject.id }), {
            preserveScroll: true,
        });
    };

    const submitFilters = (event: ChangeEvent<HTMLInputElement>) => {
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

                    <Button onClick={() => setIsCreateOpen(true)}>
                        Create Subject
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
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
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
                                        {groupMap[subject.group] ??
                                            subject.group}
                                    </td>
                                    <td className="px-4 py-3">
                                        {subject.sort_order}
                                    </td>
                                    <td className="px-4 py-3">
                                        {subject.is_active ? (
                                            <span className="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-green-600/20 ring-inset dark:bg-green-950/50 dark:text-green-400">
                                                Active
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600 ring-1 ring-neutral-500/20 ring-inset dark:bg-neutral-800 dark:text-neutral-400">
                                                Inactive
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setEditingSubject(subject)
                                                }
                                            >
                                                <Pencil className="h-4 w-4" />
                                                <span className="sr-only">
                                                    Edit
                                                </span>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() =>
                                                    setDeleting(subject)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                                <span className="sr-only">
                                                    Delete
                                                </span>
                                            </Button>
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
            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>Delete Subject</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete {deleting?.name} (
                        {deleting?.code})? This action cannot be undone.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(null)}
                        >
                            Cancel
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
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Sheet open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <SheetContent side="right" className="sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Create Subject</SheetTitle>
                        <SheetDescription>
                            Tambah mata pelajaran baru ke katalog kurikulum
                            sekolah.
                        </SheetDescription>
                    </SheetHeader>

                    <Form
                        {...SubjectController.store.form()}
                        onSuccess={() => setIsCreateOpen(false)}
                        className="flex flex-1 flex-col justify-between space-y-4 overflow-y-auto px-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-code">
                                                Code
                                            </Label>
                                            <Input
                                                id="create-code"
                                                name="code"
                                                required
                                                autoFocus
                                                autoComplete="off"
                                                placeholder="e.g. MAT"
                                                maxLength={20}
                                                className="uppercase"
                                            />
                                            <InputError message={errors.code} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="create-name">
                                                Name
                                            </Label>
                                            <Input
                                                id="create-name"
                                                name="name"
                                                required
                                                autoComplete="off"
                                                placeholder="e.g. Matematika"
                                                maxLength={255}
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-group">
                                                Group
                                            </Label>
                                            <select
                                                id="create-group"
                                                name="group"
                                                defaultValue={
                                                    groups[0]?.value ??
                                                    'muatan_nasional'
                                                }
                                                required
                                                className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                            >
                                                {groups.map((group) => (
                                                    <option
                                                        key={group.value}
                                                        value={group.value}
                                                    >
                                                        {group.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={errors.group}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="create-sort_order">
                                                Sort Order
                                            </Label>
                                            <Input
                                                id="create-sort_order"
                                                name="sort_order"
                                                type="number"
                                                min={0}
                                                defaultValue={0}
                                                required
                                            />
                                            <InputError
                                                message={errors.sort_order}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="create-description">
                                            Description (optional)
                                        </Label>
                                        <textarea
                                            id="create-description"
                                            name="description"
                                            rows={3}
                                            className="border-input placeholder:text-muted-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                            placeholder="Deskripsi singkat mata pelajaran..."
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <input
                                            type="hidden"
                                            name="is_active"
                                            value="0"
                                        />
                                        <input
                                            type="checkbox"
                                            id="create-is_active"
                                            name="is_active"
                                            value="1"
                                            defaultChecked={true}
                                            className="text-primary focus:ring-primary h-4 w-4 rounded border-gray-300"
                                        />
                                        <Label
                                            htmlFor="create-is_active"
                                            className="cursor-pointer"
                                        >
                                            Active (Mata pelajaran aktif)
                                        </Label>
                                        <InputError
                                            message={errors.is_active}
                                        />
                                    </div>
                                </div>

                                <SheetFooter className="flex flex-row justify-end gap-2 border-t px-0 pt-4">
                                    <Button
                                        variant="outline"
                                        type="button"
                                        onClick={() => setIsCreateOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && (
                                            <Spinner className="mr-2" />
                                        )}
                                        Create Subject
                                    </Button>
                                </SheetFooter>
                            </>
                        )}
                    </Form>
                </SheetContent>
            </Sheet>

            <Sheet
                open={editingSubject !== null}
                onOpenChange={(open) => !open && setEditingSubject(null)}
            >
                <SheetContent side="right" className="sm:max-w-lg">
                    {editingSubject && (
                        <>
                            <SheetHeader>
                                <SheetTitle>
                                    Edit Subject — {editingSubject.name}
                                </SheetTitle>
                                <SheetDescription>
                                    Perbarui data mata pelajaran.
                                </SheetDescription>
                            </SheetHeader>

                            <Form
                                key={editingSubject.id}
                                {...SubjectController.update.form({
                                    subject: editingSubject.id,
                                })}
                                onSuccess={() => setEditingSubject(null)}
                                className="flex flex-1 flex-col justify-between space-y-4 overflow-y-auto px-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="space-y-4">
                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="edit-code">
                                                        Code
                                                    </Label>
                                                    <Input
                                                        id="edit-code"
                                                        name="code"
                                                        defaultValue={
                                                            editingSubject.code
                                                        }
                                                        required
                                                        autoComplete="off"
                                                        maxLength={20}
                                                        className="uppercase"
                                                    />
                                                    <InputError
                                                        message={errors.code}
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="edit-name">
                                                        Name
                                                    </Label>
                                                    <Input
                                                        id="edit-name"
                                                        name="name"
                                                        defaultValue={
                                                            editingSubject.name
                                                        }
                                                        required
                                                        autoComplete="off"
                                                        maxLength={255}
                                                    />
                                                    <InputError
                                                        message={errors.name}
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="edit-group">
                                                        Group
                                                    </Label>
                                                    <select
                                                        id="edit-group"
                                                        name="group"
                                                        defaultValue={
                                                            editingSubject.group
                                                        }
                                                        required
                                                        className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                                    >
                                                        {groups.map((group) => (
                                                            <option
                                                                key={
                                                                    group.value
                                                                }
                                                                value={
                                                                    group.value
                                                                }
                                                            >
                                                                {group.label}
                                                            </option>
                                                        ))}
                                                    </select>
                                                    <InputError
                                                        message={errors.group}
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="edit-sort_order">
                                                        Sort Order
                                                    </Label>
                                                    <Input
                                                        id="edit-sort_order"
                                                        name="sort_order"
                                                        type="number"
                                                        min={0}
                                                        defaultValue={
                                                            editingSubject.sort_order
                                                        }
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.sort_order
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="edit-description">
                                                    Description (optional)
                                                </Label>
                                                <textarea
                                                    id="edit-description"
                                                    name="description"
                                                    defaultValue={
                                                        editingSubject.description ??
                                                        ''
                                                    }
                                                    rows={3}
                                                    className="border-input placeholder:text-muted-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                                    placeholder="Deskripsi singkat mata pelajaran..."
                                                />
                                                <InputError
                                                    message={errors.description}
                                                />
                                            </div>

                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="hidden"
                                                    name="is_active"
                                                    value="0"
                                                />
                                                <input
                                                    type="checkbox"
                                                    id="edit-is_active"
                                                    name="is_active"
                                                    value="1"
                                                    defaultChecked={
                                                        editingSubject.is_active
                                                    }
                                                    className="text-primary focus:ring-primary h-4 w-4 rounded border-gray-300"
                                                />
                                                <Label
                                                    htmlFor="edit-is_active"
                                                    className="cursor-pointer"
                                                >
                                                    Active (Mata pelajaran
                                                    aktif)
                                                </Label>
                                                <InputError
                                                    message={errors.is_active}
                                                />
                                            </div>
                                        </div>

                                        <SheetFooter className="flex flex-row justify-end gap-2 border-t px-0 pt-4">
                                            <Button
                                                variant="outline"
                                                type="button"
                                                onClick={() =>
                                                    setEditingSubject(null)
                                                }
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && (
                                                    <Spinner className="mr-2" />
                                                )}
                                                Save changes
                                            </Button>
                                        </SheetFooter>
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </SheetContent>
            </Sheet>
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
