<?php

namespace App\Services\Announcements;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    public function render(string $body): string
    {
        $environment = new Environment(['html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 50, 'max_delimiters_per_line' => 1000]);
        $environment->addExtension(new CommonMarkCoreExtension);

        return (string) (new MarkdownConverter($environment))->convert($body);
    }
}
