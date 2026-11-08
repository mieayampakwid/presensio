import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import {
    ArrowLeft,
    ClipboardCheck,
    Download,
    FileText,
    LoaderCircle,
    Printer,
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
import ReportCardBundleController from '@/actions/App/Http/Controllers/Teacher/ReportCardBundleController';

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
    bundle_available: boolean;
    can: { edit: boolean };
    accessible_classes?: { id: number; name: string }[];
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
    bundle_available,
    can,
    accessible_classes,
}: Props) {
    const { t } = useTranslation();
    setLayoutProps({
        breadcrumbs: [
            {
                title: 'Rapor Kelas',
                href: '/classes/report-cards',
            },
        ],
    });

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
    const [bundleBuilding, setBundleBuilding] = useState(false);
    const [bundleError, setBundleError] = useState<string | null>(null);

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

    const handleBuildBundle = async () => {
        setBundleBuilding(true);
        setBundleError(null);
        try {
            const response = await fetch(
                ReportCardBundleController.store.url({
                    school_class: school_class.id,
                }),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify({ semester_id: semester.id }),
                },
            );
            if (!response.ok) {
                setBundleError(
                    `Gagal menyiapkan cetak semua (HTTP ${response.status}).`,
                );
                return;
            }
            // The queued job lands on the private disk; refresh a few times
            // until the download link appears.
            let attempts = 0;
            const poll = window.setInterval(() => {
                attempts += 1;
                router.reload({
                    onFinish: () => {
                        if (attempts >= 10) {
                            window.clearInterval(poll);
                        }
                    },
                });
            }, 2000);
        } catch {
            setBundleError(
                'Tidak dapat terhubung ke server. Periksa koneksi dan coba lagi.',
            );
        } finally {
            setBundleBuilding(false);
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
                                {t('grading.report_cards.back_to_class')}
                            </Link>
                        </div>
                        <Heading
                            title={`Rapor Kelas: ${school_class.name}`}
                            description={`Ceklis kelulusan mata pelajaran dan catatan wali kelas untuk ${semester.name}`}
                        />
                    </div>
                    <div className="flex items-center gap-3">
                        {accessible_classes &&
                            accessible_classes.length > 1 && (
                                <Select
                                    value={String(school_class.id)}
                                    onValueChange={(newClassId) => {
                                        router.get(
                                            show.url(
                                                {
                                                    school_class:
                                                        Number(newClassId),
                                                },
                                                {
                                                    query: {
                                                        semester: semester.id,
                                                    },
                                                },
                                            ),
                                            {},
                                            { preserveState: false },
                                        );
                                    }}
                                >
                                    <SelectTrigger className="w-[180px]">
                                        <SelectValue placeholder="Pilih Kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {accessible_classes.map((cls) => (
                                            <SelectItem
                                                key={cls.id}
                                                value={String(cls.id)}
                                            >
                                                {cls.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
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
                                {t('grading.report_cards.table.version')}{' '}
                                {publication.version}{' '}
                                {t('grading.report_cards.table.published')}{' '}
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
                                {t('grading.report_cards.table.publish')}
                            </Button>
                        )}
                        {can.edit && publication !== null && (
                            <Button
                                variant="outline"
                                onClick={() => setRetractOpen(true)}
                            >
                                {t('grading.report_cards.table.revoke')}
                            </Button>
                        )}
                        {can.edit &&
                            publication !== null &&
                            bundle_available && (
                                <Button asChild variant="outline">
                                    <a
                                        href={ReportCardBundleController.download.url(
                                            { school_class: school_class.id },
                                            {
                                                query: {
                                                    semester: String(
                                                        semester.id,
                                                    ),
                                                },
                                            },
                                        )}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <Download className="h-4 w-4" />
                                        {t('grading.report_cards.download_all')}
                                    </a>
                                </Button>
                            )}
                        {can.edit &&
                            publication !== null &&
                            !bundle_available && (
                                <Button
                                    variant="outline"
                                    onClick={handleBuildBundle}
                                    disabled={bundleBuilding}
                                >
                                    {bundleBuilding ? (
                                        <LoaderCircle className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <Printer className="h-4 w-4" />
                                    )}
                                    {t('grading.report_cards.print_all')}
                                </Button>
                            )}
                    </div>
                </div>

                {bundleError !== null && (
                    <p className="text-destructive text-sm">{bundleError}</p>
                )}

                {publishErrors.length > 0 && (
                    <div className="border-destructive bg-destructive/10 text-destructive rounded-md border p-4 text-sm">
                        <p className="font-medium">
                            {t('grading.report_cards.unmet_blockers')}
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
                            {t('grading.report_cards.warnings_incomplete')}
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
                                <th className="px-4 py-3">
                                    {t('grading.objectives.subject')}
                                </th>
                                <th className="px-4 py-3">
                                    {t('grading.courses.teacher')}
                                </th>
                                <th className="px-4 py-3 text-center">
                                    {t('grading.results.table.finalized')}
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
                                <th className="px-4 py-3">
                                    {t(
                                        'grading.report_cards.table.student_name',
                                    )}
                                </th>
                                <th className="px-4 py-3 text-center">
                                    {t(
                                        'grading.report_cards.table.grade_completeness',
                                    )}
                                </th>
                                <th className="px-4 py-3 text-center">
                                    {t('grading.report_cards.table.notes')}
                                </th>
                                <th className="px-4 py-3 text-right">
                                    {t('grading.report_cards.table.actions')}
                                </th>
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
                                                    {t(
                                                        'grading.report_cards.table.notes',
                                                    )}
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
                                                {t(
                                                    'grading.report_cards.table.preview',
                                                )}
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
                                                        {t(
                                                            'grading.report_cards.table.pdf',
                                                        )}
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
                                        {t('grading.report_cards.table.empty')}
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
                            {t('grading.report_cards.notes_dialog.title', {
                                name: entryStudent?.name ?? '',
                            })}
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
                                {t(
                                    'grading.report_cards.notes_dialog.homeroom_notes',
                                )}
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
                                {t(
                                    'grading.report_cards.notes_dialog.extracurricular',
                                )}
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
                                    {t(
                                        'grading.report_cards.notes_dialog.decision',
                                    )}
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
                                            {t(
                                                'grading.report_cards.table.promoted',
                                            )}
                                        </SelectItem>
                                        <SelectItem value="retained">
                                            {t(
                                                'grading.report_cards.table.retained',
                                            )}
                                        </SelectItem>
                                        <SelectItem value="graduated">
                                            {t(
                                                'grading.report_cards.table.graduated',
                                            )}
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
                                {t('common.cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={entryForm.processing}
                            >
                                {entryForm.processing && (
                                    <LoaderCircle className="h-4 w-4 animate-spin" />
                                )}
                                {t('grading.report_cards.notes_dialog.save')}
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
                            {t('grading.report_cards.preview_dialog.title', {
                                name: previewPayload?.student.name ?? '...',
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'grading.report_cards.preview_dialog.description',
                            )}
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
                                    {t(
                                        'grading.report_cards.table.download_pdf',
                                    )}
                                </a>
                            </Button>
                        </div>
                    )}
                    {previewLoading && (
                        <p className="text-muted-foreground text-sm">
                            {t('grading.report_cards.preview_dialog.loading')}
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
                        <DialogTitle>
                            {t('grading.report_cards.revoke_dialog.confirm')}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'grading.report_cards.revoke_dialog.description',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="retract_reason">
                            {t('grading.report_cards.revoke_dialog.reason')}
                        </Label>
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
                            {t('common.cancel')}
                        </Button>
                        <Button
                            type="button"
                            onClick={handleRetract}
                            disabled={retracting}
                        >
                            {retracting && (
                                <LoaderCircle className="h-4 w-4 animate-spin" />
                            )}
                            {t('grading.report_cards.revoke_dialog.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
