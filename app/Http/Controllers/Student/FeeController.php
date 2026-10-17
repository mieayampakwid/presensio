<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Finance\FeePortal;
use App\Services\SchoolSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeeController extends Controller
{
    /**
     * Read-only fee view for the authenticated student's own bills and payments.
     */
    public function index(Request $request, FeePortal $portal, SchoolSettings $settings): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $student = $user->student;
        abort_if($student === null, 403, 'Profil siswa tidak ditemukan.');

        $school = $settings->row();

        return Inertia::render('parent/fees', [
            'portal' => $portal->forStudents([$student->id]),
            'students' => $portal->children([$student->id]),
            'schoolBank' => [
                'bank_name' => $school->bank_name,
                'bank_account_number' => $school->bank_account_number,
                'bank_account_holder' => $school->bank_account_holder,
            ],
            'canUpload' => false,
            'transferAccountConfigured' => false,
        ]);
    }
}
