import EmployeeForm from '@/pages/employees/employee-form';

type TeacherFormProps = {
    action: Record<string, unknown>;
    submitLabel: string;
    defaults: {
        name: string;
        teacherNumber: string | null;
        phoneNumber: string | null;
    };
};

export default function TeacherForm({
    action,
    submitLabel,
    defaults,
}: TeacherFormProps) {
    return (
        <EmployeeForm
            action={action}
            submitLabel={submitLabel}
            defaults={{
                name: defaults.name,
                employeeNumber: defaults.teacherNumber,
                phoneNumber: defaults.phoneNumber,
                isTeacher: true,
                isActive: true,
            }}
            lockIsTeacher={true}
        />
    );
}
