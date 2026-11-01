import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useEffect } from 'react';
import PresenceBoardController from '@/actions/App/Http/Controllers/Reports/PresenceBoardController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { STATUS_BADGES } from '@/lib/attendance';

type NotInStudent = {
    full_name: string;
    status: string | null;
};

type BoardClass = {
    id: number;
    name: string;
    enrolled: number;
    in_building: number;
    checked_out: number;
    not_checked_in: number;
    not_in: NotInStudent[];
};

type Totals = {
    enrolled: number;
    in_building: number;
    checked_out: number;
    not_checked_in: number;
};

type Board = {
    classes: BoardClass[];
    totals: Totals | null;
    as_of: string;
};

type Props = {
    board: Board;
    is_school_day: boolean;
};

const REFRESH_MS = 30000;

export default function PresenceBoard({ board, is_school_day }: Props) {
    const { t } = useTranslation();

    // A wall screen — polls unconditionally, non-school days included.
    useEffect(() => {
        const refresh = setInterval(() => {
            router.reload({ only: ['board'] });
        }, REFRESH_MS);

        return () => clearInterval(refresh);
    }, []);

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('reports.presence_board.title'),
                href: PresenceBoardController.index().url,
            },
        ],
    });

    return (
        <>
            <Head title={t('reports.presence_board.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title={t('reports.presence_board.title')}
                        description={t('reports.presence_board.description')}
                    />

                    <div className="text-muted-foreground text-right text-sm">
                        <div className="text-foreground font-medium">
                            {t('reports.presence_board.as_of', {
                                time: board.as_of,
                            })}
                        </div>
                        <div>{t('reports.presence_board.auto_refresh')}</div>
                    </div>
                </div>

                {!is_school_day && (
                    <p className="text-muted-foreground rounded-lg border px-4 py-3 text-sm">
                        {t('reports.presence_board.not_school_day')}
                    </p>
                )}

                {board.totals !== null && (
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    {t(
                                        'reports.presence_board.in_building_headcount',
                                    )}
                                </CardTitle>
                                <CardTitle className="text-4xl">
                                    {board.totals.in_building}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    {t('reports.presence_board.checked_out')}
                                </CardTitle>
                                <CardTitle className="text-4xl">
                                    {board.totals.checked_out}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    {t('reports.presence_board.not_checked_in')}
                                </CardTitle>
                                <CardTitle className="text-4xl">
                                    {board.totals.not_checked_in}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    {t('reports.presence_board.enrolled')}
                                </CardTitle>
                                <CardTitle className="text-4xl">
                                    {board.totals.enrolled}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    {board.classes.map((schoolClass) => (
                        <Card key={schoolClass.id}>
                            <CardHeader className="pb-2">
                                <div className="flex items-center justify-between">
                                    <CardTitle>{schoolClass.name}</CardTitle>
                                    <Badge variant="secondary">
                                        {t(
                                            'reports.presence_board.enrolled_badge',
                                            {
                                                count: schoolClass.enrolled,
                                            },
                                        )}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-3 gap-2 text-center">
                                    <div className="bg-primary text-primary-foreground rounded-md px-2 py-3">
                                        <div className="text-3xl font-semibold">
                                            {schoolClass.in_building}
                                        </div>
                                        <div className="text-xs opacity-80">
                                            {t(
                                                'reports.presence_board.in_building',
                                            )}
                                        </div>
                                    </div>
                                    <div className="rounded-md border px-2 py-3">
                                        <div className="text-3xl font-semibold">
                                            {schoolClass.checked_out}
                                        </div>
                                        <div className="text-muted-foreground text-xs">
                                            {t(
                                                'reports.presence_board.checked_out',
                                            )}
                                        </div>
                                    </div>
                                    <div className="rounded-md border px-2 py-3">
                                        <div className="text-3xl font-semibold">
                                            {schoolClass.not_checked_in}
                                        </div>
                                        <div className="text-muted-foreground text-xs">
                                            {t(
                                                'reports.presence_board.not_checked_in',
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {schoolClass.not_in.length > 0 && (
                                    <div>
                                        <div className="text-muted-foreground mb-2 text-xs font-medium">
                                            {t(
                                                'reports.presence_board.not_yet_checked_in',
                                            )}
                                        </div>
                                        <ul className="space-y-1.5">
                                            {schoolClass.not_in.map(
                                                (student) => (
                                                    <li
                                                        key={student.full_name}
                                                        className="flex items-center justify-between gap-2 text-sm"
                                                    >
                                                        <span className="truncate">
                                                            {student.full_name}
                                                        </span>
                                                        {student.status ? (
                                                            <Badge
                                                                variant={
                                                                    STATUS_BADGES[
                                                                        student
                                                                            .status
                                                                    ]
                                                                        ?.variant ??
                                                                    'secondary'
                                                                }
                                                            >
                                                                {
                                                                    STATUS_BADGES[
                                                                        student
                                                                            .status
                                                                    ]?.label
                                                                }
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-muted-foreground text-xs">
                                                                {t(
                                                                    'reports.presence_board.no_record',
                                                                )}
                                                            </span>
                                                        )}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}

                    {board.classes.length === 0 && (
                        <p className="text-muted-foreground text-sm">
                            {t('reports.presence_board.empty')}
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}
