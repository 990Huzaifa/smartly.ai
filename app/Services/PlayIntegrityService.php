<?php

namespace App\Services;

use Exception;
use Google\Client as GoogleClient;
use Google\Service\PlayIntegrity;
use Google\Service\PlayIntegrity\DecodeIntegrityTokenRequest;
use Illuminate\Support\Facades\Log;

class PlayIntegrityService
{
    public function verifyAppRecognition(string $integrityToken): array
    {
        try {
            $packageName = config('play_integrity.package_name');

            if (!$packageName) {
                return [
                    'verified' => false,
                    'reason' => 'PACKAGE_NAME_MISSING',
                    'appRecognitionVerdict' => null,
                ];
            }

            $client = new GoogleClient();
            $credentialsPath = storage_path('app/firebase/user-firebase-credentials.json');
            $client->setAuthConfig($credentialsPath);
            $client->addScope('https://www.googleapis.com/auth/playintegrity');

            $service = new PlayIntegrity($client);

            $decodeRequest = new DecodeIntegrityTokenRequest();
            $decodeRequest->setIntegrityToken($integrityToken);

            $response = $service->v1->decodeIntegrityToken(
                $packageName,
                $decodeRequest
            );

            $payload = $response->getTokenPayloadExternal();

            if (!$payload) {
                return [
                    'verified' => false,
                    'reason' => 'EMPTY_PAYLOAD',
                    'appRecognitionVerdict' => null,
                ];
            }

            $appIntegrity = $payload->getAppIntegrity();

            if (!$appIntegrity) {
                return [
                    'verified' => false,
                    'reason' => 'APP_INTEGRITY_MISSING',
                    'appRecognitionVerdict' => null,
                ];
            }

            $appRecognitionVerdict = $appIntegrity->getAppRecognitionVerdict();

            $verified = $appRecognitionVerdict === 'PLAY_RECOGNIZED';

            return [
                'verified' => $verified,
                'reason' => $verified ? null : 'APP_NOT_PLAY_RECOGNIZED',
                'appRecognitionVerdict' => $appRecognitionVerdict,
            ];
        } catch (Exception $e) {
            Log::error('Play Integrity verification failed', [
                'message' => $e->getMessage(),
            ]);

            return [
                'verified' => false,
                'reason' => 'GOOGLE_VERIFICATION_ERROR',
                'appRecognitionVerdict' => null,
            ];
        }
    }
}