import { usePage } from '@inertiajs/react';
import { translate } from '@/lib/i18n';
import type { Replacements } from '@/lib/i18n';

export function useTranslation() {
    const { translations, locale } = usePage().props;

    const t = (key: string, replacements?: Replacements): string =>
        translate(translations, key, replacements);

    return { t, locale };
}
