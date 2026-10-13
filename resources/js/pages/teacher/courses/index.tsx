import { Head, Link } from '@inertiajs/react';
import {
    BookOpen,
    ChevronRight,
    FileSpreadsheet,
    GraduationCap,
    UserRound,
    Users,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type CourseRow = {
    id: number;
    class_id: number;
    subject_id: number;
    teacher_id: number;
    passing_threshold: string;
    class_name: string;
    grade_level: number;
    subject_name: string;
    subject_code: string;
    subject_group: string;
    students_count: number;
    teacher_name?: string;
};

type Props = {
    courses: CourseRow[];
    activeYear?: {
        id: number;
        name: string;
        is_active: boolean;
    } | null;
    isAdmin?: boolean;
};

export default function CoursesIndex({ courses, activeYear, isAdmin }: Props) {
    const title = isAdmin ? 'Courses' : 'My Courses';
    const description = isAdmin
        ? `Daftar seluruh mata pelajaran dan penugasan kelas pada Tahun Ajaran ${activeYear?.name ?? '-'}.`
        : `Mata pelajaran yang Anda ampu pada Tahun Ajaran ${activeYear?.name ?? '-'}.`;

    return (
        <>
            <Head title={title} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <Heading title={title} description={description} />
                    </div>
                </div>

                {courses.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center">
                        <BookOpen className="text-muted-foreground mx-auto h-12 w-12" />
                        <h3 className="mt-4 text-base font-semibold">
                            Belum Ada Mata Pelajaran
                        </h3>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {isAdmin
                                ? 'Belum ada mata pelajaran yang ditugaskan ke kelas manapun pada tahun ajaran aktif ini.'
                                : 'Anda belum ditugaskan mengajar mata pelajaran apapun pada tahun ajaran aktif ini.'}
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {courses.map((course) => (
                            <div
                                key={course.id}
                                className="bg-card flex flex-col justify-between rounded-lg border p-5 shadow-xs transition-shadow hover:shadow-md"
                            >
                                <div className="space-y-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <Badge
                                                variant="outline"
                                                className="font-mono text-xs"
                                            >
                                                {course.subject_code}
                                            </Badge>
                                            <h3 className="text-foreground mt-1 text-lg font-semibold">
                                                {course.subject_name}
                                            </h3>
                                        </div>
                                        <Badge variant="secondary">
                                            {course.class_name}
                                        </Badge>
                                    </div>

                                    <div className="text-muted-foreground space-y-1 text-xs">
                                        <div className="flex items-center gap-1.5">
                                            <UserRound className="h-4 w-4" />
                                            <span>
                                                Guru:{' '}
                                                <strong className="text-foreground">
                                                    {course.teacher_name ?? '-'}
                                                </strong>
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <GraduationCap className="h-4 w-4" />
                                            <span>
                                                KKTP:{' '}
                                                <strong className="text-foreground">
                                                    {course.passing_threshold}
                                                </strong>
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <Users className="h-4 w-4" />
                                            <span>
                                                {course.students_count} Siswa
                                                Terdaftar
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-5 flex items-center gap-2 border-t pt-3">
                                    <Button
                                        asChild
                                        size="sm"
                                        className="flex-1"
                                    >
                                        <Link
                                            href={`/courses/${course.id}/gradebook`}
                                        >
                                            <FileSpreadsheet className="mr-1.5 h-4 w-4" />
                                            <span>Buku Nilai</span>
                                        </Link>
                                    </Button>
                                    <Button asChild variant="outline" size="sm">
                                        <Link href={`/courses/${course.id}`}>
                                            <span>Buka Kelas</span>
                                            <ChevronRight className="ml-1 h-3.5 w-3.5" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

CoursesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Courses',
            href: '/courses',
        },
    ],
};
