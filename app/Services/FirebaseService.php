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
        if (empty($user->firebase_uid)) {
            Log::warning('Firestore plan update skipped: firebase_uid missing.', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        try {
            $payload = array_merge([
                'subscriptionPlan' => $plan,
                'planUpdatedAt' => $this->firestoreTimestamp(),
                'updatedFrom' => 'laravel_backend',
            ], $extraData);

            $this->firestore
                ->collection($this->usersCollection)
                ->document($user->firebase_uid)
                ->set($payload, [
                    'merge' => true,
                ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Firestore user plan update failed.', [
                'user_id' => $user->id,
                'firebase_uid' => $user->firebase_uid,
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