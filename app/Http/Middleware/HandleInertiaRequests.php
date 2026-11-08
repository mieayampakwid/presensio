<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Services\SchoolSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user?->hasRole(UserRole::ScannerOperator)) {
            return [
                ...parent::share($request),
                'name' => config('app.name'),
                'auth' => [
                    'user' => ['id' => $user->id, 'username' => $user->username, 'email' => $user->email,
                        'role' => 'scanner_operator', 'roles' => ['scanner_operator']],
                    'active_role' => 'scanner_operator',
                ],
                'locale' => app()->getLocale(),
                'translations' => fn (): array => $this->translations(),
                'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user !== null ? [
                    ...$user->toArray(),
                    'roles' => $user->roles()->map(fn ($r) => $r->value)->values()->all(),
                ] : null,
                'active_role' => $user?->activeRole()->value,
            ],
            'incomplete_class_count' => fn (): int => $user?->hasRole(UserRole::Admin)
                ? SchoolClass::query()->where('grade_level', 0)->count()
                : 0,
            'pending_config_warnings' => fn (): array => $user?->hasRole(UserRole::Admin)
                ? $this->pendingConfigWarnings()
                : [],
            'notifications' => fn (): ?array => $user !== null ? [
                'unread_count' => $user->unreadNotifications()->count(),
                'latest10' => $user->notifications()->take(10)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'data' => $n->data,
                    'read_at' => $n->read_at?->toISOString(),
                    'created_at' => $n->created_at?->diffForHumans(),
                ])->all(),
            ] : null,
            'locale' => app()->getLocale(),
            'locales' => array_map(
                fn (Locale $locale): array => ['value' => $locale->value, 'label' => $locale->label()],
                Locale::cases(),
            ),
            'translations' => fn (): array => $this->translations(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * UI strings for the active locale, with English filling any missing key.
     * Server-only groups (validation, auth, ...) stay off the client.
     *
     * @return array<string, array<string, mixed>>
     */
    private function translations(): array
    {
        $serverOnly = ['auth', 'pagination', 'passwords', 'validation'];
        $fallback = config('app.fallback_locale');
        $locale = app()->getLocale();
        $bag = [];

        foreach (File::glob(lang_path($fallback.'/*.php')) as $file) {
            $group = basename($file, '.php');

            if (in_array($group, $serverOnly, true)) {
                continue;
            }

            $bag[$group] = array_replace_recursive(
                (array) Lang::get($group, [], $fallback),
                (array) Lang::get($group, [], $locale),
            );
        }

        return $bag;
    }

    /**
     * @return list<array{id: string, message: string, action_label: string, action_url: string}>
     */
    private function pendingConfigWarnings(): array
    {
        $warnings = [];
        $settings = app(SchoolSettings::class)->row();

        if (empty(trim($settings->school_name ?? ''))) {
            $warnings[] = [
                'id' => 'school_name',
                'message' => __('settings.warnings.school_name'),
                'action_label' => __('settings.warnings.school_name_action'),
                'action_url' => route('school-profile.edit'),
            ];
        }

        if ($settings->cashier_default_cash_account_id === null) {
            $warnings[] = [
                'id' => 'cashier_default_cash_account',
                'message' => __('settings.warnings.cashier_default_account'),
                'action_label' => __('settings.warnings.cashier_default_account_action'),
                'action_url' => route('school-profile.edit'),
            ];
        }

        if ($settings->default_transfer_account_id === null) {
            $warnings[] = [
                'id' => 'default_transfer_account',
                'message' => __('settings.warnings.default_transfer_account'),
                'action_label' => __('settings.warnings.default_transfer_account_action'),
                'action_url' => route('school-profile.edit'),
            ];
        }

        if ($settings->guardian_deposit_account_id === null) {
            $warnings[] = [
                'id' => 'guardian_deposit_account',
                'message' => __('settings.warnings.guardian_deposit_account'),
                'action_label' => __('settings.warnings.guardian_deposit_account_action'),
                'action_url' => route('school-profile.edit'),
            ];
        }

        $activeYear = AcademicYear::active();
        if ($activeYear === null) {
            $warnings[] = [
                'id' => 'active_academic_year',
                'message' => __('settings.warnings.active_academic_year'),
                'action_label' => __('settings.warnings.active_academic_year_action'),
                'action_url' => route('academic-years.index'),
            ];
        } elseif ($activeYear->semesters()->doesntExist()) {
            $warnings[] = [
                'id' => 'active_semester',
                'message' => __('settings.warnings.active_semester'),
                'action_label' => __('settings.warnings.active_semester_action'),
                'action_url' => route('academic-years.semesters', $activeYear),
            ];
        }

        $incompleteClasses = SchoolClass::query()->where('grade_level', 0)->count();
        if ($incompleteClasses > 0) {
            $warnings[] = [
                'id' => 'incomplete_classes',
                'message' => __('settings.warnings.incomplete_classes', ['count' => $incompleteClasses]),
                'action_label' => __('settings.warnings.incomplete_classes_action'),
                'action_url' => route('classes.index'),
            ];
        }

        return $warnings;
    }
}
