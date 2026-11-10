# Individual Student Withdrawal Implementation Plan

> **For implementer agents:** Read each task's full description. Execute in order. Each task is independently testable. Do not cut scope.

**Goal:** Provide an audited administrative UI workflow to withdraw/release an individual student mid-year, cleanly terminating their active enrollment, cancelling uncollected future bills, isolating attendance, and recording departure reason and destination notes.

**Architecture:** Use `App\Http\Controllers\Students\StudentWithdrawalController` as a dedicated controller for withdrawal preview and execution. Leverage existing `EnrollmentService::release()` and `BillService::cancelForWithdrawal()` inside a database transaction, recording audit logs via `AuditLogger`. The frontend uses an Inertia-driven confirmation modal on the students index and edit pages, backed by Wayfinder routes.

**Repos:** Single repository (`presensio`).

**Prerequisites:** Database schema columns `ended_reason` and `ended_notes` on `enrollments` are already present in `database/migrations/2026_09_21_000001_create_enrollments_table.php` and cast on `App\Models\Enrollment`.

---

### Task 1: Backend Controller, FormRequest & Withdrawal Endpoint

**Files:**
- Create: `app/Http/Requests/Students/WithdrawStudentRequest.php`
- Create: `app/Http/Controllers/Students/StudentWithdrawalController.php`
- Modify: `routes/students.php:12-25`
- Create: `tests/Feature/Students/StudentWithdrawalTest.php`

**Dependencies:** None

**What this does:** Provides `GET /students/{student}/withdrawal-preview` and `POST /students/{student}/withdraw` restricted to the `admin` role, validating input, updating the active enrollment with reason and notes, cancelling future unpaid bills, recording audit logs, and optionally unassigning RFID cards or deactivating the student user account.

**Verification:** Run `php artisan test --compact tests/Feature/Students/StudentWithdrawalTest.php` to prove that validation, role authorization, enrollment termination, bill cancellation, audit logging, and optional RFID/user actions pass.

- [x] **Step 1: Create `app/Http/Requests/Students/WithdrawStudentRequest.php`**

```php
<?php

namespace App\Http\Requests\Students;

use App\Enums\WithdrawalReason;
use App\Models\Student;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WithdrawStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Student $student */
        $student = $this->route('student');
        $activeEnrollment = $student->enrollments()->whereNull('ended_on')->first();
        $startedOn = $activeEnrollment?->started_on?->toDateString() ?? '1970-01-01';
        $today = app(SchoolSettings::class)->todayDate();

        return [
            'ended_on' => [
                'required',
                'date',
                'date_format:Y-m-d',
                "after_or_equal:{$startedOn}",
                "before_or_equal:{$today}",
            ],
            'reason' => [
                'required',
                Rule::enum(WithdrawalReason::class),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'release_rfid' => ['nullable', 'boolean'],
            'deactivate_user' => ['nullable', 'boolean'],
        ];
    }

    public function endedOn(): string
    {
        return $this->string('ended_on')->toString();
    }

    public function reason(): WithdrawalReason
    {
        return WithdrawalReason::from($this->string('reason')->toString());
    }

    public function notes(): ?string
    {
        $notes = $this->string('notes')->trim()->toString();

        return $notes === '' ? null : $notes;
    }

    public function shouldReleaseRfid(): bool
    {
        return $this->boolean('release_rfid');
    }

    public function shouldDeactivateUser(): bool
    {
        return $this->boolean('deactivate_user');
    }
}
```

- [x] **Step 2: Create `app/Http/Controllers/Students/StudentWithdrawalController.php`**

