<?php

namespace Tests\Feature\Fees;

use App\Models\Account;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Guardian;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentFeeDiscount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Role matrix over the whole fee/finance route surface (spec 14 §Access, AC-14-12, AC-14-24).
 * Only the authorization outcome is asserted: forbidden is 403, allowed is anything else
 * (validation/redirect/404 prove the role gate passed). Behavior lives in the owner tests.
 */
class FeeAccessTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['admin', 'finance', 'principal', 'teacher', 'counselor', 'parent', 'student'];

    /** Roles allowed per access level; every role not listed gets 403. */
    private const LEVELS = [
        'read' => ['admin', 'finance', 'principal'],
        'write' => ['admin', 'finance'],
        'admin' => ['admin'],
        'receipt' => ['admin', 'finance', 'principal', 'parent'],
        'parent' => ['admin', 'parent'],
        'guardian' => ['parent'],
        'student' => ['student'],
    ];

    /** @var array<string, string> route name => access level */
    private const MATRIX = [
        'fees.bills.index' => 'read',
        'fees.bills.cancel' => 'write',
        'fees.bills.discount' => 'write',
        'fees.bills.write-off' => 'write',
        'fees.cashier.index' => 'write',
        'fees.cashier.store' => 'write',
        'fees.discounts.index' => 'read',
        'fees.discounts.store' => 'write',
        'fees.discounts.update' => 'write',
        'fees.discounts.destroy' => 'write',
        'fees.generate.index' => 'write',
        'fees.generate.store' => 'write',
        'fees.generate.preview' => 'write',
        'fees.payments.index' => 'read',
        'fees.payments.receipt' => 'receipt',
        'fees.payments.void' => 'write',
        'fees.types.index' => 'read',
        'fees.types.store' => 'write',
        'fees.types.update' => 'write',
        'fees.types.destroy' => 'write',
        'fees.verifications.index' => 'read',
        'fees.verifications.proof' => 'read',
        'fees.verifications.reject' => 'write',
        'fees.verifications.verify' => 'write',
        'finance.accounts.index' => 'read',
        'finance.accounts.store' => 'admin',
        'finance.accounts.update' => 'admin',
        'finance.accounts.destroy' => 'admin',
        'finance.journals.index' => 'read',
        'finance.journals.store' => 'write',
        'finance.journals.create' => 'write',
        'finance.journals.show' => 'read',
        'finance.journals.update' => 'write',
        'finance.journals.destroy' => 'write',
        'finance.journals.attachment' => 'read',
        'finance.journals.edit' => 'write',
        'finance.journals.post' => 'write',
        'finance.journals.reverse' => 'write',
        'finance.periods.index' => 'read',
        'finance.periods.year-end' => 'write',
        'finance.periods.close' => 'write',
        'finance.periods.reopen' => 'write',
        'finance.reports.cash-book' => 'read',
        'finance.reports.collections-by-class' => 'read',
        'finance.reports.general-journal' => 'read',
        'finance.reports.general-ledger' => 'read',
        'finance.reports.receivables-aging' => 'read',
        'finance.reports.receivables-reconciliation' => 'read',
        'finance.reports.student-receivable-card' => 'read',
        'finance.reports.trial-balance' => 'read',
        'parent.fees.index' => 'parent',
        'parent.fees.transfers.store' => 'parent',
        'parent.fees.transfers.cancel' => 'guardian',
        'student.fees.index' => 'student',
    ];

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function accessMatrix(): iterable
    {
        foreach (self::MATRIX as $name => $level) {
            foreach (self::ROLES as $role) {
                $allowed = in_array($role, self::LEVELS[$level], true);

                yield "$name as $role ".($allowed ? 'allowed' : 'forbidden') => [$name, $role, $allowed];
            }
        }
    }

    /**
     * Route-model bindings resolve before the role gate, so forbidden roles need real records.
     *
     * @return array<string, mixed>
     */
    private function routeParameters(string $name): array
    {
        $uri = Route::getRoutes()->getByName($name)->uri();
        $parameters = [];

        foreach ([
            'bill' => fn () => Bill::factory()->create(),
            'discount' => fn () => StudentFeeDiscount::factory()->create(),
            'payment' => fn () => Payment::factory()->create(),
            'fee_type' => fn () => FeeType::factory()->create(),
            'account' => fn () => Account::factory()->create(),
            'journal' => fn () => JournalEntry::factory()->create(),
            'period' => fn () => '2026-09',
        ] as $parameter => $make) {
            if (str_contains($uri, '{'.$parameter.'}')) {
                $parameters[$parameter] = $make();
            }
        }

        return $parameters;
    }

    #[DataProvider('accessMatrix')]
    public function test_role_access_matches_the_fee_matrix(string $name, string $role, bool $allowed): void
    {
        $route = Route::getRoutes()->getByName($name);
        $user = User::factory()->$role()->create();

        // Portal controllers answer 403 for accounts without a linked profile; link one so the role gate is what is tested.
        match ($role) {
            'parent' => Guardian::factory()->create(['user_id' => $user->id]),
            'student' => Student::factory()->create(['user_id' => $user->id]),
            default => null,
        };

        $parameters = $this->routeParameters($name);
        if ($name === 'parent.fees.transfers.cancel' && $role === 'parent') {
            $student = Student::factory()->create();
            $user->guardian->students()->attach($student->id, ['relationship_type' => 'father']);
            $parameters['payment']->allocations()->create([
                'bill_id' => Bill::factory()->create(['student_id' => $student->id])->id,
                'amount' => '350000.00',
            ]);
        }
        $response = $this->actingAs($user)->call($route->methods()[0], route($name, $parameters));

        if ($allowed) {
            $this->assertNotSame(403, $response->getStatusCode(), "$role was denied $name");
        } else {
            $response->assertForbidden();
        }
    }

    public function test_guests_are_redirected_to_login_on_every_route_family(): void
    {
        foreach (['fees.bills.index', 'fees.bills.cancel', 'finance.reports.trial-balance', 'parent.fees.index', 'student.fees.index'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->call($route->methods()[0], route($name, $this->routeParameters($name)))
                ->assertRedirect(route('login'));
        }
    }

    public function test_every_fee_and_finance_route_is_listed_in_the_matrix(): void
    {
        $actual = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name) => preg_match('/^(fees|finance|parent\.fees|student\.fees)\./', $name) === 1)
            ->sort()
            ->values()
            ->all();

        $listed = array_keys(self::MATRIX);
        sort($listed);

        $this->assertSame($actual, $listed, 'Route added or removed without updating the access matrix.');
    }
}
