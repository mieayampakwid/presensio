<?php

namespace Tests\Unit\Models;

use App\Models\ReportCard;
use App\Models\ReportCardPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ReportCardPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_scope_excludes_retracted_publications(): void
    {
        $current = ReportCardPublication::factory()->create();
        $retracted = ReportCardPublication::factory()->retracted()->create();

        $currentIds = ReportCardPublication::current()->pluck('id');

        $this->assertContains($current->id, $currentIds);
        $this->assertNotContains($retracted->id, $currentIds);
    }

    public function test_report_card_rejects_payload_updates(): void
    {
        $card = ReportCard::factory()->create([
            'payload' => ['student' => ['name' => 'Budi Santoso']],
        ]);
        $originalPayload = $card->payload;

        $card->payload = ['student' => ['name' => 'Berubah']];

        try {
            $card->save();
            $this->fail('Report card snapshots must never be updated.');
        } catch (LogicException) {
        }

        $this->assertSame($originalPayload, $card->fresh()->payload);
    }
}
