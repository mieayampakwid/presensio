<?php

namespace App\Services\ReportCards;

use App\Enums\Curriculum;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;

class ReportCardPdf
{
    /**
     * Render the snapshot payload as the Merdeka HTML document. Only the
     * payload is read: later school-profile, logo or result changes never
     * alter the output.
     *
     * @param  array<string, mixed>  $payload
     */
    public function html(array $payload, ?string $curriculum = null): string
    {
        return $this->view($payload, $curriculum)->render();
    }

    /**
     * Render the snapshot payload to PDF bytes (A4 portrait, 2 cm margins,
     * local DejaVu fonts, remote assets disabled).
     *
     * @param  array<string, mixed>  $payload
     */
    public function pdf(array $payload, ?string $curriculum = null): string
    {
        $dompdf = Pdf::loadHtml($this->view($payload, $curriculum)->render())
            ->setPaper('a4', 'portrait');
        $dompdf->getDomPDF()->getOptions()->setIsRemoteEnabled(false);

        return (string) $dompdf->output();
    }

    /**
     * Render one combined PDF with every student snapshot of a class, one
     * page each.
     *
     * @param  array<int, array<string, mixed>>  $payloads
     */
    public function bundle(array $payloads, ?string $curriculum = null): string
    {
        $dompdf = Pdf::loadHtml($this->bundleView($payloads, $curriculum)->render())
            ->setPaper('a4', 'portrait');
        $dompdf->getDomPDF()->getOptions()->setIsRemoteEnabled(false);

        return (string) $dompdf->output();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function view(array $payload, ?string $curriculum): View
    {
        $template = match ($curriculum ?? Curriculum::Merdeka->value) {
            Curriculum::Merdeka->value => 'pdf.report-card-merdeka',
            default => throw new InvalidArgumentException("Unsupported curriculum: {$curriculum}"),
        };

        return view($template, ['payload' => $payload]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     */
    private function bundleView(array $payloads, ?string $curriculum): View
    {
        $template = match ($curriculum ?? Curriculum::Merdeka->value) {
            Curriculum::Merdeka->value => 'pdf.report-card-bundle',
            default => throw new InvalidArgumentException("Unsupported curriculum: {$curriculum}"),
        };

        return view($template, ['payloads' => $payloads]);
    }
}
