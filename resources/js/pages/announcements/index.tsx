import { create as createAnnouncement } from '@/routes/announcements';
import { Head, Link, router, usePage } from '@inertiajs/react';
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
    const { errors } = usePage().props;
    const [query, setQuery] = useState(filters);
    const search = (event: FormEvent) => {
        event.preventDefault();
        router.get(Reader.index.url(), query, { preserveState: true });
    };
    const title = classContext
        ? `Pengumuman kelas ${classContext.name}`
        : mode === 'manage'
          ? 'Kelola pengumuman'
          : 'Pengumuman';
    return (
        <>
            <Head title={title} />
            <div className="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={title}
                        description="Informasi resmi sekolah, tersimpan dan mudah ditemukan."
                    />
                    <div className="flex flex-wrap gap-2">
                        {mode !== 'feed' && (
                            <Button variant="outline" asChild>
                                <Link href={Reader.index()}>
                                    Semua pengumuman
                                </Link>
                            </Button>
                        )}
                        {canManage && mode === 'feed' && (
                            <Button variant="outline" asChild>
                                <Link href={AnnouncementController.manage()}>
                                    Kelola pengumuman
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
                                    Buat pengumuman
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
                                Cari judul atau isi
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
                                Dari tanggal
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
                                Sampai tanggal
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
                        <Button variant="outline">Tampilkan</Button>
                    </form>
                )}
                <InputError message={errors.q ?? errors.from ?? errors.to} />
                {announcements.data.length === 0 ? (
                    <div className="rounded-lg border px-6 py-14 text-center">
                        <h2 className="font-semibold">Belum ada pengumuman</h2>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Pengumuman yang sesuai dengan akun Anda akan muncul
                            di sini.
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
                                            Disematkan
                                        </Badge>
                                    )}
                                    {mode !== 'feed' && (
                                        <Badge variant="outline">
                                            {item.status}
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
                                                ? 'Sudah dikonfirmasi'
                                                : 'Perlu konfirmasi'}
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
                                        Lampiran: {item.attachment_name}
                                    </a>
                                )}
                                <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-3 text-sm">
                                    <span>
                                        {item.author} ·{' '}
                                        {item.published_at ??
                                            'Belum diterbitkan'}
                                    </span>
                                    <div className="flex gap-4">
                                        {item.can_manage && (
                                            <Link
                                                className="text-foreground underline underline-offset-4"
                                                href={AnnouncementController.edit(
                                                    item.id,
                                                )}
                                            >
                                                Edit
                                            </Link>
                                        )}
                                        <Link
                                            className="text-foreground underline underline-offset-4"
                                            href={Reader.show(item.id)}
                                        >
                                            Baca pengumuman
                                        </Link>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
                <div className="flex items-center justify-between gap-3 text-sm">
                    <span className="text-muted-foreground">
                        Halaman {announcements.current_page} dari{' '}
                        {announcements.last_page} · {announcements.total}{' '}
                        pengumuman
                    </span>
                    <div className="flex gap-2">
                        {announcements.prev_page_url && (
                            <Button variant="outline" asChild>
                                <Link href={announcements.prev_page_url}>
                                    Sebelumnya
                                </Link>
                            </Button>
                        )}
                        {announcements.next_page_url && (
                            <Button variant="outline" asChild>
                                <Link href={announcements.next_page_url}>
                                    Berikutnya
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
