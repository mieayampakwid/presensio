import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ClipboardCheck,
    FileText,
    LoaderCircle,
    Users,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import Heading from '@/components/heading';
import ReportCardView, {
    type ReportCardPayload,
} from '@/components/report-card-view';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    pdf,
    preview,
    previewPdf,
    show,
    updateEntry,
} from '@/actions/App/Http/Controllers/Teacher/ReportCardWorkspaceController';
import ReportCardPublicationController from '@/actions/App/Http/Controllers/Teacher/ReportCardPublicationController';

type ChecklistItem = {
    subject: string;
    teacher: string | null;
    finalized: boolean;
};

type StudentEntry = {
    id: number;
    name: string;
    subjects_total: number;
    subjects_with_results: number;
    complete: boolean;
    entries: {
        homeroom_notes: string | null;
        extracurricular_notes: string | null;
        promotion_decision: string | null;
    };
};

type SemesterInfo = {
    id: number;
    name: string;
    number: number;
};

type Props = {
    school_class: { id: number; name: string; grade_level: number };
    semester: SemesterInfo;
    semesters: SemesterInfo[];
    checklist: ChecklistItem[];
    students: StudentEntry[];
    publication: { version: number; published_at: string } | null;
    can: { edit: boolean };
};

/**
 * The encrypted XSRF cookie is URL-encoded; Laravel expects the decoded
 * value back in the X-XSRF-TOKEN header for native fetch requests.
 */
const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const describePublishErrors = (errors: Record<string, unknown>): string[] => {
    const messages: string[] = [];
    for (const [key, value] of Object.entries(errors)) {
        if (!Array.isArray(value)) {
            if (typeof value === 'string') {
                messages.push(value);
            }
            continue;
        }
        for (const item of value) {
            if (typeof item === 'string') {
                messages.push(item);
            } else if (item !== null && typeof item === 'object') {
                const entry = item as Record<string, unknown>;
                if (typeof entry.subject === 'string') {
                    const teacher =
                        typeof entry.teacher === 'string' ? entry.teacher : '—';
                    messages.push(
                        `${entry.subject} — ${teacher} belum difinalisasi.`,
                    );
                } else if (typeof entry.student === 'string') {
                    const label =
                        key === 'missing_decisions'
                            ? 'belum mengisi keputusan kenaikan.'
                            : 'belum memiliki nilai lengkap.';
                    messages.push(`${entry.student} ${label}`);
                }
            }
        }
    }
    return messages;
};

