<?php

namespace Tests\Unit;

use Illuminate\Support\Arr;
use Tests\TestCase;

class LangParityTest extends TestCase
{
    public function test_every_english_key_has_an_indonesian_translation(): void
    {
        foreach (glob(lang_path('en/*.php')) as $file) {
            $group = basename($file, '.php');
            $indonesian = lang_path("id/{$group}.php");

            $this->assertFileExists($indonesian, "lang/id/{$group}.php is missing");

            $this->assertSame(
                array_keys(Arr::dot(require $file)),
                array_keys(Arr::dot(require $indonesian)),
                "lang/id/{$group}.php keys differ from lang/en/{$group}.php",
            );
        }
    }
}
