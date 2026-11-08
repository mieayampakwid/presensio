import { Head, Form, setLayoutProps } from '@inertiajs/react';
import SchoolProfileController from '@/actions/App/Http/Controllers/Settings/SchoolProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

type SchoolProfileData = {
    school_name: string;
    npsn: string | null;
    school_address: string | null;
    school_phone: string | null;
    school_email: string | null;
    has_logo: boolean;
    principal_name: string | null;
    principal_nip: string | null;
    bank_name: string | null;
    bank_account_number: string | null;
    bank_account_holder: string | null;
    default_curriculum: string;
    default_passing_threshold?: string;
    default_transfer_account_id: number | null;
    guardian_deposit_account_id: number | null;
    cashier_default_cash_account_id: number | null;
};

type CashAccount = {
    id: number;
    code: string;
    name: string;
};

type Props = {
    profile: SchoolProfileData;
    cashAccounts: CashAccount[];
    guardianDepositAccounts: CashAccount[];
};

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

export default function SchoolProfile({
    profile,
    cashAccounts,
    guardianDepositAccounts,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('settings.nav.school_profile'),
                href: SchoolProfileController.edit().url,
            },
        ],
    });

    return (
        <>
            <Head title={t('settings.school.page_title')} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('settings.school.title')}
                    description={t('settings.school.description')}
                />

                <Form
                    {...SchoolProfileController.update.form()}
                    encType="multipart/form-data"
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <div className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="guardian_deposit_account_id">
                                    {t(
                                        'settings.school.guardian_deposit_account',
                                    )}
                                </Label>
                                <select
                                    id="guardian_deposit_account_id"
                                    name="guardian_deposit_account_id"
                                    className={SELECT_CLASS}
                                    defaultValue={
                                        profile.guardian_deposit_account_id ??
                                        ''
                                    }
                                >
                                    <option value="">
                                        {t('settings.school.unconfigured')}
                                    </option>
                                    {guardianDepositAccounts.map((account) => (
                                        <option
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.code} — {account.name}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-muted-foreground text-sm">
                                    {t('settings.school.guardian_deposit_hint')}
                                </p>
                                <InputError
                                    message={errors.guardian_deposit_account_id}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="school_name">
                                    {t('settings.school.name')}
                                </Label>
                                <Input
                                    id="school_name"
                                    name="school_name"
                                    defaultValue={profile.school_name}
                                    required
                                    placeholder={t(
                                        'settings.school.name_placeholder',
                                    )}
                                />
                                <InputError message={errors.school_name} />
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="npsn">
                                        {t('settings.school.npsn')}
                                    </Label>
                                    <Input
                                        id="npsn"
                                        name="npsn"
                                        defaultValue={profile.npsn ?? ''}
                                        placeholder={t(
                                            'settings.school.npsn_placeholder',
                                        )}
                                    />
                                    <InputError message={errors.npsn} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="default_curriculum">
                                        {t(
                                            'settings.school.default_curriculum',
                                        )}
                                    </Label>
                                    <select
                                        id="default_curriculum"
                                        name="default_curriculum"
                                        defaultValue={
                                            profile.default_curriculum ||
                                            'merdeka'
                                        }
                                        className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                    >
                                        <option value="merdeka">
                                            {t(
                                                'settings.school.curriculum_merdeka',
                                            )}
                                        </option>
                                    </select>
                                    <InputError
                                        message={errors.default_curriculum}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="default_passing_threshold">
                                        {t('settings.school.passing_threshold')}
                                    </Label>
                                    <Input
                                        id="default_passing_threshold"
                                        name="default_passing_threshold"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        defaultValue={
                                            profile.default_passing_threshold ??
                                            '75.00'
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors.default_passing_threshold
                                        }
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="school_address">
                                    {t('settings.school.address')}
                                </Label>
                                <textarea
                                    id="school_address"
                                    name="school_address"
                                    defaultValue={profile.school_address ?? ''}
                                    rows={3}
                                    className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                    placeholder={t(
                                        'settings.school.address_placeholder',
                                    )}
                                />
                                <InputError message={errors.school_address} />
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="school_phone">
                                        {t('settings.school.phone')}
                                    </Label>
                                    <Input
                                        id="school_phone"
                                        name="school_phone"
                                        defaultValue={
                                            profile.school_phone ?? ''
                                        }
                                        placeholder={t(
                                            'settings.school.phone_placeholder',
                                        )}
                                    />
                                    <InputError message={errors.school_phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="school_email">
                                        {t('settings.school.email')}
                                    </Label>
                                    <Input
                                        id="school_email"
                                        type="email"
                                        name="school_email"
                                        defaultValue={
                                            profile.school_email ?? ''
                                        }
                                        placeholder={t(
                                            'settings.school.email_placeholder',
                                        )}
                                    />
                                    <InputError message={errors.school_email} />
                                </div>
                            </div>

                            <div className="space-y-2 rounded-lg border p-4">
                                <h3 className="text-sm font-semibold">
                                    {t('settings.school.logo_title')}
                                </h3>
                                <p className="text-muted-foreground text-xs">
                                    {t('settings.school.logo_hint')}
                                </p>
                                {profile.has_logo && (
                                    <div className="flex items-center gap-4 py-2">
                                        <img
                                            src="/settings/school/logo"
                                            alt={t(
                                                'settings.school.logo_preview_alt',
                                            )}
                                            className="h-16 w-16 rounded border bg-white object-contain p-1"
                                        />
                                        <span className="text-muted-foreground text-xs">
                                            {t('settings.school.current_logo')}
                                        </span>
                                    </div>
                                )}
                                <Input
                                    id="logo"
                                    name="logo"
                                    type="file"
                                    accept="image/png,image/jpeg,image/jpg"
                                />
                                <InputError message={errors.logo} />
                            </div>

                            <div className="space-y-4 rounded-lg border p-4">
                                <h3 className="text-sm font-semibold">
                                    {t('settings.school.principal_section')}
                                </h3>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="principal_name">
                                            {t(
                                                'settings.school.principal_name',
                                            )}
                                        </Label>
                                        <Input
                                            id="principal_name"
                                            name="principal_name"
                                            defaultValue={
                                                profile.principal_name ?? ''
                                            }
                                            placeholder={t(
                                                'settings.school.principal_name_placeholder',
                                            )}
                                        />
                                        <InputError
                                            message={errors.principal_name}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="principal_nip">
                                            {t('settings.school.principal_nip')}
                                        </Label>
                                        <Input
                                            id="principal_nip"
                                            name="principal_nip"
                                            defaultValue={
                                                profile.principal_nip ?? ''
                                            }
                                            placeholder={t(
                                                'settings.school.principal_nip_placeholder',
                                            )}
                                        />
                                        <InputError
                                            message={errors.principal_nip}
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4 rounded-lg border p-4">
                                <h3 className="text-sm font-semibold">
                                    {t('settings.school.bank_section')}
                                </h3>
                                <div className="grid gap-4">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="bank_name">
                                                {t('settings.school.bank_name')}
                                            </Label>
                                            <Input
                                                id="bank_name"
                                                name="bank_name"
                                                defaultValue={
                                                    profile.bank_name ?? ''
                                                }
                                                placeholder={t(
                                                    'settings.school.bank_name_placeholder',
                                                )}
                                            />
                                            <InputError
                                                message={errors.bank_name}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="bank_account_number">
                                                {t(
                                                    'settings.school.account_number',
                                                )}
                                            </Label>
                                            <Input
                                                id="bank_account_number"
                                                name="bank_account_number"
                                                defaultValue={
                                                    profile.bank_account_number ??
                                                    ''
                                                }
                                                placeholder={t(
                                                    'settings.school.account_number_placeholder',
                                                )}
                                            />
                                            <InputError
                                                message={
                                                    errors.bank_account_number
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="bank_account_holder">
                                            {t(
                                                'settings.school.account_holder',
                                            )}
                                        </Label>
                                        <Input
                                            id="bank_account_holder"
                                            name="bank_account_holder"
                                            defaultValue={
                                                profile.bank_account_holder ??
                                                ''
                                            }
                                            placeholder={t(
                                                'settings.school.account_holder_placeholder',
                                            )}
                                        />
                                        <InputError
                                            message={errors.bank_account_holder}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="default_transfer_account_id">
                                            {t(
                                                'settings.school.transfer_account',
                                            )}
                                        </Label>
                                        <select
                                            id="default_transfer_account_id"
                                            name="default_transfer_account_id"
                                            defaultValue={
                                                profile.default_transfer_account_id ??
                                                ''
                                            }
                                            className={SELECT_CLASS}
                                        >
                                            <option value="">
                                                {t(
                                                    'settings.school.unselected',
                                                )}
                                            </option>
                                            {cashAccounts.map((account) => (
                                                <option
                                                    key={account.id}
                                                    value={account.id}
                                                >
                                                    {account.code} -{' '}
                                                    {account.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                errors.default_transfer_account_id
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="cashier_default_cash_account_id">
                                            {t(
                                                'settings.school.cashier_default_account',
                                            )}
                                        </Label>
                                        <select
                                            id="cashier_default_cash_account_id"
                                            name="cashier_default_cash_account_id"
                                            defaultValue={
                                                profile.cashier_default_cash_account_id ??
                                                ''
                                            }
                                            className={SELECT_CLASS}
                                        >
                                            <option value="">
                                                {t(
                                                    'settings.school.cashier_auto_account',
                                                )}
                                            </option>
                                            {cashAccounts.map((account) => (
                                                <option
                                                    key={account.id}
                                                    value={account.id}
                                                >
                                                    {account.code} -{' '}
                                                    {account.name}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="text-muted-foreground text-sm">
                                            {t(
                                                'settings.school.cashier_account_hint',
                                            )}
                                        </p>
                                        <InputError
                                            message={
                                                errors.cashier_default_cash_account_id
                                            }
                                        />
                                    </div>
                                </div>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {t('settings.school.save')}
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}