```php
<?php

namespace App\Http\Controllers\Students;

use App\Enums\BillStatus;
use App\Enums\FeeKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\WithdrawStudentRequest;
use App\Models\Bill;
use App\Models\Student;
use App\Services\Audit\AuditLogger;
use App\Services\EnrollmentService;
use App\Services\Finance\MoneyCalculator;
use App\Services\SchoolSettings;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StudentWithdrawalController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollmentService,
        private readonly SchoolSettings $schoolSettings,
        private readonly MoneyCalculator $calculator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Preview the impact of withdrawing the student as of a given date (defaults to today).
     */
    public function preview(Request $request, Student $student): JsonResponse
    {
        $activeEnrollment = $student->enrollments()->whereNull('ended_on')->with('schoolClass')->first();

        if ($activeEnrollment === null) {
            return response()->json([
                'has_active_enrollment' => false,
            ]);
        }

        $endDateStr = $request->string('ended_on')->toString();
        $endDate = $endDateStr !== '' ? Date::parse($endDateStr) : Date::parse($this->schoolSettings->todayDate());

        $bills = Bill::query()
            ->with('feeType')
            ->where('student_id', $student->id)
            ->whereIn('status', [BillStatus::Unpaid, BillStatus::PartiallyPaid])
            ->orderBy('due_date')
            ->get();

        $cancelledBills = [];
        $reviewBills = [];

        foreach ($bills as $bill) {
            $start = $bill->feeType->type === FeeKind::OneTime
                ? Date::parse($bill->due_date->toDateString())
                : Date::parse($bill->period_key.'-01');

            if (! $start->greaterThan($endDate)) {
                continue;
            }

            if (Money::isPositive($this->calculator->paidAmount($bill))) {
                $reviewBills[] = [
                    'id' => $bill->id,
                    'title' => $bill->title,
                    'period_key' => $bill->period_key,
                    'net_amount' => $bill->netAmount(),
                    'paid_amount' => $this->calculator->paidAmount($bill),
                ];
            } else {
                $cancelledBills[] = [
                    'id' => $bill->id,
                    'title' => $bill->title,
                    'period_key' => $bill->period_key,
                    'net_amount' => $bill->netAmount(),
                ];
            }
        }

        return response()->json([
            'has_active_enrollment' => true,
            'class_id' => $activeEnrollment->class_id,
            'class_name' => $activeEnrollment->schoolClass?->name,
            'started_on' => $activeEnrollment->started_on->toDateString(),
            'has_rfid' => $student->rfidCard !== null,
            'has_user_account' => $student->user_id !== null,
            'cancelled_bills_count' => count($cancelledBills),
            'review_bills_count' => count($reviewBills),
            'review_bills' => $reviewBills,
        ]);
    }

    /**
     * Execute the withdrawal of the student.
     */
    public function store(WithdrawStudentRequest $request, Student $student): RedirectResponse
    {
        $activeEnrollment = $student->enrollments()->whereNull('ended_on')->first();

        if ($activeEnrollment === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('toasts.students.withdraw_no_active_enrollment')]);

            return to_route('students.index');
        }

        DB::transaction(function () use ($request, $student, $activeEnrollment): void {
            $review = $this->enrollmentService->release(
                $student,
                $request->endedOn(),
                $request->reason(),
                $request->notes(),
            );

            if ($request->shouldReleaseRfid() && $student->rfidCard !== null) {
                $student->rfidCard->update(['student_id' => null]);
            }

            if ($request->shouldDeactivateUser() && $student->user !== null) {
                $student->user->update(['is_active' => false]);
            }

            $this->auditLogger->record(
                $student,
                'student.withdrawn',
                [
                    'class_id' => $activeEnrollment->class_id,
                    'started_on' => $activeEnrollment->started_on->toDateString(),
                ],
                [
                    'ended_on' => $request->endedOn(),
                    'reason' => $request->reason()->value,
                    'notes' => $request->notes(),
                    'review_bills_count' => count($review),
                    'rfid_released' => $request->shouldReleaseRfid(),
                    'user_deactivated' => $request->shouldDeactivateUser(),
                ],
                $request->notes() ?? $request->reason()->label(),
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('toasts.students.withdrawn', ['name' => $student->full_name]),
        ]);

        return to_route('students.index');
    }
}
```

- [x] **Step 3: Register Routes in `routes/students.php`**

Add the preview and store routes inside the `role:admin` group in `routes/students.php`:

```php
    Route::get('students/{student}/withdrawal-preview', [StudentWithdrawalController::class, 'preview'])
        ->missing(fn () => response()->json(['error' => 'Student not found'], 404))
        ->name('students.withdrawal-preview');
    Route::post('students/{student}/withdraw', [StudentWithdrawalController::class, 'store'])
        ->missing(fn () => to_route('students.index'))
        ->name('students.withdraw');
```

