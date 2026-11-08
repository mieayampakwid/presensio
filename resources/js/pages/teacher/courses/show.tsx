import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import {
    Award,
    ChevronLeft,
    ClipboardList,
    FileSpreadsheet,
    Users,
} from 'lucide-react';
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
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('grading.courses.title'),
                href: '/courses',
            },
            {
                title: 'Course Workspace',
            },
        ],
    });

    return (
        <>
            <Head title={`${course.subject_name} — ${course.class_name}`} />

            <div className="space-y-6 p-4">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/courses">
                            <ChevronLeft className="h-4 w-4" />
                            {t('grading.courses.title')}
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant="outline"
                                className="font-mono text-xs"
                            >
                                {course.subject_code}
                            </Badge>
                            <Badge variant="secondary">
                                {course.class_name}
                            </Badge>
                            <Badge variant={canWrite ? 'default' : 'secondary'}>
                                {canWrite ? 'Pengajar Utama' : 'Akses Baca'}
                            </Badge>
                        </div>
                        <Heading
                            title={course.subject_name}
                            description={`Guru: ${course.teacher_name} | KKTP: ${course.passing_threshold}`}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/courses/${course.id}/assessments`}>
                                <ClipboardList className="mr-1.5 h-4 w-4" />
                                <span>
                                    {t('grading.courses.manage_assessments')}
                                </span>
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/courses/${course.id}/results`}>
                                <Award className="mr-1.5 h-4 w-4" />
                                <span>
                                    {t('grading.courses.report_achievements')}
                                </span>
                            </Link>
                        </Button>
                        <Button size="sm" asChild>
                            <Link href={`/courses/${course.id}/gradebook`}>
                                <FileSpreadsheet className="mr-1.5 h-4 w-4" />
                                <span>{t('grading.courses.gradebook')}</span>
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Users className="text-muted-foreground h-5 w-5" />
                            <h3 className="text-base font-semibold">
                                {t('grading.courses.students_list', {
                                    count: students.length,
                                })}
                            </h3>
                        </div>
                    </div>

                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="w-12 px-4 py-3 text-center font-medium">
                                        {t('grading.courses.no')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('grading.courses.nisn_nipd')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('grading.courses.student_name')}
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        {t('grading.courses.gender')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {students.map((student, idx) => (
                                    <tr key={student.id} className="border-t">
                                        <td className="text-muted-foreground px-4 py-3 text-center">
                                            {idx + 1}
                                        </td>
                                        <td className="px-4 py-3 font-mono">
                                            {student.student_number ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 font-medium">
                                            {student.full_name}
                                        </td>
                                        <td className="px-4 py-3 text-xs uppercase">
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
                                            {t(
                                                'grading.courses.empty_students',
                                            )}
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
