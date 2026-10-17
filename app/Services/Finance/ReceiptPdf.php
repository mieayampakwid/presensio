<?php

namespace App\Services\Finance;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\SchoolSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;

class ReceiptPdf
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly Terbilang $terbilang,
    ) {}

    /**
     * Render the Kuitansi as HTML. Only verified or void payments have one.
     */
    public function html(Payment $payment): string
    {
        return $this->view($payment)->render();
    }

    /**
     * Render the Kuitansi to PDF bytes (A4 portrait, local fonts, remote assets disabled).
     */
    public function pdf(Payment $payment): string
    {
        $dompdf = Pdf::loadHtml($this->html($payment))->setPaper('a4', 'portrait');
        $dompdf->getDomPDF()->getOptions()->setIsRemoteEnabled(false);

        return (string) $dompdf->output();
    }

    private function view(Payment $payment): View
    {
        $payment->loadMissing(['allocations.bill.student', 'verifiedBy.employee']);
        $date = $payment->payment_date->toDateString();
        $row = $this->settings->row();
        $logoPath = $row->logo_path;
        $logo = $logoPath !== null && Storage::disk('local')->exists($logoPath)
            ? 'data:'.(preg_match('/\.jpe?g$/', $logoPath) ? 'image/jpeg' : 'image/png').';base64,'.base64_encode((string) Storage::disk('local')->get($logoPath))
            : null;

        return view('pdf.receipt', [
            'payment' => $payment,
            'isVoid' => $payment->status === PaymentStatus::Void,
            'schoolName' => $this->settings->schoolName(),
            'schoolAddress' => $row->school_address,
            'logoSrc' => $logo,
            'lines' => $payment->allocations->map(fn ($allocation) => [
                'student' => $allocation->bill->student->full_name,
                'student_number' => $allocation->bill->student->student_number,
                'class' => $allocation->bill->student->classOn($date)?->name,
                'title' => $allocation->bill->title,
                'amount' => $allocation->amount,
            ])->all(),
            'words' => $this->terbilang->rupiah($payment->amount),
        ]);
    }
}
