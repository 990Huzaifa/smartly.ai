<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Factory;
use Google\Cloud\Core\Timestamp;
use Google\Cloud\Firestore\FirestoreClient;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class FirebaseService
{
    protected $userMessaging;
    private FirestoreClient $firestore;
    private string $usersCollection = 'Users';



    public function __construct()
    {
        $credentialsPath = storage_path('app/firebase/user-firebase-credentials.json');

        if (!is_readable($credentialsPath)) {
            throw new \RuntimeException("Firebase credentials file not readable: {$credentialsPath}");
        }

        $serviceAccount = json_decode(file_get_contents($credentialsPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid Firebase credentials JSON: ' . json_last_error_msg());
        }

        $projectId = $serviceAccount['project_id'] ?? null;

        if (!$projectId) {
            throw new \RuntimeException('Firebase project_id missing in service account JSON.');
        }

        /*
        * Important:
        * Some Google Cloud clients still check Application Default Credentials.
        * So we set it manually at runtime too.
        */
        putenv("GOOGLE_APPLICATION_CREDENTIALS={$credentialsPath}");
        $_ENV['GOOGLE_APPLICATION_CREDENTIALS'] = $credentialsPath;
        $_SERVER['GOOGLE_APPLICATION_CREDENTIALS'] = $credentialsPath;

        // FCM Messaging
        $userFactory = (new Factory)
            ->withServiceAccount($credentialsPath);

        $this->userMessaging = $userFactory->createMessaging();

        // Firestore direct client
        $this->firestore = new FirestoreClient([
            'projectId' => $projectId,
            'keyFilePath' => $credentialsPath,
        ]);
    }



    /**
     * Send notification to single device
     */
    public function sendToDevice($fcmToken, $title, $body, $data = [])
    {
        try {
            $messaging = $this->userMessaging;
            
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $result = $messaging->send($message);
            
            // Log::info('FCM notification sent successfully', [
            //     'token' => $fcmToken,
            //     'result' => $result
            // ]);
            
            return [
                'success' => true,
                'result' => $result
            ];
            
        } catch (\Exception $e) {
            // Log::error('FCM notification failed', [
            //     'token' => $fcmToken,
            //     'error' => $e->getMessage()
            // ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to multiple devices of same app type
     */
    public function sendToMultipleDevices($fcmTokens, $title, $body, $data = [])
    {
        try {
            $messaging = $this->userMessaging;
            
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $result = $messaging->sendMulticast($message, $fcmTokens);
            
            // Log::info('FCM multicast notification sent', [
            //     'tokens_count' => count($fcmTokens),
            //     'success_count' => $result->successes()->count(),
            //     'failure_count' => $result->failures()->count()
            // ]);
            
            return [
                'success' => true,
                'result' => $result,
                'success_count' => $result->successes()->count(),
                'failure_count' => $result->failures()->count()
            ];
            
        } catch (\Exception $e) {
            // Log::error('FCM multicast notification failed', [
            //     'tokens_count' => count($fcmTokens),
            //     'error' => $e->getMessage()
            // ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function updateUserPlan(User $user, string $plan, array $extraData = []): bool
    {
        if (empty($user->id)) {
            Log::warning('Firestore plan update skipped: user id missing.');

            return false;
        }

        try {
            $payload = array_merge([
                'subscriptionPlan' => $plan,
            ], $extraData);

            /**
             * Important:
             * Firestore is type-sensitive.
             * If user_id in Firestore is stored as number, use (int) $user->id.
             * If user_id in Firestore is stored as string, use (string) $user->id.
             */
            $documents = $this->firestore
                ->collection($this->usersCollection)
                ->where('user_id', '=', (int) $user->id)
                ->limit(1)
                ->documents();

            $found = false;

            foreach ($documents as $document) {
                if (!$document->exists()) {
                    continue;
                }

                $this->firestore
                    ->collection($this->usersCollection)
                    ->document($document->id())
                    ->set($payload, [
                        'merge' => true,
                    ]);

                $found = true;

                Log::info('Firestore user plan updated successfully.', [
                    'db_user_id' => $user->id,
                    'firestore_document_id' => $document->id(),
                    'plan' => $plan,
                ]);

                break;
            }

            if (!$found) {
                Log::warning('Firestore user not found by user_id.', [
                    'db_user_id' => $user->id,
                    'plan' => $plan,
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Firestore user plan update failed.', [
                'db_user_id' => $user->id,
                'plan' => $plan,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function firestoreTimestamp(): Timestamp
    {
        return new Timestamp(now()->toDateTimeImmutable());
    }
}