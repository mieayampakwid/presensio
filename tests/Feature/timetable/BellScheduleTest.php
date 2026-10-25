<?php

namespace Tests\Feature\timetable;

use App\Models\BellSchedule;
use App\Models\LessonPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BellScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_bell_schedules_page(): void
    {
        $admin = User::factory()->admin()->create();
        BellSchedule::factory()->create(['name' => 'Reguler', 'weekdays' => [1, 2, 3, 4]]);

        $this->actingAs($admin)
            ->get(route('bell-schedules.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('timetable/bell-schedules')
                ->has('schedules', 1)
                ->where('schedules.0.name', 'Reguler')
                ->has('schedules.0.periods')
                ->where('operational_weekdays', [1, 2, 3, 4, 5]));
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden_from_bell_schedule_management(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('bell-schedules.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_schedules_covering_all_operational_days(): void
    {
        $admin = User::factory()->admin()->create();

        // Friday is covered first so the Monday-Thursday schedule can save.
        BellSchedule::factory()->create(['name' => 'Jumat', 'weekdays' => [5]]);

        $this->actingAs($admin)
            ->post(route('bell-schedules.store'), [
                'name' => 'Reguler',
                'weekdays' => [1, 2, 3, 4],
                'periods' => [
                    ['kind' => 'lesson', 'number' => 1, 'start_time' => '07:00', 'end_time' => '07:35'],
                    ['kind' => 'lesson', 'number' => 2, 'start_time' => '07:40', 'end_time' => '08:15'],
                    ['kind' => 'break', 'name' => 'Istirahat', 'start_time' => '08:15', 'end_time' => '08:30'],
                    ['kind' => 'lesson', 'number' => 3, 'start_time' => '08:30', 'end_time' => '09:05'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('bell-schedules.index'));

        $this->assertDatabaseHas('bell_schedules', [
            'name' => 'Reguler',
            'weekdays' => json_encode([1, 2, 3, 4]),
        ]);

        $this->assertDatabaseHas('lesson_periods', [
            'number' => 1,
            'kind' => 'lesson',
            'name' => null,
            'start_time' => '07:00',
            'end_time' => '07:35',
            'sort_order' => 1,
        ]);

        $this->assertDatabaseHas('lesson_periods', [
            'kind' => 'break',
            'number' => null,
            'name' => 'Istirahat',
            'sort_order' => 3,
        ]);

        $this->assertSame(2, BellSchedule::count());
    }

    /**
     * AC-13-01: covering a day twice is rejected with HTTP 422.
     */
    public function test_creating_a_schedule_that_covers_a_day_twice_returns_422(): void
    {
        $admin = User::factory()->admin()->create();
        BellSchedule::factory()->create(['name' => 'Reguler', 'weekdays' => [1, 2, 3, 4]]);

        $this->actingAs($admin)
            ->postJson(route('bell-schedules.store'), [
                'name' => 'Jumat',
                'weekdays' => [4, 5],
                'periods' => $this->lessonRows(1),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('weekdays');

        $this->assertDatabaseMissing('bell_schedules', ['name' => 'Jumat']);
    }

    /**
     * AC-13-01: leaving an operational day uncovered is rejected with HTTP 422.
     */
    public function test_creating_a_schedule_that_leaves_an_operational_day_uncovered_returns_422(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('bell-schedules.store'), [
                'name' => 'Reguler',
                'weekdays' => [1, 2, 3],
                'periods' => $this->lessonRows(1),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('weekdays');

        $this->assertSame(0, BellSchedule::count());
    }

    /**
     * AC-13-01: claiming a non-operational weekday is rejected with HTTP 422.
     */
    public function test_creating_a_schedule_with_a_non_operational_weekday_returns_422(): void
    {
        $admin = User::factory()->admin()->create();
        BellSchedule::factory()->create(['name' => 'Reguler', 'weekdays' => [1, 2, 3, 4]]);

        $this->actingAs($admin)
            ->postJson(route('bell-schedules.store'), [
                'name' => 'Jumat',
                'weekdays' => [5, 6],
                'periods' => $this->lessonRows(1),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('weekdays');

        $this->assertSame(1, BellSchedule::count());
    }

    public function test_overlapping_periods_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('bell-schedules.store'), [
                'name' => 'Reguler',
                'weekdays' => [1, 2, 3, 4],
                'periods' => [
                    ['kind' => 'lesson', 'number' => 1, 'start_time' => '07:00', 'end_time' => '07:35'],
                    ['kind' => 'lesson', 'number' => 2, 'start_time' => '07:30', 'end_time' => '08:05'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('periods');
    }

    public function test_period_ending_before_or_at_its_start_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('bell-schedules.store'), [
                'name' => 'Reguler',
                'weekdays' => [1, 2, 3, 4],
                'periods' => [
                    ['kind' => 'lesson', 'number' => 1, 'start_time' => '07:35', 'end_time' => '07:00'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('periods.0.end_time');

        $this->assertDatabaseMissing('bell_schedules', ['name' => 'Reguler']);
    }

    public function test_kind_specific_period_fields_are_enforced(): void
    {
        $admin = User::factory()->admin()->create();

        // Lesson without a number.
        $this->actingAs($admin)
            ->postJson(route('bell-schedules.store'), [
                'name' => 'Reguler',
                'weekdays' => [1, 2, 3, 4],
                'periods' => [
                    ['kind' => 'lesson', 'start_time' => '07:00', 'end_time' => '07:35'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('periods.0.number');

        // Break without a label.
        $this->actingAs($admin)
            ->postJson(route('bell-schedules.store'), [
                'name' => 'Reguler',
                'weekdays' => [1, 2, 3, 4],
                'periods' => [
                    ['kind' => 'break', 'start_time' => '09:15', 'end_time' => '09:30'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('periods.0.name');

        // Break carrying a lesson number.
        $this->actingAs($admin)
            ->postJson(route('bell-schedules.store'), [
                'name' => 'Reguler',
                'weekdays' => [1, 2, 3, 4],
                'periods' => [
                    ['kind' => 'break', 'number' => 1, 'name' => 'Istirahat', 'start_time' => '09:15', 'end_time' => '09:30'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('periods.0.number');
    }

    public function test_admin_can_update_a_schedule_and_periods_keep_their_identity(): void
    {
        $admin = User::factory()->admin()->create();
        $this->createScheduleWithLessons('Reguler', [1, 2, 3, 4], 3);
        BellSchedule::factory()->create(['name' => 'Jumat', 'weekdays' => [5]]);

        $schedule = BellSchedule::query()->where('name', 'Reguler')->with('periods')->firstOrFail();
        $periods = $schedule->periods->keyBy('number');
        $removed = $periods[2];

        $this->actingAs($admin)
            ->put(route('bell-schedules.update', $schedule), [
                'name' => 'Reguler Panjang',
                'weekdays' => [1, 2, 3, 4],
                'periods' => [
                    ['id' => $periods[1]->id, 'kind' => 'lesson', 'number' => 1, 'start_time' => '07:10', 'end_time' => '07:45'],
                    ['id' => $periods[3]->id, 'kind' => 'lesson', 'number' => 2, 'start_time' => '07:50', 'end_time' => '08:25'],
                    ['kind' => 'lesson', 'number' => 3, 'start_time' => '08:30', 'end_time' => '09:05'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('bell-schedules.index'));

        $schedule->refresh();

        $this->assertSame('Reguler Panjang', $schedule->name);

        // Kept rows preserve their identity so future slot references survive.
        $this->assertDatabaseHas('lesson_periods', [
            'id' => $periods[1]->id,
            'number' => 1,
            'start_time' => '07:10',
            'sort_order' => 1,
        ]);

        $this->assertDatabaseMissing('lesson_periods', ['id' => $removed->id]);

        $this->assertDatabaseHas('lesson_periods', [
            'bell_schedule_id' => $schedule->id,
            'number' => 3,
            'sort_order' => 3,
        ]);

        $this->assertSame(3, $schedule->periods()->count());
    }

    public function test_updating_a_schedule_that_double_covers_or_uncovers_a_day_returns_422(): void
    {
        $admin = User::factory()->admin()->create();
        $reguler = BellSchedule::factory()->create(['name' => 'Reguler', 'weekdays' => [1, 2, 3, 4]]);
        $jumat = BellSchedule::factory()->create(['name' => 'Jumat', 'weekdays' => [5]]);

        // Double coverage: the Friday schedule claims a weekday owned by Reguler.
        $this->actingAs($admin)
            ->putJson(route('bell-schedules.update', $jumat), [
                'name' => 'Jumat',
                'weekdays' => [4, 5],
                'periods' => $this->lessonRows(1),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('weekdays');

        // Uncovered: dropping Thursday leaves it without any schedule.
        $this->actingAs($admin)
            ->putJson(route('bell-schedules.update', $reguler), [
                'name' => 'Reguler',
                'weekdays' => [1, 2, 3],
                'periods' => $this->lessonRows(1),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('weekdays');

        $this->assertDatabaseHas('bell_schedules', ['id' => $jumat->id, 'weekdays' => json_encode([5])]);
        $this->assertDatabaseHas('bell_schedules', ['id' => $reguler->id, 'weekdays' => json_encode([1, 2, 3, 4])]);
    }

    /**
     * AC-13-01: deleting a schedule leaves its weekdays uncovered, so it is rejected.
     */
    public function test_deleting_a_schedule_that_would_leave_days_uncovered_returns_422(): void
    {
        $admin = User::factory()->admin()->create();
        $jumat = BellSchedule::factory()->create(['name' => 'Jumat', 'weekdays' => [5]]);

        $this->actingAs($admin)
            ->deleteJson(route('bell-schedules.destroy', $jumat))
            ->assertStatus(422);

        $this->assertDatabaseHas('bell_schedules', ['id' => $jumat->id]);
    }

    /**
     * A minimal run of consecutive lesson periods, 40 minutes apart.
     *
     * @return list<array<string, int|string>>
     */
    private static function lessonRows(int $count): array
    {
        $rows = [];

        foreach (range(1, $count) as $number) {
            $start = 7 * 60 + ($number - 1) * 40;

            $rows[] = [
                'kind' => 'lesson',
                'number' => $number,
                'start_time' => self::clockTime($start),
                'end_time' => self::clockTime($start + 35),
            ];
        }

        return $rows;
    }

    private static function clockTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function createScheduleWithLessons(string $name, array $weekdays, int $lessons): BellSchedule
    {
        return BellSchedule::factory()
            ->has(
                LessonPeriod::factory()
                    ->count($lessons)
                    ->sequence(fn (Sequence $sequence): array => [
                        'number' => $sequence->index + 1,
                        'sort_order' => $sequence->index + 1,
                        'start_time' => self::clockTime(7 * 60 + $sequence->index * 40),
                        'end_time' => self::clockTime(7 * 60 + $sequence->index * 40 + 35),
                    ]),
                'periods'
            )
            ->create(['name' => $name, 'weekdays' => $weekdays]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'principal' => ['principal'],
            'teacher' => ['teacher'],
            'counselor' => ['counselor'],
            'finance' => ['finance'],
            'staff' => ['staff'],
            'parent' => ['parent'],
            'student' => ['student'],
        ];
    }
}
