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
    preview,
    show,
    updateEntry,
} from '@/actions/App/Http/Controllers/Teacher/ReportCardWorkspaceController';

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
    can: { edit: boolean };
};

type PreviewPayload = {
    school: {
        name: string;
        logo: { path: string | null; content_base64: string | null };
    };
    homeroom_teacher: string | null;
    class: { name: string; phase: string | null };
    student: {
        name: string;
        student_number: string | null;
        address: string | null;
    };
    subjects: {
        name: string;
        group_label: string;
        final_score: number | null;
        description: string | null;
        from_class: string | null;
    }[];
    attendance: { sick: number; leave: number; absent: number };
    notes: { homeroom: string | null; extracurricular: string | null };
    decision: { value: string; label: string } | null;
    complete: boolean;
};

export default function ReportCards({
    school_class,
    semester,
    semesters,
    checklist,
    students,
    can,
}: Props) {
    const [entryStudent, setEntryStudent] = useState<StudentEntry | null>(null);
    const [previewPayload, setPreviewPayload] = useState<PreviewPayload | null>(
        null,
    );
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);

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
            setPreviewPayload((await response.json()) as PreviewPayload);
        } catch {
            setPreviewError(
                'Tidak dapat terhubung ke server. Periksa koneksi dan coba lagi.',
            );
        } finally {
            setPreviewLoading(false);
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
                </div>

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
                        <div className="space-y-4 text-sm">
                            <div>
                                <p className="font-medium">
                                    {previewPayload.school.name}
                                </p>
                                <p className="text-muted-foreground">
                                    Kelas {previewPayload.class.name} · Fase{' '}
                                    {previewPayload.class.phase ?? '—'} ·{' '}
                                    {previewPayload.homeroom_teacher ?? '—'}
                                </p>
                            </div>
                            <table className="w-full text-left">
                                <thead className="bg-muted text-muted-foreground">
                                    <tr>
                                        <th className="px-2 py-1">
                                            Mata Pelajaran
                                        </th>
                                        <th className="px-2 py-1 text-center">
                                            Nilai
                                        </th>
                                        <th className="px-2 py-1">
                                            Capaian Kompetensi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {previewPayload.subjects.map((subject) => (
                                        <tr
                                            key={subject.name}
                                            className="border-b"
                                        >
                                            <td className="px-2 py-1">
                                                {subject.name}
                                                {subject.from_class && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        (dari kelas{' '}
                                                        {subject.from_class})
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-2 py-1 text-center">
                                                {subject.final_score ?? '—'}
                                            </td>
                                            <td className="px-2 py-1">
                                                {subject.description ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <p className="text-muted-foreground">
                                Sakit {previewPayload.attendance.sick} · Izin{' '}
                                {previewPayload.attendance.leave} · Tanpa
                                Keterangan {previewPayload.attendance.absent}
                            </p>
                            {previewPayload.notes.homeroom && (
                                <p>Catatan: {previewPayload.notes.homeroom}</p>
                            )}
                            {previewPayload.notes.extracurricular && (
                                <p>
                                    Ekstrakurikuler:{' '}
                                    {previewPayload.notes.extracurricular}
                                </p>
                            )}
                            {previewPayload.decision && (
                                <p>
                                    Keputusan: {previewPayload.decision.label}
                                </p>
                            )}
                            {!previewPayload.complete && (
                                <p className="text-destructive">
                                    Rapor belum lengkap — ada nilai yang belum
                                    ada.
                                </p>
                            )}
                        </div>
                    )}
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
