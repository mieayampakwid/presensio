import { Head, setLayoutProps } from '@inertiajs/react';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import EmployeeForm from './employee-form';

type Option = { value: string; label: string };
type UserOption = { id: number; username: string };
type ShiftOption = { id: number; name: string };

type Props = {
    employment_types: Option[];
    operational_days: number[];
    available_users: UserOption[];
    shifts: ShiftOption[];
};

export default function CreateEmployee({
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
                title: t('employees.create.breadcrumb'),
            },
        ],
    });

    return (
        <>
            <Head title={t('employees.create.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('employees.create.title')}
                    description={t('employees.create.description')}
                />

                <EmployeeForm
                    action={EmployeeController.store.form()}
                    cancelUrl={EmployeeController.index().url}
                    submitLabel={t('employees.create.submit')}
                    defaults={{
                        name: '',
                        isTeacher: false,
                        isActive: true,
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
