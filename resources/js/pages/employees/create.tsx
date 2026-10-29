import { Head } from '@inertiajs/react';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import Heading from '@/components/heading';
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
    return (
        <>
            <Head title="Tambah Pegawai" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Tambah Pegawai"
                    description="Tambahkan data master guru atau tenaga kependidikan."
                />

                <EmployeeForm
                    action={EmployeeController.store.form()}
                    cancelUrl={EmployeeController.index().url}
                    submitLabel="Simpan Pegawai"
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

CreateEmployee.layout = {
    breadcrumbs: [
        {
            title: 'Pegawai',
            href: EmployeeController.index().url,
        },
        {
            title: 'Tambah',
        },
    ],
};
