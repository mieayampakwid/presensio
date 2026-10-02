import { Head, Form } from '@inertiajs/react';
import SchoolProfileController from '@/actions/App/Http/Controllers/Settings/SchoolProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import SettingsLayout from '@/layouts/settings/layout';

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
};

type Props = {
    profile: SchoolProfileData;
};

export default function SchoolProfile({ profile }: Props) {
    return (
        <SettingsLayout>
            <Head title="School Profile Settings" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="School Profile"
                    description="Official school identity used on report cards, documents, and notifications."
                />

                <Form
                    {...SchoolProfileController.update.form()}
                    encType="multipart/form-data"
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <div className="space-y-6">
                            <div className="grid gap-2">
                                <Label htmlFor="school_name">Official School Name</Label>
                                <Input
                                    id="school_name"
                                    name="school_name"
                                    defaultValue={profile.school_name}
                                    required
                                    placeholder="e.g. SMP Negeri 1 Surabaya"
                                />
                                <InputError message={errors.school_name} />
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="npsn">NPSN</Label>
                                    <Input
                                        id="npsn"
                                        name="npsn"
                                        defaultValue={profile.npsn ?? ''}
                                        placeholder="Nomor Pokok Sekolah Nasional"
                                    />
                                    <InputError message={errors.npsn} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="default_curriculum">Default Curriculum</Label>
                                    <select
                                        id="default_curriculum"
                                        name="default_curriculum"
                                        defaultValue={profile.default_curriculum || 'merdeka'}
                                        className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                    >
                                        <option value="merdeka">Kurikulum Merdeka</option>
                                    </select>
                                    <InputError message={errors.default_curriculum} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="school_address">School Address</Label>
                                <textarea
                                    id="school_address"
                                    name="school_address"
                                    defaultValue={profile.school_address ?? ''}
                                    rows={3}
                                    className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                    placeholder="Address line, city, postal code"
                                />
                                <InputError message={errors.school_address} />
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="school_phone">Phone</Label>
                                    <Input
                                        id="school_phone"
                                        name="school_phone"
                                        defaultValue={profile.school_phone ?? ''}
                                        placeholder="e.g. 031-1234567"
                                    />
                                    <InputError message={errors.school_phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="school_email">Official Email</Label>
                                    <Input
                                        id="school_email"
                                        type="email"
                                        name="school_email"
                                        defaultValue={profile.school_email ?? ''}
                                        placeholder="e.g. info@sekolah.sch.id"
                                    />
                                    <InputError message={errors.school_email} />
                                </div>
                            </div>

                            <div className="space-y-2 rounded-lg border p-4">
                                <h3 className="text-sm font-semibold">School Logo</h3>
                                <p className="text-muted-foreground text-xs">
                                    PNG, JPG, or JPEG format (max 1 MB). Private storage streamed via authorized endpoint.
                                </p>
                                {profile.has_logo && (
                                    <div className="flex items-center gap-4 py-2">
                                        <img
                                            src="/settings/school/logo"
                                            alt="School logo preview"
                                            className="h-16 w-16 object-contain rounded border bg-white p-1"
                                        />
                                        <span className="text-muted-foreground text-xs">Current logo</span>
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
                                <h3 className="text-sm font-semibold">Principal (Kepala Sekolah)</h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="principal_name">Principal Name & Titles</Label>
                                        <Input
                                            id="principal_name"
                                            name="principal_name"
                                            defaultValue={profile.principal_name ?? ''}
                                            placeholder="e.g. Dr. H. Sulaiman, M.Pd."
                                        />
                                        <InputError message={errors.principal_name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="principal_nip">NIP</Label>
                                        <Input
                                            id="principal_nip"
                                            name="principal_nip"
                                            defaultValue={profile.principal_nip ?? ''}
                                            placeholder="e.g. 197001011995011001"
                                        />
                                        <InputError message={errors.principal_nip} />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4 rounded-lg border p-4">
                                <h3 className="text-sm font-semibold">School Bank Account (Transfer Instructions)</h3>
                                <div className="grid gap-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="bank_name">Bank Name</Label>
                                            <Input
                                                id="bank_name"
                                                name="bank_name"
                                                defaultValue={profile.bank_name ?? ''}
                                                placeholder="e.g. Bank Mandiri"
                                            />
                                            <InputError message={errors.bank_name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="bank_account_number">Account Number</Label>
                                            <Input
                                                id="bank_account_number"
                                                name="bank_account_number"
                                                defaultValue={profile.bank_account_number ?? ''}
                                                placeholder="e.g. 1420012345678"
                                            />
                                            <InputError message={errors.bank_account_number} />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="bank_account_holder">Account Holder Name</Label>
                                        <Input
                                            id="bank_account_holder"
                                            name="bank_account_holder"
                                            defaultValue={profile.bank_account_holder ?? ''}
                                            placeholder="e.g. SMP Negeri 1 Surabaya"
                                        />
                                        <InputError message={errors.bank_account_holder} />
                                    </div>
                                </div>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Save School Profile
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </SettingsLayout>
    );
}
