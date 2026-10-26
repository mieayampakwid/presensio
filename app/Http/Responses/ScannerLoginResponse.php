<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Laravel\Fortify\Http\Responses\LoginResponse;
use Symfony\Component\HttpFoundation\Response;

class ScannerLoginResponse extends LoginResponse
{
    /** @param Request $request */
    public function toResponse($request): Response
    {
        if ($request->user()?->hasRole(UserRole::ScannerOperator)) {
            $request->session()->forget('url.intended');
            if (! $request->wantsJson()) {
                return redirect()->route('scanner.index');
            }
        }

        return parent::toResponse($request);
    }
}
