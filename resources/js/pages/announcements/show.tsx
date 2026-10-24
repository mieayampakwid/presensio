import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Controller from '@/actions/App/Http/Controllers/Announcements/AnnouncementController';
import Reader from '@/actions/App/Http/Controllers/Announcements/AnnouncementReaderController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Announcement } from './types';
export default function Show({
    announcement: item,
    bodyHtml,
}: {
    announcement: Announcement;
    bodyHtml: string;
}) {
    const [deleting, setDeleting] = useState(false);
    const [busy, setBusy] = useState(false);
    const acknowledge = () => {
        setBusy(true);
        router.post(
            Reader.acknowledge.url(item.id),
            {},
            { onFinish: () => setBusy(false) },
        );
    };
    return (
        <>
            <Head title={item.title} />
            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
                <Link
                    className="text-muted-foreground text-sm underline underline-offset-4"
                    href={Reader.index()}
                >
                    Kembali ke pengumuman
                </Link>
                <article className="space-y-6 rounded-lg border p-5 md:p-8">
                    <header className="space-y-4">
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">{item.status}</Badge>
                            {item.is_pinned && (
                                <Badge variant="secondary">Disematkan</Badge>
                            )}
                        </div>
                        <h1 className="text-2xl leading-snug font-semibold text-balance">
                            {item.title}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {item.author} ·{' '}
                            {item.published_at ?? 'Belum diterbitkan'}
                            {item.is_updated && (
                                <span className="block">
                                    Diperbarui {item.updated_at}
                                </span>
                            )}
                        </p>
                    </header>
                    <div
                        className="[&_pre]:bg-muted text-sm leading-7 break-words [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:pl-4 [&_h1]:mb-4 [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:my-4 [&_h2]:text-lg [&_h2]:font-semibold [&_li]:ml-5 [&_ol]:list-decimal [&_p]:mb-4 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:p-3 [&_ul]:list-disc"
                        dangerouslySetInnerHTML={{ __html: bodyHtml }}
                    />
                    {item.attachment_name && (
                        <div className="border-t pt-5">
                            <a
                                className="text-sm underline underline-offset-4"
                                href={Reader.attachment.url(item.id)}
                            >
                                Unduh lampiran: {item.attachment_name}
                            </a>
                        </div>
                    )}
                    {item.requires_acknowledgement && (
                        <div className="bg-muted space-y-3 rounded-lg p-4">
                            <h2 className="font-medium">Konfirmasi membaca</h2>
                            <p className="text-muted-foreground text-sm">
                                Konfirmasi menyatakan Anda telah membaca
                                pengumuman ini, bukan memberikan persetujuan
                                kegiatan.
                            </p>
                            {item.acknowledged_at ? (
                                <p className="text-sm font-medium">
                                    Sudah dikonfirmasi
                                </p>
                            ) : item.can_acknowledge ? (
                                <Button disabled={busy} onClick={acknowledge}>
                                    {busy ? 'Menyimpan…' : 'Saya sudah membaca'}
                                </Button>
                            ) : (
                                <p className="text-sm">
                                    Konfirmasi tersedia setelah pengumuman
                                    terbit.
                                </p>
                            )}
                        </div>
                    )}
                    <div className="flex flex-wrap gap-3">
                        {item.can_report && (
                            <Button asChild variant="outline">
                                <Link href={Reader.recipients(item.id)}>
                                    Status penerima
                                </Link>
                            </Button>
                        )}
                        {item.can_manage && (
                            <>
                                <Button asChild variant="outline">
                                    <Link href={Controller.edit(item.id)}>
                                        Edit pengumuman
                                    </Link>
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() => setDeleting(true)}
                                >
                                    Hapus pengumuman
                                </Button>
                            </>
                        )}
                    </div>
                </article>
            </div>
            <Dialog open={deleting} onOpenChange={setDeleting}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus pengumuman?</DialogTitle>
                        <DialogDescription>
                            Pengumuman, lampiran, dan catatan konfirmasi akan
                            dihapus. Tindakan ini tidak dapat dibatalkan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={busy}
                            onClick={() => {
                                setBusy(true);
                                router.delete(Controller.destroy.url(item.id), {
                                    onFinish: () => setBusy(false),
                                });
                            }}
                        >
                            Ya, hapus
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
