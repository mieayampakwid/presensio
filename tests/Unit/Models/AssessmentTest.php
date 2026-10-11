<?php

namespace Tests\Unit\Models;

use App\Enums\AssessmentKind;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\LearningObjective;
use App\Models\SchoolSetting;
use App\Models\Score;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_belongs_to_class_subject_and_semester(): void
    {
        $classSubject = ClassSubject::factory()->create();
        $semester = Semester::factory()->create();

        $assessment = Assessment::factory()->create([
            'class_subject_id' => $classSubject->id,
            'semester_id' => $semester->id,
            'kind' => AssessmentKind::SummativeScope,
        ]);

        $this->assertTrue($assessment->classSubject->is($classSubject));
        $this->assertTrue($assessment->semester->is($semester));
        $this->assertSame(AssessmentKind::SummativeScope, $assessment->kind);
        $this->assertFalse($assessment->isReleased());
    }

    public function test_assessment_has_many_scores_and_belongs_to_many_learning_objectives(): void
    {
        $assessment = Assessment::factory()->create();
        $tp1 = LearningObjective::factory()->create();
        $tp2 = LearningObjective::factory()->create();

        $assessment->learningObjectives()->attach([$tp1->id, $tp2->id]);

        $score = Score::factory()->create([
            'assessment_id' => $assessment->id,
            'score' => '85.00',
        ]);

        $this->assertCount(2, $assessment->learningObjectives);
        $this->assertTrue($assessment->scores->contains($score));
        $this->assertTrue($score->assessment->is($assessment));
    }

    public function test_deleting_learning_objective_linked_to_assessment_is_restricted(): void
    {
        $assessment = Assessment::factory()->create();
        $tp = LearningObjective::factory()->create();

        $assessment->learningObjectives()->attach($tp->id);

        $this->expectException(QueryException::class);
        $tp->delete();
    }

    public function test_deleting_assessment_cascades_scores_and_pivot_but_preserves_objectives(): void
    {
        $assessment = Assessment::factory()->create();
        $tp = LearningObjective::factory()->create();
        $assessment->learningObjectives()->attach($tp->id);

        $score = Score::factory()->create(['assessment_id' => $assessment->id]);

        $assessment->delete();

        $this->assertDatabaseMissing('assessments', ['id' => $assessment->id]);
        $this->assertDatabaseMissing('scores', ['id' => $score->id]);
        $this->assertDatabaseMissing('assessment_objectives', [
            'assessment_id' => $assessment->id,
            'learning_objective_id' => $tp->id,
        ]);
        $this->assertDatabaseHas('learning_objectives', ['id' => $tp->id]);
    }

    public function test_class_subject_is_in_use_checks_assessments(): void
    {
        $classSubject = ClassSubject::factory()->create();

        $this->assertFalse($classSubject->isInUse());

        Assessment::factory()->create([
            'class_subject_id' => $classSubject->id,
        ]);

        $this->assertTrue($classSubject->fresh()->isInUse());
    }

    public function test_course_semester_lazy_creation_and_open_guard(): void
    {
        $setting = SchoolSetting::first();
        if ($setting) {
            $setting->update([
                'default_scope_weight' => '60.00',
                'default_final_weight' => '40.00',
            ]);
        }

        $classSubject = ClassSubject::factory()->create();
        $semester = Semester::factory()->create();

        $courseSemester = CourseSemester::forCourse($classSubject, $semester);

        $this->assertEquals('60.00', $courseSemester->scope_weight);
        $this->assertEquals('40.00', $courseSemester->final_weight);
        $this->assertFalse($courseSemester->isFinalized());

        // assertOpen passes when not finalized
        $courseSemester->assertOpen();

        // When finalized, assertOpen throws 422
        $user = User::factory()->create();
        $courseSemester->update([
            'finalized_at' => now(),
            'finalized_by_user_id' => $user->id,
        ]);

        $this->assertTrue($courseSemester->fresh()->isFinalized());

        try {
            $courseSemester->assertOpen();
            $this->fail('Expected HttpException 422');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
