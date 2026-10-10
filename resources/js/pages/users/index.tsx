import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/Users/UserController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import UserForm from './user-form';

type UserRow = {
    id: number;
    username: string;
    email: string | null;
    role: string;
    roles?: string[];
    is_active: boolean;
};

type ProfileOption = { id: number; label: string };

type ProfileOptions = {
    teacher: ProfileOption[];
    parent: ProfileOption[];
    student: ProfileOption[];
};

type Paginator = {
    data: UserRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    users: Paginator;
    filters: { search: string };
    profiles?: ProfileOptions;
};

export default function UsersIndex({ users, filters, profiles }: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);

    const openCreate = () => {
        if (!profiles) {
            router.reload({ only: ['profiles'] });
        }
        setIsCreateOpen(true);
    };
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('users.index.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('users.index.title')}
                        description={t('users.index.description')}
                    />

                    <Button onClick={openCreate}>
                        {t('users.index.create')}
                    </Button>
                </div>

                <form className="max-w-sm">
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder={t('users.index.search_placeholder')}
                    />
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('users.index.columns.username')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('users.index.columns.email')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('users.index.columns.roles')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('users.index.columns.status')}
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {user.username}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {user.email ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {(user.roles &&
                                            user.roles.length > 0
                                                ? user.roles
                                                : [user.role]
                                            ).map((role) => (
                                                <Badge
                                                    key={role}
                                                    variant="outline"
                                                    className="capitalize"
                                                >
                                                    {t(`roles.${role}`)}
                                                </Badge>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                user.is_active
                                                    ? 'secondary'
                                                    : 'destructive'
                                            }
                                        >
                                            {user.is_active
                                                ? t('users.status.active')
                                                : t('users.status.inactive')}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    UserController.edit({
                                                        user: user.id,
                                                    }).url
                                                }
                                            >
                                                <Pencil className="h-4 w-4" />
                                                <span className="sr-only">
                                                    {t('common.edit')}
                                                </span>
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}

                            {users.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={5}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('users.index.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {t('common.page_of', {
                            current: users.current_page,
                            last: users.last_page,
                        })}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!users.prev_page_url}
                        >
                            <Link href={users.prev_page_url ?? '#'}>
                                <ChevronLeft className="h-4 w-4" />
                                {t('common.previous')}
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!users.next_page_url}
                        >
                            <Link href={users.next_page_url ?? '#'}>
                                {t('common.next')}
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            <Sheet open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <SheetContent side="right" className="sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>{t('users.create.title')}</SheetTitle>
                        <SheetDescription>
                            {t('users.create.description')}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 overflow-y-auto px-4">
                        <UserForm
                            action={UserController.store.form()}
                            submitLabel={t('users.form.create_submit')}
                            defaults={{
                                username: '',
                                email: null,
                                roles: ['teacher'],
                                role: 'teacher',
                            }}
                            profiles={
                                profiles ?? {
                                    teacher: [],
                                    parent: [],
                                    student: [],
                                }
                            }
                            showPassword
                            showActive={false}
                            onSuccess={() => setIsCreateOpen(false)}
                        />
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Users',
        },
    ],
};
