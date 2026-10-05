import { Head } from '@inertiajs/react';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import Heading from '@/components/heading';
import EmployeeForm from './employee-form';

type Option = { value: string; label: string };
type UserOption = { id: number; username: string };

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
};

type Props = {
    employee: EmployeeData;
    employment_types: Option[];
    operational_days: number[];
    available_users: UserOption[];
};

export default function EditEmployee({
    employee,
    employment_types,
    operational_days,
    available_users,
}: Props) {
    return (
        <>
            <Head title={`Edit Pegawai - ${employee.name}`} />

            <div className="space-y-6 p-4">
                <Heading
                    title="Edit Pegawai"
                    description="Perbarui informasi guru atau tenaga kependidikan."
                />

                <EmployeeForm
                    action={EmployeeController.update({
                        employee: employee.id,
                    })}
                    submitLabel="Simpan Perubahan"
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
                    }}
                    employmentTypes={employment_types}
                    operationalDays={operational_days}
                    availableUsers={available_users}
                />
            </div>
        </>
    );
}

EditEmployee.layout = {
    breadcrumbs: [
        {
            title: 'Pegawai',
            href: EmployeeController.index().url,
        },
        {
            title: 'Edit',
        },
    ],
};
