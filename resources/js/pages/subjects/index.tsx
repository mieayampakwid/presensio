import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Trash2 } from 'lucide-react';
import { useState, type ChangeEvent } from 'react';
import SubjectController from '@/actions/App/Http/Controllers/Subjects/SubjectController';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
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
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('nav.items.subjects'),
                href: SubjectController.index().url,
            },
        ],
    });
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
            <Head title={t('subjects.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('subjects.title')}
                        description={t('subjects.description')}
                    />

                    <Button onClick={() => setIsCreateOpen(true)}>
                        {t('subjects.create')}
                    </Button>
                </div>

                <form className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="search">{t('subjects.search')}</Label>
                        <Input
                            id="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder={t('subjects.search_placeholder')}
                            onChange={submitFilters}
                            className="w-64"
                        />
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    {t('subjects.table.code')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('subjects.table.name')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('subjects.table.group')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('subjects.table.order')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('subjects.table.status')}
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    {t('subjects.table.actions')}
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
                                                {t('subjects.table.active')}
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-medium text-neutral-600 ring-1 ring-neutral-500/20 ring-inset dark:bg-neutral-800 dark:text-neutral-400">
                                                {t('subjects.table.inactive')}
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
                                                    {t('common.edit')}
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
                                                    {t('common.delete')}
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
                                        {t('subjects.table.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {t('common.page_of', {
                            current: subjects.current_page,
                            last: subjects.last_page,
                        })}
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
                                {t('common.previous')}
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!subjects.next_page_url}
                        >
                            <Link href={subjects.next_page_url ?? '#'}>
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
                        {t('subjects.delete_dialog.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('subjects.delete_dialog.message', {
                            name: deleting?.name ?? '',
                            code: deleting?.code ?? '',
                        })}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
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

            <Sheet open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <SheetContent side="right" className="sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>{t('subjects.create_title')}</SheetTitle>
                        <SheetDescription>
                            {t('subjects.create_description')}
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
                                                {t('subjects.fields.code')}
                                            </Label>
                                            <Input
                                                id="create-code"
                                                name="code"
                                                required
                                                autoFocus
                                                autoComplete="off"
                                                placeholder={t(
                                                    'subjects.fields.code_placeholder',
                                                )}
                                                className="uppercase"
                                            />
                                            <InputError message={errors.code} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="create-name">
                                                {t('subjects.fields.name')}
                                            </Label>
                                            <Input
                                                id="create-name"
                                                name="name"
                                                required
                                                autoComplete="off"
                                                placeholder={t(
                                                    'subjects.fields.name_placeholder',
                                                )}
                                                maxLength={255}
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="create-group">
                                                {t('subjects.fields.group')}
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
                                                {t(
                                                    'subjects.fields.sort_order',
                                                )}
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
                                            {t('subjects.fields.description')}
                                        </Label>
                                        <textarea
                                            id="create-description"
                                            name="description"
                                            rows={3}
                                            className="border-input placeholder:text-muted-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                            placeholder={t(
                                                'subjects.fields.description_placeholder',
                                            )}
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
                                            {t('subjects.fields.is_active')}
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
                                        {t('common.cancel')}
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && (
                                            <Spinner className="mr-2" />
                                        )}
                                        {t('subjects.create')}
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
                                    {t('subjects.edit_title', {
                                        name: editingSubject.name,
                                    })}
                                </SheetTitle>
                                <SheetDescription>
                                    {t('subjects.edit_description')}
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
                                                        {t(
                                                            'subjects.fields.code',
                                                        )}
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
                                                        {t(
                                                            'subjects.fields.name',
                                                        )}
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
                                                        {t(
                                                            'subjects.fields.group',
                                                        )}
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
                                                        {t(
                                                            'subjects.fields.sort_order',
                                                        )}
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
                                                    {t(
                                                        'subjects.fields.description',
                                                    )}
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
                                                    placeholder={t(
                                                        'subjects.fields.description_placeholder',
                                                    )}
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
                                                    {t(
                                                        'subjects.fields.is_active',
                                                    )}
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
                                                {t('common.cancel')}
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && (
                                                    <Spinner className="mr-2" />
                                                )}
                                                {t('common.save_changes')}
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
