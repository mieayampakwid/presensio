import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type ClassSubjectRow = {
    id: number;
    subject_name: string;
    subject_code: string;
    subject_group: string;
    teacher_name: string;
};

type SchoolClassData = {
    id: number;
    name: string;
    grade_level: number;
    curriculum: string;
    academic_year?: {
        id: number;
        name: string;
        is_active: boolean;
    } | null;
    teacher?: {
        id: number;
        name: string;
    } | null;
};

type Props = {
    schoolClass: SchoolClassData;
    classSubjects: ClassSubjectRow[];
};

export default function ShowClassSubjects({
    schoolClass,
    classSubjects,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('classes.index.title'), href: '/classes' },
            { title: t('classes.subjects.breadcrumb') },
        ],
    });

    return (
        <>
            <Head
                title={t('classes.subjects.title', {
                    name: schoolClass.name,
                })}
            />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <Button variant="ghost" size="sm" asChild>
                                <Link href="/classes">
                                    <ChevronLeft className="h-4 w-4" />
                                    {t('classes.index.title')}
                                </Link>
                            </Button>
                        </div>
                        <Heading
                            title={t('classes.subjects.title', {
                                name: schoolClass.name,
                            })}
                            description={t('classes.subjects.description', {
                                year: schoolClass.academic_year?.name ?? '-',
                                teacher:
                                    schoolClass.teacher?.name ??
                                    t('classes.subjects.teacher_not_set'),
                            })}
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    {t('classes.subjects.columns.code')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('classes.subjects.columns.subject')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('classes.subjects.columns.group')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('classes.subjects.columns.teacher')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {classSubjects.map((item) => (
                                <tr key={item.id} className="border-t">
                                    <td className="px-4 py-3 font-mono font-medium">
                                        <Badge variant="outline">
                                            {item.subject_code}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {item.subject_name}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {item.subject_group}
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {item.teacher_name}
                                    </td>
                                </tr>
                            ))}

                            {classSubjects.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('classes.subjects.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
