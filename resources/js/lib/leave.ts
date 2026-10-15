export const LEAVE_STATUS_BADGES: Record<
    string,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    pending: { label: 'Pending', variant: 'outline' },
    approved: { label: 'Approved', variant: 'default' },
    rejected: { label: 'Rejected', variant: 'destructive' },
    cancelled: { label: 'Cancelled', variant: 'secondary' },
};

export const LEAVE_TYPE_LABELS: Record<string, string> = {
    sick: 'Sakit',
    leave: 'Izin',
    annual_leave: 'Cuti',
    official_duty: 'Dinas Luar',
};
