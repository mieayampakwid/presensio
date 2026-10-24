<?php

namespace Tests\Unit\Services\Announcements;

use App\Services\Announcements\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

class MarkdownRendererTest extends TestCase
{
    public function test_renders_markdown_without_html_or_unsafe_links(): void
    {
        $html = (new MarkdownRenderer)->render("# Judul\n\n<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n\n[klik](javascript:alert(1))\n\n[aman](https://example.com)");
        $this->assertStringContainsString('<h1>Judul</h1>', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
        foreach (['<script', '<img', 'javascript:', 'onerror'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $html);
        }
    }
}
