<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Factory;
use Google\Cloud\Core\Timestamp;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class FirebaseService
{
    protected $userMessaging;
    private FirestoreClient $firestore;
    private string $usersCollection = 'Users';



    public function __construct()
    {
        // Initialize Firebase
        $userFactory = (new Factory)
            ->withServiceAccount(storage_path('app/firebase/user-firebase-credentials.json'));
        $this->userMessaging = $userFactory->createMessaging();
        $this->firestore = $userFactory->createFirestore()->database();
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