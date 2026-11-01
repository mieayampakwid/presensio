// Components
import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTranslation();

    setLayoutProps({
        title: t('auth_pages.forgot.layout_title'),
        description: t('auth_pages.forgot.layout_description'),
    });

    return (
        <>
            <Head title={t('auth_pages.forgot.title')} />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="username">
                                    {t('auth_pages.form.username')}
                                </Label>
                                <Input
                                    id="username"
                                    type="text"
                                    name="username"
                                    autoComplete="off"
                                    autoFocus
                                    placeholder={t(
                                        'auth_pages.form.username_placeholder',
                                    )}
                                />

                                <InputError message={errors.username} />
                            </div>

                            <div className="my-6 flex items-center justify-start">
                                <Button
                                    className="w-full"
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing && (
                                        <LoaderCircle className="h-4 w-4 animate-spin" />
                                    )}
                                    {t('auth_pages.forgot.submit')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="text-muted-foreground space-x-1 text-center text-sm">
                    <span>{t('auth_pages.forgot.return_to')}</span>
                    <TextLink href={login()}>
                        {t('auth_pages.forgot.log_in')}
                    </TextLink>
                </div>
            </div>
        </>
    );
}
