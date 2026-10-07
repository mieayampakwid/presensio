<?php

namespace App\Services\Finance;

use App\Enums\JournalSource;
use Carbon\CarbonInterface;

final class PostableEntry
{
    /**
     * @param  list<PostableLine>  $lines
     */
    public function __construct(
        public CarbonInterface|string $entryDate,
        public string $description,
        public JournalSource $sourceType,
        public array $lines,
        public ?int $sourceId = null,
        public ?int $reversesEntryId = null,
        public ?string $attachmentPath = null,
        public ?int $createdByUserId = null,
    ) {}
}
