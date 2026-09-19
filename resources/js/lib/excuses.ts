export const EXCUSE_STATUS_BADGES: Record<
    string,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    pending: { label: 'Pending', variant: 'outline' },
    approved: { label: 'Approved', variant: 'default' },
    rejected: { label: 'Rejected', variant: 'destructive' },
};

export const EXCUSE_TYPE_LABELS: Record<string, string> = {
    sick: 'Sick (Sakit)',
    leave: 'Leave (Izin)',
};
