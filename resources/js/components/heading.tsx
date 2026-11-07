import { cn } from '@/lib/utils';

export default function Heading({
    title,
    description,
    variant = 'default',
    as,
    className,
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    as?: 'h1' | 'h2' | 'h3';
    className?: string;
}) {
    const Tag = as ?? (variant === 'small' ? 'h2' : 'h1');

    return (
        <header className={cn('space-y-0.5', className)}>
            <Tag
                className={
                    variant === 'small'
                        ? 'text-base font-medium'
                        : 'text-xl font-semibold tracking-tight'
                }
            >
                {title}
            </Tag>
            {description && (
                <p className="text-muted-foreground text-sm">{description}</p>
            )}
        </header>
    );
}
