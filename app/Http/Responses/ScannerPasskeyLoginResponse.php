<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Laravel\Passkeys\Http\Responses\PasskeyLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class ScannerPasskeyLoginResponse extends PasskeyLoginResponse
{
    /** @param Request $request */
    public function toResponse($request): Response
    {
        if ($request->user()?->hasRole(UserRole::ScannerOperator)) {
            $request->session()->forget('url.intended');

            return $request->wantsJson()
                ? response()->json(['redirect' => route('scanner.index')])
                : redirect()->route('scanner.index');
        }

        return parent::toResponse($request);
    }
}
