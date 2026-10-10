import { Form, Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import GuardianController from '@/actions/App/Http/Controllers/Guardians/GuardianController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type GuardianRow = {
    id: number;
    name: string;
    phone_number: string;
    work: string | null;
    address?: string | null;
};

type Paginator = {
    data: GuardianRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    guardians: Paginator;
    filters: { search: string };
};

export default function GuardiansIndex({ guardians, filters }: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingGuardian, setEditingGuardian] = useState<GuardianRow | null>(
        null,
    );
    const [deleting, setDeleting] = useState<GuardianRow | null>(null);
    const destroy = (guardian: GuardianRow) => {
        router.delete(
            GuardianController.destroy({ guardian: guardian.id }).url,
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Guardians" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Guardians"
                        description="Absence notifications go to these contacts; the phone number is the guardian identity."
                    />

                    <Button onClick={() => setIsCreateOpen(true)}>
                        Create guardian
                    </Button>
                </div>

                <form className="max-w-sm">
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search by name or phone…"
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
                                    Phone
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Occupation
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {guardians.data.map((guardian) => (
                                <tr key={guardian.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {guardian.name}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {guardian.phone_number}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {guardian.work ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setEditingGuardian(guardian)
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
                                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                onClick={() =>
                                                    setDeleting(guardian)
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

                            {guardians.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        No guardians found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        Page {guardians.current_page} of {guardians.last_page}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!guardians.prev_page_url}
                        >
                            <Link href={guardians.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                Previous
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!guardians.next_page_url}
                        >
                            <Link href={guardians.next_page_url ?? '#'}>
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
                    <DialogTitle>Delete guardian</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{deleting?.name}"?
                        Their links to children are removed with them.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
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

            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create guardian</DialogTitle>
                        <DialogDescription>
                            Add a new guardian contact for notifications and
                            student linkage.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        {...GuardianController.store.form()}
                        onSuccess={() => setIsCreateOpen(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="create-name">Name</Label>
                                    <Input
                                        id="create-name"
                                        name="name"
                                        required
                                        autoFocus
                                        autoComplete="off"
                                        placeholder="Full name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-phone_number">
                                        Phone number
                                    </Label>
                                    <Input
                                        id="create-phone_number"
                                        name="phone_number"
                                        required
                                        autoComplete="off"
                                        placeholder="e.g. +6281234567890"
                                    />
                                    <InputError message={errors.phone_number} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-work">
                                        Occupation (optional)
                                    </Label>
                                    <Input
                                        id="create-work"
                                        name="work"
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.work} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="create-address">
                                        Address (optional)
                                    </Label>
                                    <textarea
                                        id="create-address"
                                        name="address"
                                        rows={3}
                                        className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                    />
                                    <InputError message={errors.address} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <Button
                                        variant="secondary"
                                        type="button"
                                        onClick={() => setIsCreateOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Create guardian
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingGuardian !== null}
                onOpenChange={(open) => !open && setEditingGuardian(null)}
            >
                <DialogContent>
                    {editingGuardian && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Edit {editingGuardian.name}
                                </DialogTitle>
                                <DialogDescription>
                                    Update contact details.
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                key={editingGuardian.id}
                                {...GuardianController.update.form({
                                    guardian: editingGuardian.id,
                                })}
                                onSuccess={() => setEditingGuardian(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-name">
                                                Name
                                            </Label>
                                            <Input
                                                id="edit-name"
                                                name="name"
                                                defaultValue={
                                                    editingGuardian.name
                                                }
                                                required
                                                autoComplete="off"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-phone_number">
                                                Phone number
                                            </Label>
                                            <Input
                                                id="edit-phone_number"
                                                name="phone_number"
                                                defaultValue={
                                                    editingGuardian.phone_number
                                                }
                                                required
                                                autoComplete="off"
                                            />
                                            <InputError
                                                message={errors.phone_number}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-work">
                                                Occupation (optional)
                                            </Label>
                                            <Input
                                                id="edit-work"
                                                name="work"
                                                defaultValue={
                                                    editingGuardian.work ?? ''
                                                }
                                                autoComplete="off"
                                            />
                                            <InputError message={errors.work} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="edit-address">
                                                Address (optional)
                                            </Label>
                                            <textarea
                                                id="edit-address"
                                                name="address"
                                                defaultValue={
                                                    editingGuardian.address ??
                                                    ''
                                                }
                                                rows={3}
                                                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                            />
                                            <InputError
                                                message={errors.address}
                                            />
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <Button
                                                variant="secondary"
                                                type="button"
                                                onClick={() =>
                                                    setEditingGuardian(null)
                                                }
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                Save changes
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

GuardiansIndex.layout = {
    breadcrumbs: [
        {
            title: 'Guardians',
            href: GuardianController.index().url,
        },
    ],
};
