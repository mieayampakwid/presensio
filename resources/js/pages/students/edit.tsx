import { Head, setLayoutProps } from '@inertiajs/react';
import { UserMinus } from 'lucide-react';
import { useState } from 'react';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import StudentForm from './student-form';
import WithdrawalDialog from './withdrawal-dialog';

type ClassOption = {
    id: number;
    name: string;
};

type GuardianOption = {
    id: number;
    name: string;
    phone_number: string;
};

type StudentRow = {
    id: number;
    full_name: string;
    nickname: string | null;
    dob: string;
    student_number: string | null;
    class_id: number | null;
    gender: string;
    birth_place: string | null;
    religion: string | null;
    address: string | null;
};

type Props = {
    student: StudentRow;
    classes: ClassOption[];
    guardians: GuardianOption[];
    current_guardian_ids?: number[];
    current_guardians?: { id: number; relationship_type: string }[];
};

export default function EditStudent({
    student,
    classes,
    guardians,
    current_guardian_ids,
    current_guardians,
}: Props) {
    const { t } = useTranslation();
    const [withdrawing, setWithdrawing] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('students.index.title'),
                href: StudentController.index().url,
            },
            {
                title: t('common.edit'),
            },
        ],
    });

    return (
        <>
            <Head
                title={t('students.edit.head', {
                    name: student.full_name,
                })}
            />

            <div className="space-y-6 p-4">
                <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title={t('students.edit.title')}
                        description={t('students.edit.description')}
                    />

                    {student.class_id && (
                        <Button
                            type="button"
                            variant="outline"
                            className="border-destructive/30 text-destructive hover:bg-destructive/10 hover:text-destructive"
                            onClick={() => setWithdrawing(true)}
                        >
                            <UserMinus className="mr-2 h-4 w-4" />
                            {t('students.withdrawal.action')}
                        </Button>
                    )}
                </div>

                <StudentForm
                    action={StudentController.update.form({
                        student: student.id,
                    })}
                    cancelUrl={StudentController.index().url}
                    submitLabel={t('common.save_changes')}
                    defaults={{
                        fullName: student.full_name,
                        nickname: student.nickname ?? '',
                        dob: student.dob,
                        gender: student.gender ?? 'L',
                        birthPlace: student.birth_place ?? '',
                        religion: student.religion ?? '',
                        address: student.address ?? '',
                        studentNumber: student.student_number ?? '',
                        classId: student.class_id
                            ? String(student.class_id)
                            : '',
                    }}
                    classes={classes}
                    guardians={guardians}
                    currentGuardianIds={current_guardian_ids}
                    currentGuardians={current_guardians}
                />
            </div>

            <WithdrawalDialog
                student={
                    withdrawing
                        ? {
                              id: student.id,
                              full_name: student.full_name,
                              student_number: student.student_number,
                              school_class: student.class_id
                                  ? (classes.find(
                                        (c) => c.id === student.class_id,
                                    ) ?? null)
                                  : null,
                          }
                        : null
                }
                open={withdrawing}
                onOpenChange={setWithdrawing}
            />
        </>
    );
}
