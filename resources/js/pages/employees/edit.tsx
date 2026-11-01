import { Head, setLayoutProps } from '@inertiajs/react';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import EmployeeForm from './employee-form';

type Option = { value: string; label: string };
type UserOption = { id: number; username: string };
type ShiftOption = { id: number; name: string };

type EmployeeData = {
    id: number;
    name: string;
    employee_number: string | null;
    phone_number: string | null;
    employment_type: string;
    position: string | null;
    working_days: number[] | null;
    is_active: boolean;
    is_teacher: boolean;
    user_id: number | null;
    shift_id: number | null;
};

type Props = {
    employee: EmployeeData;
    employment_types: Option[];
    operational_days: number[];
    available_users: UserOption[];
    shifts: ShiftOption[];
};

export default function EditEmployee({
    employee,
    employment_types,
    operational_days,
    available_users,
    shifts,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('employees.index.title'),
                href: EmployeeController.index().url,
            },
            {
                title: t('employees.edit.breadcrumb'),
            },
        ],
    });

    return (
        <>
            <Head
                title={t('employees.edit.head_title', { name: employee.name })}
            />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('employees.edit.title')}
                    description={t('employees.edit.description')}
                />

                <EmployeeForm
                    action={EmployeeController.update.form({
                        employee: employee.id,
                    })}
                    cancelUrl={EmployeeController.index().url}
                    submitLabel={t('common.save_changes')}
                    defaults={{
                        name: employee.name,
                        employeeNumber: employee.employee_number,
                        phoneNumber: employee.phone_number,
                        employmentType: employee.employment_type,
                        position: employee.position,
                        workingDays: employee.working_days,
                        isActive: employee.is_active,
                        isTeacher: employee.is_teacher,
                        userId: employee.user_id,
                        shiftId: employee.shift_id,
                    }}
                    employmentTypes={employment_types}
                    operationalDays={operational_days}
                    availableUsers={available_users}
                    shifts={shifts}
                />
            </div>
        </>
    );
}