- [x] **Step 4: Create `tests/Feature/Students/StudentWithdrawalTest.php`**

```php
<?php

namespace Tests\Feature\Students;

use App\Enums\BillStatus;
use App\Enums\FeeKind;
use App\Enums\WithdrawalReason;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Finance\BillService;
use App\Services\SchoolSettings;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class StudentWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Student $student;
    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-11-09 08:00:00');
        $this->seed(ChartOfAccountsSeeder::class);
        app(SchoolSettings::class)->refresh();

        $this->admin = User::factory()->admin()->create();
        $year = AcademicYear::factory()->active()->create(['starts_at' => '2026-07-01', 'ends_at' => '2027-06-30']);
        $this->class = SchoolClass::factory()->create(['academic_year_id' => $year->id]);
        $this->student = Student::factory()->enrolledIn($this->class, '2026-07-01')->create();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_non_admin_cannot_withdraw_student(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->post(route('students.withdraw', $this->student), [
                'ended_on' => '2026-11-09',
                'reason' => WithdrawalReason::TransferOut->value,
            ])
            ->assertForbidden();
    }

    public function test_withdrawal_preview_calculates_bill_impact(): void
    {
        $feeType = FeeType::factory()->create();
        Bill::factory()->create([
            'student_id' => $this->student->id,
            'fee_type_id' => $feeType->id,
            'period_key' => '2026-12',
            'due_date' => '2026-12-10',
            'amount' => '300000.00',
        ]);

        $partial = Bill::factory()->create([
            'student_id' => $this->student->id,
            'fee_type_id' => $feeType->id,
            'period_key' => '2026-12',
            'due_date' => '2026-12-10',
            'amount' => '300000.00',
        ]);
        PaymentAllocation::factory()->for(Payment::factory()->verified(), 'payment')->for($partial)->create(['amount' => '100000.00']);
        app(BillService::class)->recompute($partial);

        $response = $this->actingAs($this->admin)
            ->getJson(route('students.withdrawal-preview', $this->student))
            ->assertOk()
            ->json();

        $this->assertTrue($response['has_active_enrollment']);
        $this->assertSame($this->class->name, $response['class_name']);
        $this->assertSame(1, $response['cancelled_bills_count']);
        $this->assertSame(1, $response['review_bills_count']);
    }

    public function test_admin_can_withdraw_student_successfully(): void
    {
        $card = RfidCard::factory()->create(['student_id' => $this->student->id]);
        $user = User::factory()->student()->create();
        $this->student->update(['user_id' => $user->id]);

        $feeType = FeeType::factory()->create();
        $futureBill = Bill::factory()->create([
            'student_id' => $this->student->id,
            'fee_type_id' => $feeType->id,
            'period_key' => '2026-12',
            'due_date' => '2026-12-10',
            'amount' => '300000.00',
        ]);

        $this->actingAs($this->admin)
            ->post(route('students.withdraw', $this->student), [
                'ended_on' => '2026-11-09',
                'reason' => WithdrawalReason::TransferOut->value,
                'notes' => 'Pindah ke SMP 1 Bandung',
                'release_rfid' => true,
                'deactivate_user' => true,
            ])
            ->assertRedirect(route('students.index'));

        $enrollment = $this->student->enrollments()->firstOrFail();
        $this->assertSame('2026-11-09', $enrollment->ended_on->toDateString());
        $this->assertSame(WithdrawalReason::TransferOut, $enrollment->ended_reason);
        $this->assertSame('Pindah ke SMP 1 Bandung', $enrollment->ended_notes);

        $this->assertSame(BillStatus::Cancelled, $futureBill->fresh()->status);
        $this->assertNull($card->fresh()->student_id);
        $this->assertFalse($user->fresh()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student.withdrawn',
            'auditable_type' => $this->student->getMorphClass(),
            'auditable_id' => $this->student->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_withdrawal_fails_if_ended_on_is_before_started_on(): void
    {
        $this->actingAs($this->admin)
            ->post(route('students.withdraw', $this->student), [
                'ended_on' => '2026-06-30',
                'reason' => WithdrawalReason::DroppedOut->value,
            ])
            ->assertSessionHasErrors('ended_on');
    }
}
```

