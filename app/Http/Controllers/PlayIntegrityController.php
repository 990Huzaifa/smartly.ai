<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\PlayIntegrityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlayIntegrityController extends Controller
{
    public function verify(Request $request, PlayIntegrityService $playIntegrityService)
    {
        $request->validate([
            'integrity_token' => ['required', 'string'],
        ]);

        $result = $playIntegrityService->verifyAppRecognition(
            $request->integrity_token
        );
        Log::info(json_encode($result)); 

        return response()->json([
            'success' => true,
            'verified' => $result['verified'],
            'reason' => $result['reason'],
            'appRecognitionVerdict' => $result['appRecognitionVerdict'],
        ]);
    }
}