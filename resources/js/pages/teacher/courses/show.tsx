import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type StudentRow = {
    id: number;
    full_name: string;
    student_number: string | null;
    gender: string;
};

type CourseData = {
    id: number;
    class_id: number;
    subject_id: number;
    passing_threshold: string;
    class_name: string;
    subject_name: string;
    subject_code: string;
    teacher_name: string;
};

type Props = {
    course: CourseData;
    students: StudentRow[];
    canWrite: boolean;
};

export default function CourseShow({ course, students, canWrite }: Props) {
    return (
        <>
            <Head title={`${course.subject_name} — ${course.class_name}`} />

            <div className="space-y-6 p-4">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/courses">
                            <ChevronLeft className="h-4 w-4" />
                            My Courses
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <Badge variant={canWrite ? 'default' : 'secondary'} className="mt-2">
                            {canWrite ? 'Pengajar Utama' : 'Akses Baca'}
                        </Badge>
                    <div>
                        <div className="flex items-center gap-2">
                            <Badge variant="outline" className="font-mono text-xs">
                                {course.subject_code}
                            </Badge>
                            <Badge variant="secondary">
                                {course.class_name}
                            </Badge>
                        </div>
                        <Heading
                            title={course.subject_name}
                            description={`Guru: ${course.teacher_name} | KKTP: ${course.passing_threshold}`}
                        />
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Users className="h-5 w-5 text-muted-foreground" />
                            <h3 className="text-base font-semibold">
                                Daftar Siswa ({students.length})
                            </h3>
                        </div>
                    </div>

                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="w-12 px-4 py-3 font-medium text-center">No</th>
                                    <th className="px-4 py-3 font-medium">NISN / No. Induk</th>
                                    <th className="px-4 py-3 font-medium">Nama Siswa</th>
                                    <th className="px-4 py-3 font-medium">Jenis Kelamin</th>
                                </tr>
                            </thead>
                            <tbody>
                                {students.map((student, idx) => (
                                    <tr key={student.id} className="border-t">
                                        <td className="px-4 py-3 text-center text-muted-foreground">
                                            {idx + 1}
                                        </td>
                                        <td className="px-4 py-3 font-mono">
                                            {student.student_number ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 font-medium">
                                            {student.full_name}
                                        </td>
                                        <td className="px-4 py-3 uppercase text-xs">
                                            {student.gender}
                                        </td>
                                    </tr>
                                ))}

                                {students.length === 0 && (
                                    <tr className="border-t">
                                        <td
                                            colSpan={4}
                                            className="text-muted-foreground px-4 py-8 text-center"
                                        >
                                            Tidak ada siswa yang terdaftar aktif di kelas ini hari ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

CourseShow.layout = {
    breadcrumbs: [
        {
            title: 'My Courses',
            href: '/courses',
        },
        {
            title: 'Course Workspace',
        },
    ],
};