- [x] **Step 5: Run tests to verify Task 1**
  Run: `php artisan test --compact tests/Feature/Students/StudentWithdrawalTest.php`
  Expected: 4 tests passing.

---

### Task 2: Student Listing Status & Filter Backend

**Files:**
- Modify: `app/Http/Controllers/Students/StudentController.php:28-55`
- Modify: `tests/Feature/Students/StudentManagementTest.php`

**Dependencies:** Task 1

**What this does:** Updates `StudentController::index()` to pass whether each student has an active enrollment in the active academic year, and adds a `status` query filter (`active`, `withdrawn`, or default `all`).

**Verification:** Run `php artisan test --compact tests/Feature/Students/StudentManagementTest.php`.

- [x] **Step 1: Update `StudentController::index()`**

In `app/Http/Controllers/Students/StudentController.php`, update the query in `index()`:

```php
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $activeYearId = AcademicYear::active()?->id;

        $students = Student::query()
            ->with(['currentEnrollment.schoolClass:id,name', 'rfidCard:id,student_id'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%")
                        ->orWhereHas('guardians', function (Builder $query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('phone_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status === 'active', function (Builder $query) use ($activeYearId) {
                $query->whereHas('enrollments', function (Builder $q) use ($activeYearId) {
                    $q->whereNull('ended_on')
                        ->when($activeYearId !== null, fn ($eq) => $eq->whereHas('schoolClass', fn ($cq) => $cq->where('academic_year_id', $activeYearId)));
                });
            })
            ->when($status === 'withdrawn', function (Builder $query) use ($activeYearId) {
                $query->whereDoesntHave('enrollments', function (Builder $q) use ($activeYearId) {
                    $q->whereNull('ended_on')
                        ->when($activeYearId !== null, fn ($eq) => $eq->whereHas('schoolClass', fn ($cq) => $cq->where('academic_year_id', $activeYearId)));
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('students/index', [
            'students' => $students->through(fn (Student $s) => [
                'id' => $s->id,
                'full_name' => $s->full_name,
                'student_number' => $s->student_number,
                'school_class' => $s->currentEnrollment?->schoolClass ? [
                    'id' => $s->currentEnrollment->schoolClass->id,
                    'name' => $s->currentEnrollment->schoolClass->name,
                ] : null,
                'is_enrolled' => $s->currentEnrollment !== null,
                'has_rfid' => $s->rfidCard !== null,
            ]),
            'filters' => [
                'search' => $search,
                'status' => $status !== '' ? $status : 'all',
            ],
        ]);
    }
```

- [x] **Step 2: Add test cases to `tests/Feature/Students/StudentManagementTest.php`**

Add tests proving that filtering by `status=active` and `status=withdrawn` works properly and that `is_enrolled` is returned in the payload.

- [x] **Step 3: Run student tests**
  Run: `php artisan test --compact tests/Feature/Students/StudentManagementTest.php`
  Expected: All passing.

---

### Task 3: Localization Keys (en & id)

**Files:**
- Modify: `lang/en/toasts.php`
- Modify: `lang/id/toasts.php`
- Modify: `lang/en/students.php`
- Modify: `lang/id/students.php`
- Test: `tests/Unit/LangParityTest.php`

**Dependencies:** Task 2

**What this does:** Adds all necessary UI translation keys for the withdrawal modal, filters, status badges, and flash toasts across both English and Indonesian, verified by `LangParityTest`.

**Verification:** Run `php artisan test --compact tests/Unit/LangParityTest.php`.

- [x] **Step 1: Add keys in `lang/en/toasts.php` and `lang/id/toasts.php`**

In `lang/en/toasts.php` under `'students'`:
```php
'withdrawn' => 'Student :name has been released.',
'withdraw_no_active_enrollment' => 'Student does not have an active enrollment.',
```

In `lang/id/toasts.php` under `'students'`:
```php
'withdrawn' => 'Siswa :name berhasil dilepaskan.',
'withdraw_no_active_enrollment' => 'Siswa tidak memiliki pendaftaran kelas yang aktif.',
```

