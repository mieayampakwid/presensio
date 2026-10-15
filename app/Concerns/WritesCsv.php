<?php

namespace App\Concerns;

use RuntimeException;

/**
 * Shared CSV writer conventions (spec 06): stream to php://output and
 * emit the UTF-8 BOM first so Excel/Numbers read the file as UTF-8.
 */
trait WritesCsv
{
    /**
     * UTF-8 BOM first so Excel/Numbers read the file as UTF-8.
     *
     * @return resource
     */
    protected function openCsv()
    {
        $out = fopen('php://output', 'w');

        if ($out === false) {
            throw new RuntimeException('Unable to open the CSV output stream.');
        }

        fwrite($out, "\xEF\xBB\xBF");

        return $out;
    }
}
