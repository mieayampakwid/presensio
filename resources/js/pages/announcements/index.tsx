import { create as createAnnouncement } from '@/routes/announcements';
import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/react';
import { Pin, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import AnnouncementController from '@/actions/App/Http/Controllers/Announcements/AnnouncementController';
import Reader from '@/actions/App/Http/Controllers/Announcements/AnnouncementReaderController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create as createClass } from '@/routes/classes/announcements';
import type { Announcement, ClassContext, PageData } from './types';
import { useTranslation } from '@/hooks/use-translation';
type Props = {
    announcements: PageData<Announcement>;
    filters: { q: string; from: string; to: string };
    mode: string;
    classContext: ClassContext;
    canManage: boolean;
    canCreate: boolean;
};
export default function Announcements({
    announcements,
    filters,
    mode,
    classContext,
    canManage,
    canCreate,
}: Props) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const [query, setQuery] = useState(filters);
    const search = (event: FormEvent) => {
        event.preventDefault();
        router.get(Reader.index.url(), query, { preserveState: true });
    };
    const title = classContext
        ? t('announcements.class_feed_title', { name: classContext.name })
        : mode === 'manage'
          ? t('announcements.manage_title')
          : t('announcements.title');
    setLayoutProps({
        breadcrumbs: [
            { title: t('announcements.title'), href: '/announcements' },
            ...(classContext || mode === 'manage' ? [{ title }] : []),
        ],
    });

    return (
        <>
            <Head title={title} />
            <div className="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={title}
                        description={t('announcements.description')}
                    />
                    <div className="flex flex-wrap gap-2">
                        {mode !== 'feed' && (
                            <Button variant="outline" asChild>
                                <Link href={Reader.index()}>
                                    {t('announcements.all')}
                                </Link>
                            </Button>
                        )}
                        {canManage && mode === 'feed' && (
                            <Button variant="outline" asChild>
                                <Link href={AnnouncementController.manage()}>
                                    {t('announcements.manage')}
                                </Link>
                            </Button>
                        )}
                        {canCreate && (
                            <Button asChild>
                                <Link
                                    href={
                                        classContext
                                            ? createClass(classContext.id)
                                            : createAnnouncement()
                                    }
                                >
                                    <Plus className="size-4" />
                                    {t('announcements.create')}
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
                {mode === 'feed' && (
                    <form
                        onSubmit={search}
                        className="flex flex-wrap items-end gap-3 rounded-lg border p-4"
                    >
                        <div className="min-w-48 flex-1 space-y-2">
                            <Label htmlFor="announcement-search">
                                {t('announcements.search_placeholder')}
                            </Label>
                            <Input
                                id="announcement-search"
                                value={query.q ?? ''}
                                onChange={(e) =>
                                    setQuery({ ...query, q: e.target.value })
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="announcement-from">
                                {t('announcements.from_date')}
                            </Label>
                            <Input
                                id="announcement-from"
                                type="date"
                                value={query.from ?? ''}
                                onChange={(e) =>
                                    setQuery({ ...query, from: e.target.value })
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="announcement-to">
                                {t('announcements.to_date')}
                            </Label>
                            <Input
                                id="announcement-to"
                                type="date"
                                value={query.to ?? ''}
                                onChange={(e) =>
                                    setQuery({ ...query, to: e.target.value })
                                }
                            />
                        </div>
                        <Button variant="outline">
                            {t('announcements.filter_show')}
                        </Button>
                    </form>
                )}
                <InputError message={errors.q ?? errors.from ?? errors.to} />
                {announcements.data.length === 0 ? (
                    <div className="rounded-lg border px-6 py-14 text-center">
                        <h2 className="font-semibold">
                            {t('announcements.empty_title')}
                        </h2>
                        <p className="text-muted-foreground mt-2 text-sm">
                            {t('announcements.empty_description')}
                        </p>
                    </div>
                ) : (
                    <div className="divide-y rounded-lg border">
                        {announcements.data.map((item) => (
                            <article
                                key={item.id}
                                className="space-y-3 p-5 md:p-6"
                            >
                                <div className="flex flex-wrap items-center gap-2 text-sm">
                                    {item.is_pinned && (
                                        <Badge variant="secondary">
                                            <Pin className="mr-1 size-3" />
                                            {t('announcements.pinned')}
                                        </Badge>
                                    )}
                                    {mode !== 'feed' && (
                                        <Badge variant="outline">
                                            {t(
                                                `announcements.status.${item.status}`,
                                            )}
                                        </Badge>
                                    )}
                                    {item.requires_acknowledgement && (
                                        <Badge
                                            variant={
                                                item.acknowledged_at
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {item.acknowledged_at
                                                ? t(
                                                      'announcements.acknowledged',
                                                  )
                                                : t(
                                                      'announcements.needs_acknowledgement',
                                                  )}
                                        </Badge>
                                    )}
                                </div>
                                <h2 className="text-lg font-semibold">
                                    <Link
                                        className="hover:underline"
                                        href={Reader.show(item.id)}
                                    >
                                        {item.title}
                                    </Link>
                                </h2>
                                <p className="text-muted-foreground line-clamp-2 text-sm leading-relaxed">
                                    {item.excerpt}
                                </p>
                                {item.attachment_name && (
                                    <a
                                        className="block text-sm underline underline-offset-4"
                                        href={Reader.attachment.url(item.id)}
                                    >
                                        {t('announcements.attachment')}{' '}
                                        {item.attachment_name}
                                    </a>
                                )}
                                <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-3 text-sm">
                                    <span>
                                        {item.author} ·{' '}
                                        {item.published_at ??
                                            t('announcements.not_published')}
                                    </span>
                                    <div className="flex gap-4">
                                        {item.can_manage && (
                                            <Link
                                                className="text-foreground underline underline-offset-4"
                                                href={AnnouncementController.edit(
                                                    item.id,
                                                )}
                                            >
                                                {t('common.edit')}
                                            </Link>
                                        )}
                                        <Link
                                            className="text-foreground underline underline-offset-4"
                                            href={Reader.show(item.id)}
                                        >
                                            {t('announcements.read_more')}
                                        </Link>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
                <div className="flex items-center justify-between gap-3 text-sm">
                    <span className="text-muted-foreground">
                        {t('announcements.pagination', {
                            current: announcements.current_page,
                            last: announcements.last_page,
                            total: announcements.total,
                        })}
                    </span>
                    <div className="flex gap-2">
                        {announcements.prev_page_url && (
                            <Button variant="outline" asChild>
                                <Link href={announcements.prev_page_url}>
                                    {t('common.previous')}
                                </Link>
                            </Button>
                        )}
                        {announcements.next_page_url && (
                            <Button variant="outline" asChild>
                                <Link href={announcements.next_page_url}>
                                    {t('common.next')}
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