- [x] **Step 2: Add keys in `lang/en/students.php` and `lang/id/students.php`**

In `lang/en/students.php`:
```php
    'withdrawal' => [
        'action' => 'Withdraw Student',
        'title' => 'Withdraw Student',
        'description' => 'Terminate active enrollment for this student. Future unpaid tuition bills will be cancelled automatically.',
        'student' => 'Student',
        'class' => 'Current Class',
        'ended_on' => 'Departure Date',
        'reason' => 'Reason',
        'reasons' => [
            'transfer_out' => 'Transfer Out to Another School',
            'dropped_out' => 'Dropped Out',
            'expelled' => 'Expelled',
            'deceased' => 'Deceased',
            'graduated' => 'Graduated',
            'other' => 'Other',
        ],
        'notes' => 'Notes / Destination School',
        'notes_placeholder' => 'e.g. Transferred to SMP Negeri 1...',
        'release_rfid' => 'Release active RFID card (unassign)',
        'deactivate_user' => 'Deactivate student portal account',
        'cancelled_bills_note' => ':count future unpaid bills will be cancelled.',
        'review_bills_warning' => ':count partially paid bills will require manual review.',
        'confirm' => 'Confirm Withdrawal',
        'cancel' => 'Cancel',
    ],
    'status' => [
        'badge_active' => 'Active',
        'badge_withdrawn' => 'Withdrawn / Alumni',
        'filter_label' => 'Status',
        'all' => 'All Statuses',
        'active' => 'Active Only',
        'withdrawn' => 'Withdrawn / Alumni Only',
    ],
```

In `lang/id/students.php`:
```php
    'withdrawal' => [
        'action' => 'Lepaskan Siswa',
        'title' => 'Lepaskan Siswa',
        'description' => 'Akhiri masa pendaftaran aktif siswa ini. Tagihan masa depan yang belum dibayar akan dibatalkan otomatis.',
        'student' => 'Siswa',
        'class' => 'Kelas Saat Ini',
        'ended_on' => 'Tanggal Keluar',
        'reasons' => [
            'transfer_out' => 'Pindah Sekolah / Mutasi Keluar',
            'dropped_out' => 'Mengundurkan Diri',
            'expelled' => 'Dikeluarkan',
            'deceased' => 'Meninggal Dunia',
            'graduated' => 'Lulus',
            'other' => 'Lainnya',
        ],
        'notes' => 'Catatan / Sekolah Tujuan',
        'notes_placeholder' => 'Contoh: Pindah ke SMP Negeri 1...',
        'release_rfid' => 'Lepas kaitan kartu RFID aktif',
        'deactivate_user' => 'Nonaktifkan akun portal siswa',
        'cancelled_bills_note' => ':count tagihan masa depan yang belum dibayar akan dibatalkan.',
        'review_bills_warning' => ':count tagihan yang telah dicicil sebagian memerlukan review bendahara.',
        'confirm' => 'Konfirmasi Pelepasan',
        'cancel' => 'Batal',
    ],
    'status' => [
        'badge_active' => 'Aktif',
        'badge_withdrawn' => 'Keluar / Alumni',
        'filter_label' => 'Status',
        'all' => 'Semua Status',
        'active' => 'Hanya Aktif',
        'withdrawn' => 'Hanya Keluar / Alumni',
    ],
```

- [x] **Step 3: Run `LangParityTest`**
  Run: `php artisan test --compact tests/Unit/LangParityTest.php`
  Expected: Passed (0 parity discrepancies).

---

### Task 4: Frontend UI (Withdrawal Dialog & Student Pages Integration)

**Files:**
- Create: `resources/js/pages/students/withdrawal-dialog.tsx`
- Modify: `resources/js/pages/students/index.tsx`
- Modify: `resources/js/pages/students/edit.tsx`
- Command: `sail artisan wayfinder:generate --with-form`

**Dependencies:** Task 1, Task 2, Task 3

**What this does:** Builds the React modal dialog for confirming student withdrawal, loads real-time bill impact previews via the preview endpoint, integrates a "Lepaskan Siswa" button in table actions and student edit headers, and adds the status filter dropdown and status badges.

