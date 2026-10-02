<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SchoolLogoController extends Controller
{
    /**
     * Stream school logo image from private storage for any authenticated user.
     */
    public function __invoke(): StreamedResponse|Response
    {
        $setting = SchoolSetting::first();
        $logoPath = $setting?->logo_path;

        if (! $logoPath || ! Storage::disk('local')->exists($logoPath)) {
            abort(404);
        }

        return Storage::disk('local')->response($logoPath);
    }
}
