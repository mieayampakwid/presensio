<?php

namespace App\Services\StudentImport;

/**
 * Import run options (persisted alongside the remembered column mapping).
 */
class ImportOptions
{
    public function __construct(
        public readonly bool $autoCreateClasses = false,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromArray(array $options): self
    {
        return new self(
            autoCreateClasses: (bool) ($options['auto_create_classes'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'auto_create_classes' => $this->autoCreateClasses,
        ];
    }
}