**Verification:** Run `sail npm run check`, `sail npm run types:check`, and `sail npm run test:i18n`.

- [x] **Step 1: Regenerate Wayfinder**
  Run: `sail artisan wayfinder:generate --with-form`
  Expected: Generates `StudentWithdrawalController` actions and routes in `@/actions/...`.

- [x] **Step 2: Create `resources/js/pages/students/withdrawal-dialog.tsx`**

Create the dialog component using `@inertiajs/react` `useForm`, fetching preview metrics on mount or open, and rendering date, reason, notes, options, and warnings:

```tsx
import { useForm } from '@inertiajs/react';
import { AlertTriangle, UserMinus } from 'lucide-react';
import { useEffect, useState } from 'react';
import StudentWithdrawalController from '@/actions/App/Http/Controllers/Students/StudentWithdrawalController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';

type StudentTarget = {
    id: number;
    full_name: string;
    student_number: string | null;
    school_class: { id: number; name: string } | null;
    has_rfid?: boolean;
};

type PreviewData = {
    has_active_enrollment: boolean;
    class_name?: string;
    started_on?: string;
    has_rfid: boolean;
    has_user_account: boolean;
    cancelled_bills_count: number;
    review_bills_count: number;
};

type Props = {
    student: StudentTarget | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function WithdrawalDialog({ student, open, onOpenChange }: Props) {
    const { t } = useTranslation();
    const [preview, setPreview] = useState<PreviewData | null>(null);
    const [loadingPreview, setLoadingPreview] = useState(false);

    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors, reset } = useForm({
        ended_on: today,
        reason: 'transfer_out',
        notes: '',
        release_rfid: true,
        deactivate_user: true,
    });

    useEffect(() => {
        if (!open || !student) {
            setPreview(null);
            reset();
            return;
        }

        setLoadingPreview(true);
        fetch(StudentWithdrawalController.preview({ student: student.id, ended_on: data.ended_on }).url)
            .then((res) => res.json())
            .then((data: PreviewData) => {
                setPreview(data);
                setLoadingPreview(false);
            })
            .catch(() => setLoadingPreview(false));
    }, [open, student, data.ended_on]);

    if (!student) return null;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(StudentWithdrawalController.store({ student: student.id }).url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <div className="flex items-center gap-2 text-destructive">
                        <UserMinus className="h-5 w-5" />
                        <DialogTitle>{t('students.withdrawal.title')}</DialogTitle>
                    </div>
                    <DialogDescription>
                        {t('students.withdrawal.description')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="rounded-md border p-3 text-sm bg-muted/40 space-y-1">
                        <div>
                            <span className="font-medium text-muted-foreground">{t('students.withdrawal.student')}:</span>{' '}
                            <span className="font-semibold">{student.full_name}</span>{' '}
                            {student.student_number && <span className="text-muted-foreground">({student.student_number})</span>}
                        </div>
                        {student.school_class && (
                            <div>
                                <span className="font-medium text-muted-foreground">{t('students.withdrawal.class')}:</span>{' '}
                                <span className="font-semibold">{student.school_class.name}</span>
                            </div>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="ended_on">{t('students.withdrawal.ended_on')}</Label>
                        <Input
                            id="ended_on"
                            type="date"
                            max={today}
                            min={preview?.started_on}
                            value={data.ended_on}
                            onChange={(e) => setData('ended_on', e.target.value)}
                        />
                        <InputError message={errors.ended_on} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="reason">{t('students.withdrawal.reason')}</Label>
                        <select
                            id="reason"
                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                        >
                            <option value="transfer_out">{t('students.withdrawal.reasons.transfer_out')}</option>
                            <option value="dropped_out">{t('students.withdrawal.reasons.dropped_out')}</option>
                            <option value="expelled">{t('students.withdrawal.reasons.expelled')}</option>
                            <option value="deceased">{t('students.withdrawal.reasons.deceased')}</option>
                            <option value="other">{t('students.withdrawal.reasons.other')}</option>
                        </select>
                        <InputError message={errors.reason} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="notes">{t('students.withdrawal.notes')}</Label>
                        <Textarea
                            id="notes"
                            placeholder={t('students.withdrawal.notes_placeholder')}
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={3}
                        />
                        <InputError message={errors.notes} />
                    </div>

                    {preview && preview.has_rfid && (
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="release_rfid"
                                checked={data.release_rfid}
                                onCheckedChange={(checked) => setData('release_rfid', Boolean(checked))}
                            />
                            <Label htmlFor="release_rfid" className="text-sm cursor-pointer font-normal">
                                {t('students.withdrawal.release_rfid')}
                            </Label>
                        </div>
                    )}

                    {preview && preview.has_user_account && (
                        <div className="flex items-center space-x-2">
                            <Checkbox
                                id="deactivate_user"
                                checked={data.deactivate_user}
                                onCheckedChange={(checked) => setData('deactivate_user', Boolean(checked))}
                            />
                            <Label htmlFor="deactivate_user" className="text-sm cursor-pointer font-normal">
                                {t('students.withdrawal.deactivate_user')}
                            </Label>
                        </div>
                    )}

                    {preview && preview.cancelled_bills_count > 0 && (
                        <div className="text-xs text-muted-foreground">
                            ✓ {t('students.withdrawal.cancelled_bills_note', { count: preview.cancelled_bills_count })}
                        </div>
                    )}

                    {preview && preview.review_bills_count > 0 && (
                        <Alert variant="destructive">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription>
                                {t('students.withdrawal.review_bills_warning', { count: preview.review_bills_count })}
                            </AlertDescription>
                        </Alert>
                    )}

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            {t('students.withdrawal.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing || loadingPreview}
                        >
                            {t('students.withdrawal.confirm')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
```

