import { Fragment } from 'react';
import { Badge } from '@/components/ui/badge';

export type ReportCardPayload = {
    school: {
        name: string;
        npsn: string | null;
        address: string | null;
        logo: { path: string | null; content_base64: string | null };
    };
    principal: { name: string | null; nip: string | null };
    homeroom_teacher: string | null;
    class: { name: string; grade_level: number; phase: string | null };
    semester: {
        name: string;
        number: number;
        academic_year: string;
        starts_at: string;
        ends_at: string;
    };
    student: {
        name: string;
        student_number: string | null;
        address: string | null;
    };
    version: number;
    published_at: string;
    subjects: {
        subject_id: number;
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

export default function ReportCardView({
    payload,
}: {
    payload: ReportCardPayload;
}) {
    const subjects = payload.subjects;
    let lastGroup: string | null = null;

    const logoBase64 = payload.school.logo.content_base64;
    const logoPath = payload.school.logo.path ?? '';
    const logoMime = /\.(jpe?g)$/i.test(logoPath) ? 'image/jpeg' : 'image/png';
    const logoSrc =
        logoBase64 === null ? null : `data:${logoMime};base64,${logoBase64}`;

    const decisionLine =
        payload.decision === null
            ? null
            : payload.decision.value === 'promoted'
              ? `Naik ke kelas ${payload.class.grade_level + 1}`
              : payload.decision.value === 'retained'
                ? `Tinggal di kelas ${payload.class.grade_level}`
                : 'Lulus';

    return (
        <div className="space-y-6 text-sm">
            <div className="flex items-center gap-4">
                {logoSrc !== null && (
                    <img
                        src={logoSrc}
                        alt="Logo sekolah"
                        className="h-16 w-16 object-contain"
                    />
                )}
                <div className="flex-1 text-center">
                    <p className="text-lg font-bold">{payload.school.name}</p>
                    {payload.school.address && (
                        <p className="text-muted-foreground text-xs">
                            {payload.school.address}
                        </p>
                    )}
                </div>
            </div>

            <p className="text-center text-base font-bold">
                LAPORAN HASIL BELAJAR
            </p>

            <div className="grid grid-cols-2 gap-x-8 gap-y-1">
                <p>
                    Nama : <strong>{payload.student.name}</strong>
                </p>
                <p>
                    Kelas : <strong>{payload.class.name}</strong>
                </p>
                <p>NIS : {payload.student.student_number ?? '—'}</p>
                <p>Fase : {payload.class.phase ?? '—'}</p>
                <p>NISN : —</p>
                <p>Semester : {payload.semester.name}</p>
                <p />
                <p>Tahun Ajaran : {payload.semester.academic_year}</p>
            </div>

            <table className="w-full text-left text-sm">
                <thead className="bg-muted text-muted-foreground font-medium">
                    <tr className="border-b">
                        <th className="w-12 px-3 py-2">No</th>
                        <th className="px-3 py-2">Mata Pelajaran</th>
                        <th className="px-3 py-2 text-center">Nilai Akhir</th>
                        <th className="px-3 py-2">Capaian Kompetensi</th>
                    </tr>
                </thead>
                <tbody>
                    {subjects.map((subject, index) => {
                        const groupHeading =
                            subject.group_label !== lastGroup
                                ? ((lastGroup = subject.group_label),
                                  subject.group_label)
                                : null;

                        return (
                            <Fragment key={subject.subject_id}>
                                {groupHeading !== null && (
                                    <tr className="bg-muted">
                                        <td
                                            colSpan={4}
                                            className="px-3 py-2 font-medium"
                                        >
                                            {groupHeading}
                                        </td>
                                    </tr>
                                )}
                                <tr className="border-b">
                                    <td className="px-3 py-2 text-center">
                                        {index + 1}
                                    </td>
                                    <td className="px-3 py-2">
                                        {subject.name}
                                        {subject.from_class && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                (dari kelas {subject.from_class}
                                                )
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        {subject.final_score ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {subject.description ?? '—'}
                                    </td>
                                </tr>
                            </Fragment>
                        );
                    })}
                </tbody>
            </table>

            <div className="grid grid-cols-2 gap-6">
                <div>
                    <p className="font-semibold">Ekstrakurikuler</p>
                    <p>{payload.notes.extracurricular ?? '—'}</p>
                </div>
                <div>
                    <p className="font-semibold">Ketidakhadiran</p>
                    <p>Sakit: {payload.attendance.sick} hari</p>
                    <p>Izin: {payload.attendance.leave} hari</p>
                    <p>Tanpa Keterangan: {payload.attendance.absent} hari</p>
                </div>
            </div>

            <div>
                <p className="font-semibold">Catatan Wali Kelas</p>
                <p>{payload.notes.homeroom ?? '—'}</p>
            </div>

            {decisionLine !== null && (
                <div>
                    <p className="font-semibold">Keputusan</p>
                    <p>{decisionLine}</p>
                </div>
            )}

            <div className="grid grid-cols-3 gap-4 pt-6 text-center">
                <div>
                    <p>Orang Tua/Wali</p>
                </div>
                <div>
                    <p>Wali Kelas</p>
                    <p className="mt-12 font-semibold">
                        {payload.homeroom_teacher ?? '—'}
                    </p>
                </div>
                <div>
                    <p>Kepala Sekolah</p>
                    <p className="mt-12 font-semibold">
                        {payload.principal.name ?? '—'}
                    </p>
                    <p className="text-xs">
                        NIP. {payload.principal.nip ?? '—'}
                    </p>
                </div>
            </div>

            <p className="text-muted-foreground text-center text-xs">
                <Badge variant="secondary">Versi {payload.version}</Badge>{' '}
                diterbitkan {payload.published_at}
            </p>
        </div>
    );
}
