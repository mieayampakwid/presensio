import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Controller from '@/actions/App/Http/Controllers/Announcements/AnnouncementController';
import Reader from '@/actions/App/Http/Controllers/Announcements/AnnouncementReaderController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeAnnouncement } from '@/routes/announcements';
import { store as storeClass } from '@/routes/classes/announcements';
import type { Announcement, ClassContext } from './types';
type Props = {
    announcement: Announcement | null;
    classContext: ClassContext;
    canManage: boolean;
    classes: { id: number; name: string }[];
    roles: string[];
    timezone: string;
    schoolNow: string;
};
const selectClass =
    'border-input bg-background h-10 w-full rounded-md border px-3 text-sm';
export default function AnnouncementForm({
    announcement,
    classContext,
    canManage,
    classes,
    roles,
    timezone,
    schoolNow,
}: Props) {
    const live = announcement?.status === 'Terbit';
    const form = useForm({
        title: announcement?.title ?? '',
        body: announcement?.body ?? '',
        audience_type:
            announcement?.audience_type ?? (classContext ? 'class' : 'all'),
        audience_role: announcement?.audience_role ?? '',
        audience_grade_level: String(announcement?.audience_grade_level ?? ''),
        class_id: String(announcement?.class_id ?? classContext?.id ?? ''),
        is_pinned: announcement?.is_pinned ?? false,
        requires_acknowledgement:
            announcement?.requires_acknowledgement ?? false,
        notify_urgent: announcement?.notify_urgent ?? false,
        published_at: announcement?.published_at?.replace(' ', 'T') ?? '',
        attachment: null as File | null,
        remove_attachment: false,
    });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(
            announcement
                ? Controller.update.url(announcement.id)
                : classContext
                  ? storeClass.url(classContext.id)
                  : storeAnnouncement.url(),
            { forceFormData: true },
        );
    };
    const title = announcement ? 'Edit pengumuman' : 'Buat pengumuman';
    const target = (value: string) =>
        form.setData((data) => ({
            ...data,
            audience_type: value,
            audience_role: '',
            audience_grade_level: '',
            class_id: '',
        }));
    return (
        <>
            <Head title={title} />
            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
                <Heading
                    title={title}
                    description="Sampaikan informasi yang jelas kepada penerima yang tepat."
                />
                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-lg border p-5 md:p-6"
                >
                    <div className="space-y-2">
                        <Label htmlFor="title">Judul</Label>
                        <Input
                            id="title"
                            required
                            maxLength={255}
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="body">Isi pengumuman</Label>
                        <textarea
                            className="border-input bg-background focus-visible:ring-ring flex min-h-60 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                            id="body"
                            rows={12}
                            required
                            maxLength={50000}
                            value={form.data.body}
                            onChange={(e) =>
                                form.setData('body', e.target.value)
                            }
                        />
                        <p className="text-muted-foreground text-xs">
                            Mendukung Markdown: judul, daftar, penekanan, dan
                            tautan. HTML dinonaktifkan.
                        </p>
                        <InputError message={form.errors.body} />
                    </div>
                    {live && (
                        <p className="bg-muted rounded-md p-3 text-sm">
                            Pengumuman sudah terbit. Target, waktu terbit, dan
                            aturan konfirmasi tetap; perubahan isi tidak meminta
                            konfirmasi ulang.
                        </p>
                    )}
                    <fieldset
                        disabled={live}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <legend className="mb-3 font-medium">Penerima</legend>
                        <div className="space-y-2">
                            <Label htmlFor="audience_type">
                                Target pengumuman
                            </Label>
                            <select
                                id="audience_type"
                                className={selectClass}
                                disabled={!canManage || !!classContext || live}
                                value={form.data.audience_type}
                                onChange={(e) => target(e.target.value)}
                            >
                                {canManage && (
                                    <>
                                        <option value="all">
                                            Semua pengguna
                                        </option>
                                        <option value="role">
                                            Peran tertentu
                                        </option>
                                        <option value="grade_level">
                                            Tingkat kelas
                                        </option>
                                    </>
                                )}
                                <option value="class">Kelas tertentu</option>
                            </select>
                            <InputError message={form.errors.audience_type} />
                        </div>
                        {form.data.audience_type === 'role' && (
                            <div className="space-y-2">
                                <Label htmlFor="audience_role">Peran</Label>
                                <select
                                    id="audience_role"
                                    className={selectClass}
                                    required
                                    value={form.data.audience_role}
                                    onChange={(e) =>
                                        form.setData(
                                            'audience_role',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Pilih peran</option>
                                    {roles.map((role) => (
                                        <option key={role} value={role}>
                                            {role}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={form.errors.audience_role}
                                />
                            </div>
                        )}
                        {form.data.audience_type === 'grade_level' && (
                            <div className="space-y-2">
                                <Label htmlFor="grade">Tingkat kelas</Label>
                                <Input
                                    id="grade"
                                    type="number"
                                    min={1}
                                    max={12}
                                    required
                                    value={form.data.audience_grade_level}
                                    onChange={(e) =>
                                        form.setData(
                                            'audience_grade_level',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.audience_grade_level}
                                />
                            </div>
                        )}
                        {form.data.audience_type === 'class' && (
                            <div className="space-y-2">
                                <Label htmlFor="class_id">Kelas</Label>
                                <select
                                    id="class_id"
                                    className={selectClass}
                                    required
                                    disabled={!!classContext || live}
                                    value={form.data.class_id}
                                    onChange={(e) =>
                                        form.setData('class_id', e.target.value)
                                    }
                                >
                                    <option value="">Pilih kelas</option>
                                    {classes.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.class_id} />
                            </div>
                        )}
                    </fieldset>
                    <div className="space-y-2">
                        <Label htmlFor="attachment">
                            Lampiran PDF/JPG/PNG (maks. 5 MB)
                        </Label>
                        <Input
                            id="attachment"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            onChange={(e) =>
                                form.setData(
                                    'attachment',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={form.errors.attachment} />
                        {announcement?.attachment_name && (
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.remove_attachment}
                                    onChange={(e) =>
                                        form.setData(
                                            'remove_attachment',
                                            e.target.checked,
                                        )
                                    }
                                />
                                Hapus lampiran: {announcement.attachment_name}
                            </label>
                        )}
                    </div>
                    <fieldset className="space-y-3">
                        <legend className="mb-3 font-medium">
                            Penerbitan dan konfirmasi
                        </legend>
                        <div className="space-y-2">
                            <Label htmlFor="published_at">
                                Waktu terbit ({timezone})
                            </Label>
                            <Input
                                id="published_at"
                                type="datetime-local"
                                step="1"
                                disabled={live}
                                value={form.data.published_at}
                                onChange={(e) =>
                                    form.setData('published_at', e.target.value)
                                }
                            />
                            <p className="text-muted-foreground text-xs">
                                Kosongkan untuk menyimpan draf, atau pilih waktu
                                mendatang untuk menjadwalkan.
                            </p>
                            <InputError message={form.errors.published_at} />
                        </div>
                        {!live && (
                            <Button
                                variant="outline"
                                type="button"
                                onClick={() =>
                                    form.setData('published_at', schoolNow)
                                }
                            >
                                Terbitkan sekarang
                            </Button>
                        )}
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.is_pinned}
                                onChange={(e) =>
                                    form.setData('is_pinned', e.target.checked)
                                }
                            />
                            Sematkan di atas
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                disabled={live}
                                checked={form.data.requires_acknowledgement}
                                onChange={(e) =>
                                    form.setData(
                                        'requires_acknowledgement',
                                        e.target.checked,
                                    )
                                }
                            />
                            Minta konfirmasi membaca
                        </label>
                        {canManage && (
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    disabled={live}
                                    checked={form.data.notify_urgent}
                                    onChange={(e) =>
                                        form.setData(
                                            'notify_urgent',
                                            e.target.checked,
                                        )
                                    }
                                />
                                Kirim notifikasi penting sesuai pengaturan kanal
                                sekolah
                            </label>
                        )}
                    </fieldset>
                    {Object.keys(form.errors).length > 0 && (
                        <div role="alert" className="text-destructive text-sm">
                            Periksa kolom formulir.{' '}
                            {Object.entries(form.errors)
                                .filter(
                                    ([key]) =>
                                        ![
                                            'title',
                                            'body',
                                            'audience_type',
                                            'audience_role',
                                            'audience_grade_level',
                                            'class_id',
                                            'published_at',
                                            'attachment',
                                        ].includes(key),
                                )
                                .map(([key, value]) => (
                                    <p key={key}>{value}</p>
                                ))}
                        </div>
                    )}
                    <div className="flex justify-end gap-3 border-t pt-5">
                        <Button type="button" variant="outline" asChild>
                            <Link
                                href={
                                    announcement
                                        ? Reader.show(announcement.id)
                                        : Reader.index()
                                }
                            >
                                Batal
                            </Link>
                        </Button>
                        <Button disabled={form.processing}>
                            {form.processing
                                ? 'Menyimpan…'
                                : 'Simpan pengumuman'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