- [x] **Step 3: Update `resources/js/pages/students/index.tsx`**

1. Import `WithdrawalDialog` and `UserMinus` icon.
2. Add `status` filter select beside the search box (`filters: { search: string, status: string }`).
3. Add a Status column or badge in the class column (`is_enrolled ? student.school_class.name : t('students.status.badge_withdrawn')`).
4. Add a "Lepaskan Siswa" button in the table row actions when `student.is_enrolled` is true.

- [x] **Step 4: Update `resources/js/pages/students/edit.tsx`**

Add an action button in the heading actions or next to Save Changes: if the student is enrolled, render a secondary destructive button "Lepaskan Siswa" that triggers `WithdrawalDialog`.

- [x] **Step 5: Frontend Verification**
  Run: `sail npm run check`
  Run: `sail npm run types:check`
  Run: `sail npm run test:i18n`
  Expected: Clean pass across formatting, TypeScript, and JSX literal check (0 literals).

---

### Task 5: End-to-End Verification, Specs & Status Flip

**Files:**
- Modify: `docs/specs/24-student-withdrawal.md:3`
- Modify: `docs/specs/README.md:64`
- Modify: `/Users/arifinnor/Vario/10_Projects/Presensio/Presensio.md`
- Modify: `CLAUDE.md:228`

**Dependencies:** Tasks 1–4

**What this does:** Executes full project CI check (`composer ci:check`), flips status to Implemented across all documentation and vault MOC, and records completion in `CLAUDE.md`.

**Verification:** `composer ci:check` passes cleanly.

- [x] **Step 1: Run full CI test suite**
  Run: `composer ci:check`
  Expected: JS check clean, TS clean, Pint clean, PHPStan clean, all PHPUnit tests pass.

- [x] **Step 2: Flip Spec status in `docs/specs/24-student-withdrawal.md`**
  Update line 3:
  `Status: implemented 2026-11-09 (v1.0)`

- [x] **Step 3: Update `docs/specs/README.md`**
  Update row 24:
  `| 24 | [Individual Student Withdrawal](24-student-withdrawal.md) | Implemented 2026-11-09 (v1.0) | UI & lifecycle for releasing single student enrollments (amends 02/07/14) |`

- [x] **Step 4: Update Vault MOC (`Presensio.md`)**
  Move Spec 24 from Open Work to Shipped, and append decision history entry.

- [x] **Step 5: Record in `CLAUDE.md` status block**
  Update the status block to document the completion of Spec 24.
