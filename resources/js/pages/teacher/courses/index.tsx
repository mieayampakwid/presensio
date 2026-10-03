import { Head, Link } from '@inertiajs/react';
import { BookOpen, ChevronRight, GraduationCap, Users } from 'lucide-react';
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
};

type Props = {
    courses: CourseRow[];
    activeYear?: {
        id: number;
        name: string;
        is_active: boolean;
    } | null;
};

export default function CoursesIndex({ courses, activeYear }: Props) {
    return (
        <>
            <Head title="My Courses" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <Heading
                            title="My Courses"
                            description={`Mata pelajaran yang Anda ampu pada Tahun Ajaran ${activeYear?.name ?? '-'}.`}
                        />
                    </div>
                </div>

                {courses.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center">
                        <BookOpen className="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 className="mt-4 text-base font-semibold">
                            Belum Ada Mata Pelajaran
                        </h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Anda belum ditugaskan mengajar mata pelajaran apapun pada tahun ajaran aktif ini.
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {courses.map((course) => (
                            <div
                                key={course.id}
                                className="flex flex-col justify-between rounded-lg border bg-card p-5 shadow-xs transition-shadow hover:shadow-md"
                            >
                                <div className="space-y-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <Badge variant="outline" className="font-mono text-xs">
                                                {course.subject_code}
                                            </Badge>
                                            <h3 className="mt-1 text-lg font-semibold text-foreground">
                                                {course.subject_name}
                                            </h3>
                                        </div>
                                        <Badge variant="secondary">
                                            {course.class_name}
                                        </Badge>
                                    </div>

                                    <div className="space-y-1 text-xs text-muted-foreground">
                                        <div className="flex items-center gap-1.5">
                                            <GraduationCap className="h-4 w-4" />
                                            <span>KKTP: <strong className="text-foreground">{course.passing_threshold}</strong></span>
                                        </div>
                                        <div className="flex items-center gap-1.5">
                                            <Users className="h-4 w-4" />
                                            <span>{course.students_count} Siswa Terdaftar</span>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-5 pt-3 border-t">
                                    <Button asChild variant="outline" size="sm" className="w-full justify-between">
                                        <Link href={`/courses/${course.id}`}>
                                            <span>Buka Kelas</span>
                                            <ChevronRight className="h-4 w-4" />
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
            title: 'My Courses',
            href: '/courses',
        },
    ],
};
