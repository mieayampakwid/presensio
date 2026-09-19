export const STATUS_BADGES: Record<
    string,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    present: { label: 'Present', variant: 'default' },
    late: { label: 'Late', variant: 'outline' },
    absent: { label: 'Absent', variant: 'destructive' },
    sick: { label: 'Sick', variant: 'secondary' },
    leave: { label: 'Leave', variant: 'secondary' },
};

export const METHOD_LABELS: Record<string, string> = {
    rfid: 'RFID',
    dynamic_qr: 'QR',
    manual_override: 'Manual override',
};