export default function ReportCards({
    school_class,
    semester,
    semesters,
    checklist,
    students,
    publication,
    can,
}: Props) {
    const [entryStudent, setEntryStudent] = useState<StudentEntry | null>(null);
    const [previewPayload, setPreviewPayload] =
        useState<ReportCardPayload | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [previewStudentId, setPreviewStudentId] = useState<number | null>(
        null,
    );
    const [publishErrors, setPublishErrors] = useState<string[]>([]);
    const [publishWarnings, setPublishWarnings] = useState<
        { student: string }[]
    >([]);
    const [publishing, setPublishing] = useState(false);
    const [retractOpen, setRetractOpen] = useState(false);
    const [retractReason, setRetractReason] = useState('');
    const [retractError, setRetractError] = useState<string | null>(null);
    const [retracting, setRetracting] = useState(false);

    const entryForm = useForm({
        semester_id: semester.id,
        homeroom_notes: '',
        extracurricular_notes: '',
        promotion_decision: '',
    });

    const handleSemesterChange = (newSemesterId: string) => {
        router.get(
            show.url(
                { school_class: school_class.id },
                { query: { semester: newSemesterId } },
            ),
            {},
            { preserveState: false },
        );
    };

    const openEntry = (student: StudentEntry) => {
        entryForm.reset();
        entryForm.setDefaults({
            semester_id: semester.id,
            homeroom_notes: student.entries.homeroom_notes ?? '',
            extracurricular_notes: student.entries.extracurricular_notes ?? '',
            promotion_decision: student.entries.promotion_decision ?? '',
        });
        setEntryStudent(student);
    };

    const saveEntry = (event: FormEvent) => {
        event.preventDefault();
        if (entryStudent === null) {
            return;
        }
        entryForm.put(
            updateEntry.url({
                school_class: school_class.id,
                student: entryStudent.id,
            }),
            {
                onSuccess: () => setEntryStudent(null),
            },
        );
    };

    const openPreview = async (student: StudentEntry) => {
        setPreviewLoading(true);
        setPreviewPayload(null);
        setPreviewError(null);
        setPreviewStudentId(student.id);
        try {
            const response = await fetch(
                preview.url(
                    { school_class: school_class.id, student: student.id },
                    { query: { semester: String(semester.id) } },
                ),
            );
            if (!response.ok) {
                setPreviewError(
                    response.status === 403
                        ? 'Anda tidak berwenang melihat pratinjau rapor ini.'
                        : `Gagal memuat pratinjau (HTTP ${response.status}).`,
                );
                return;
            }
            setPreviewPayload((await response.json()) as ReportCardPayload);
        } catch {
            setPreviewError(
                'Tidak dapat terhubung ke server. Periksa koneksi dan coba lagi.',
            );
        } finally {
            setPreviewLoading(false);
        }
    };

    const handlePublish = async () => {
        setPublishing(true);
        setPublishErrors([]);
        setPublishWarnings([]);
        try {
            const response = await fetch(
                ReportCardPublicationController.publish.url({
                    school_class: school_class.id,
                }),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify({
                        semester_id: semester.id,
                    }),
                },
            );
            const body = (await response.json()) as {
                version?: number;
                warnings?: { student: string }[];
                errors?: Record<string, unknown>;
            };
            if (response.status === 422 && body.errors) {
                setPublishErrors(describePublishErrors(body.errors));
                return;
            }
            if (!response.ok) {
                setPublishErrors([
                    `Gagal menerbitkan rapor (HTTP ${response.status}).`,
                ]);
                return;
            }
            setPublishWarnings(body.warnings ?? []);
            // Inertia v3 reload keeps component state by default, so the
            // publish warnings above survive the refreshed publication prop.
            router.reload();
        } catch {
            setPublishErrors([
                'Tidak dapat terhubung ke server. Periksa koneksi dan coba lagi.',
            ]);
        } finally {
            setPublishing(false);
        }
    };

    const handleRetract = async () => {
        if (retractReason.trim() === '') {
            setRetractError('Alasan penarikan wajib diisi.');
            return;
        }
        setRetracting(true);
        setRetractError(null);
        try {
            const response = await fetch(
                ReportCardPublicationController.retract.url({
                    school_class: school_class.id,
                }),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify({
                        semester_id: semester.id,
                        reason: retractReason.trim(),
                    }),
                },
            );
            const body = (await response.json()) as {
                errors?: Record<string, unknown>;
            };
            if (response.status === 422) {
                const messages = describePublishErrors(body.errors ?? {});
                setRetractError(messages[0] ?? 'Penarikan gagal.');
                return;
            }
            if (!response.ok) {
                setRetractError(
                    `Gagal menarik rapor (HTTP ${response.status}).`,
                );
                return;
            }
            setRetractOpen(false);
            setRetractReason('');
            router.reload();
        } catch {
            setRetractError(
                'Tidak dapat terhubung ke server. Periksa koneksi dan coba lagi.',
            );
        } finally {
            setRetracting(false);
        }
    };

    return (
        <>
            <Head
                title={`Rapor Kelas - ${school_class.name} (${semester.name})`}
            />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="text-muted-foreground flex items-center gap-2 text-sm">
                            <Link
                                href="/classes"
                                className="flex items-center gap-1 hover:underline"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Kembali ke Kelas
                            </Link>
                        </div>
                        <Heading
                            title={`Rapor Kelas: ${school_class.name}`}
                            description={`Ceklis kelulusan mata pelajaran dan catatan wali kelas untuk ${semester.name}`}
                        />
                    </div>
                    <div className="flex items-center gap-3">
                        <Select
                            value={String(semester.id)}
                            onValueChange={handleSemesterChange}
                        >
                            <SelectTrigger className="w-[180px]">
                                <SelectValue placeholder="Pilih Semester" />
                            </SelectTrigger>
                            <SelectContent>
                                {semesters.map((s) => (
                                    <SelectItem key={s.id} value={String(s.id)}>
                                        {s.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {can.edit && publication !== null && (
                            <Badge variant="default">
                                Versi {publication.version} · diterbitkan{' '}
                                {publication.published_at}
                            </Badge>
                        )}
                        {can.edit && publication === null && (
                            <Button
                                onClick={handlePublish}
                                disabled={publishing}
                            >
                                {publishing ? (
                                    <LoaderCircle className="h-4 w-4 animate-spin" />
                                ) : (
                                    <ClipboardCheck className="h-4 w-4" />
                                )}
                                Terbitkan
                            </Button>
                        )}
                        {can.edit && publication !== null && (
                            <Button
                                variant="outline"
                                onClick={() => setRetractOpen(true)}
                            >
                                Tarik
                            </Button>
                        )}
                    </div>
                </div>

                {publishErrors.length > 0 && (
                    <div className="border-destructive bg-destructive/10 text-destructive rounded-md border p-4 text-sm">
                        <p className="font-medium">
                            Rapor belum dapat diterbitkan:
                        </p>
                        <ul className="list-disc pl-5">
                            {publishErrors.map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    </div>
                )}
                {publishWarnings.length > 0 && (
                    <div className="rounded-md border p-4 text-sm">
                        <p className="font-medium">
                            Diterbitkan dengan catatan (nilai catatan wali kelas
                            belum lengkap):
                        </p>
                        <ul className="text-muted-foreground list-disc pl-5">
                            {publishWarnings.map((warning) => (
                                <li key={warning.student}>{warning.student}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* Finalization checklist */}
                <div className="rounded-md border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted text-muted-foreground font-medium">
                            <tr className="border-b">
                                <th className="px-4 py-3">Mata Pelajaran</th>
                                <th className="px-4 py-3">Guru</th>
                                <th className="px-4 py-3 text-center">
                                    Finalisasi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {checklist.map((item) => (
                                <tr key={item.subject} className="border-b">
                                    <td className="px-4 py-2">
                                        {item.subject}
                                    </td>
                                    <td className="px-4 py-2">
                                        {item.teacher ?? '—'}
                                    </td>
                                    <td className="px-4 py-2 text-center">
                                        <Badge
                                            variant={
                                                item.finalized
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {item.finalized
                                                ? 'Selesai'
                                                : 'Belum'}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Students */}
                <div className="rounded-md border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted text-muted-foreground font-medium">
                            <tr className="border-b">
                                <th className="px-4 py-3">Nama Siswa</th>
                                <th className="px-4 py-3 text-center">
                                    Kelengkapan Nilai
                                </th>
                                <th className="px-4 py-3 text-center">
                                    Catatan
                                </th>
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {students.map((student) => (
                                <tr key={student.id} className="border-b">
                                    <td className="px-4 py-2">
                                        {student.name}
                                    </td>
                                    <td className="px-4 py-2 text-center">
                                        <Badge
                                            variant={
                                                student.complete
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {student.subjects_with_results}/
                                            {student.subjects_total}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-2 text-center">
                                        <Badge
                                            variant={
                                                student.entries.homeroom_notes
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {student.entries.homeroom_notes
                                                ? 'Ada'
                                                : 'Kosong'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-2 text-right">
                                        <div className="flex justify-end gap-2">
                                            {can.edit && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        openEntry(student)
                                                    }
                                                >
                                                    <ClipboardCheck className="h-4 w-4" />
                                                    Catatan
                                                </Button>
                                            )}
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    openPreview(student)
                                                }
                                            >
                                                <FileText className="h-4 w-4" />
                                                Pratinjau
                                            </Button>
                                            {publication !== null && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <a
                                                        href={pdf.url(
                                                            {
                                                                school_class:
                                                                    school_class.id,
                                                                student:
                                                                    student.id,
                                                            },
                                                            {
                                                                query: {
                                                                    semester:
                                                                        String(
                                                                            semester.id,
                                                                        ),
                                                                },
                                                            },
                                                        )}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        <FileText className="h-4 w-4" />
                                                        PDF
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {students.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground px-4 py-6 text-center"
                                    >
                                        <Users className="mx-auto mb-2 h-6 w-6" />
                                        Belum ada siswa terdaftar.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Entry dialog */}
            <Dialog
                open={entryStudent !== null}
                onOpenChange={(open) => !open && setEntryStudent(null)}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            Catatan Wali Kelas — {entryStudent?.name}
                        </DialogTitle>
                        <DialogDescription>
                            {semester.number === 2
                                ? 'Semester Genap: isi catatan dan keputusan kenaikan.'
                                : 'Catatan hanya tersimpan untuk semester ini.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={saveEntry} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="homeroom_notes">
                                Catatan Wali Kelas (maks. 1000)
                            </Label>
                            <textarea
                                id="homeroom_notes"
                                value={entryForm.data.homeroom_notes}
                                onChange={(e) =>
                                    entryForm.setData(
                                        'homeroom_notes',
                                        e.target.value,
                                    )
                                }
                                maxLength={1000}
                                rows={4}
                                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                            />
                            {entryForm.errors.homeroom_notes && (
                                <p className="text-destructive text-sm">
                                    {entryForm.errors.homeroom_notes}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="extracurricular_notes">
                                Ekstrakurikuler (maks. 500)
                            </Label>
                            <textarea
                                id="extracurricular_notes"
                                value={entryForm.data.extracurricular_notes}
                                onChange={(e) =>
                                    entryForm.setData(
                                        'extracurricular_notes',
                                        e.target.value,
                                    )
                                }
                                maxLength={500}
                                rows={2}
                                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                            />
                            {entryForm.errors.extracurricular_notes && (
                                <p className="text-destructive text-sm">
                                    {entryForm.errors.extracurricular_notes}
                                </p>
                            )}
                        </div>
                        {semester.number === 2 && (
                            <div className="space-y-2">
                                <Label htmlFor="promotion_decision">
                                    Keputusan
                                </Label>
                                <Select
                                    value={entryForm.data.promotion_decision}
                                    onValueChange={(value) =>
                                        entryForm.setData(
                                            'promotion_decision',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="promotion_decision"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Pilih keputusan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="promoted">
                                            Naik Kelas
                                        </SelectItem>
                                        <SelectItem value="retained">
                                            Tinggal Kelas
                                        </SelectItem>
                                        <SelectItem value="graduated">
                                            Lulus
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {entryForm.errors.promotion_decision && (
                                    <p className="text-destructive text-sm">
                                        {entryForm.errors.promotion_decision}
                                    </p>
                                )}
                            </div>
                        )}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEntryStudent(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={entryForm.processing}
                            >
                                {entryForm.processing && (
                                    <LoaderCircle className="h-4 w-4 animate-spin" />
                                )}
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Preview dialog */}
            <Dialog
                open={
                    previewPayload !== null ||
                    previewLoading ||
                    previewError !== null
                }
                onOpenChange={(open) => {
                    if (!open) {
                        setPreviewPayload(null);
                        setPreviewError(null);
                        setPreviewStudentId(null);
                    }
                }}
            >
                <DialogContent className="max-h-[80vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            Pratinjau Rapor —{' '}
                            {previewPayload?.student.name ?? '...'}
                        </DialogTitle>
                        <DialogDescription>
                            Cuplikan rapor yang akan diterbitkan.
                        </DialogDescription>
                    </DialogHeader>
                    {previewStudentId !== null && publication !== null && (
                        <div className="flex justify-end">
                            <Button asChild variant="outline" size="sm">
                                <a
                                    href={previewPdf.url(
                                        {
                                            school_class: school_class.id,
                                            student: previewStudentId,
                                        },
                                        {
                                            query: {
                                                semester: String(semester.id),
                                            },
                                        },
                                    )}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <FileText className="h-4 w-4" />
                                    Unduh PDF
                                </a>
                            </Button>
                        </div>
                    )}
                    {previewLoading && (
                        <p className="text-muted-foreground text-sm">
                            Memuat pratinjau…
                        </p>
                    )}
                    {previewError && (
                        <p className="text-destructive text-sm">
                            {previewError}
                        </p>
                    )}
                    {previewPayload && (
                        <ReportCardView payload={previewPayload} />
                    )}
                </DialogContent>
            </Dialog>
            {/* Retract dialog */}
            <Dialog
                open={retractOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setRetractOpen(false);
                        setRetractError(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Tarik Rapor</DialogTitle>
                        <DialogDescription>
                            Versi terbit akan disembunyikan dari orang tua dan
                            siswa. Alasan wajib diisi dan dicatat audit.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="retract_reason">Alasan</Label>
                        <textarea
                            id="retract_reason"
                            value={retractReason}
                            onChange={(e) => setRetractReason(e.target.value)}
                            rows={3}
                            maxLength={255}
                            className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        />
                        {retractError && (
                            <p className="text-destructive text-sm">
                                {retractError}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRetractOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            onClick={handleRetract}
                            disabled={retracting}
                        >
                            {retracting && (
                                <LoaderCircle className="h-4 w-4 animate-spin" />
                            )}
                            Tarik Rapor
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ReportCards.layout = {
    breadcrumbs: [
        {
            title: 'Rapor Kelas',
            href: '/classes/report-cards',
        },
    ],
};
