<?php

namespace App\Jobs;

use App\Models\CreditsTransaction;
use App\Models\CreditsWallet;
use App\Models\Premium;
use App\Models\Subscription;
use App\Models\User;
use App\Services\GoogleAuthService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessGoogleNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $base64Data;

    public function __construct(string $base64Data)
    {
        $this->base64Data = $base64Data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $decodedJsonString = base64_decode($this->base64Data);
            $notification = json_decode($decodedJsonString, true);

                // Log::info('RTDN Job: Notification Received.', [
                //     'notification' => $notification
                // ]);
                // 2. Route the Notification
                if (isset($notification['testNotification'])) {
                    $this->handleTestNotification($notification);
                } elseif (isset($notification['subscriptionNotification'])) {
                    $this->handleSubscriptionNotification($notification);
                }elseif (isset($notification['voidedPurchaseNotification'])) {
                    $this->handleVoidedPurchaseNotification($notification);
                } else {
                    Log::warning('RTDN Job: Unhandled Notification Type.', $notification);
                }
        } catch (Exception $e) {
            // This will ensure the job fails and can be retried if necessary,
            // but the webhook itself already returned 200 OK.
            Log::error('RTDN Job failed to process notification.', [
                'error' => $e->getMessage(),
                'base64' => $this->base64Data
            ]);

            // Optional: Re-throw the exception to trigger Laravel queue retry
            // throw $e; 
        }
    }

    protected function handleTestNotification(array $notification)
    {
        Log::info('RTDN Job: Test Notification successfully confirmed.', [
            'package' => $notification['packageName']
        ]);
        // No further action is usually required for a test.
    }

    protected function handleSubscriptionNotification(array $notification)
    {
        $subNotification = $notification['subscriptionNotification'];
        $type = $subNotification['notificationType'] ?? 'UNKNOWN';
        $purchaseToken = $subNotification['purchaseToken'] ?? 'N/A';
        $subscriptionId = $subNotification['subscriptionId'] ?? 'N/A';

        $data = $this->verifyFromGoogle($purchaseToken, $subscriptionId);

        $orderId = $data['orderId'] ?? 'N/A';
        $orderDetails = $this->getOrderDetails($orderId);
        $productId = $orderDetails['lineItems'][0]['subscriptionDetails']['basePlanId'] ?? null;
        Log::info("RTDN Job: Subscription event received.", [
            'type_code' => $type,
            'orderId' => $orderId,
            'basePlanId' => $productId,
            'data'=> $data
        ]);

        $filterData = [
            "start"=> $data['startTimeMillis'],
            "expiry"=> $data['expiryTimeMillis'],
            "price"=> $data['priceAmountMicros'],
            "user_id"=> $data['obfuscatedExternalAccountId']
        ];
        $filterData = $this->filter($filterData);
        $data = array_merge($data, $filterData);

        // ============================
        // 🎯 PLAN MAPPING
        // ============================
        
        $planConfig = [
            'premium-monthly'      => ['duration' => 'monthly', "is_premium" => true],
            'premium-yearly'       => ['duration' => 'yearly', "is_premium" => true],

            'basic-credt-monthly'      => ['credits' => 10,  'type' => 'credits_monthly', 'duration' => 'monthly',"is_premium" => false],
            'basic-credt-yearly'       => ['credits' => 10,  'type' => 'credits_annual',  'duration' => 'yearly',"is_premium" => false],
            'unlimited-credt-monthly'  => ['credits' => 0,   'type' => 'unlimited',       'duration' => 'monthly',"is_premium" => false],
            'unlimited-credt-yearly'   => ['credits' => 0,   'type' => 'unlimited',       'duration' => 'yearly',"is_premium" => false],
            
            'basic-cred-monthly'      => ['credits' => 10,  'type' => 'credits_monthly', 'duration' => 'monthly',"is_premium" => false],
            'basic-cred-yearly'       => ['credits' => 10,  'type' => 'credits_annual',  'duration' => 'yearly',"is_premium" => false],
            'unlimited-cred-monthly'  => ['credits' => 0,   'type' => 'unlimited',       'duration' => 'monthly',"is_premium" => false],
            'unlimited-cred-yearly'   => ['credits' => 0,   'type' => 'unlimited',       'duration' => 'yearly',"is_premium" => false],


        ];

        $plan = $planConfig[$productId] ?? null;
        
        if($plan['is_premium']){
            $this->premium($type, $data, $productId, $plan);
        }
        else{
            $this->subscription( $type, $data, $productId, $plan);
        }


    }

    protected function handleVoidedPurchaseNotification(array $notification)
    {
        $voidNotification = $notification['voidedPurchaseNotification'];
        
        // Note: voidedPurchaseNotification is used for both subs AND one-time purchases
        $purchaseToken = $voidNotification['purchaseToken'] ?? 'N/A';
        $orderId = $voidNotification['orderId'] ?? 'N/A';
        
        // Note: The notification doesn't give you the subscriptionId directly, 
        // but the orderId/purchaseToken is enough to look it up in your system.
        // If you need the full subscription details, you might call the Google API 
        // to verify the purchase token against all possible products.

        // 1. Find the subscription or purchase in your database
        // $subscription = Subscription::where('purchase_token', $purchaseToken)
        //                             ->where('platform', 'google')
        //                             ->first();

        Log::info("RTDN Job: Voided Purchase event received (Refund/Revoke).", [
            'purchaseToken' => $purchaseToken,
            'orderId' => $orderId,
            // The refundType is also available in this notification if needed for logging:
            // 'refundType' => $voidNotification['refundType'] ?? 'N/A', 
        ]);

        // 2. Process the refund/void action
        // if ($subscription) {
            
        //     // Revoke Access: The user should immediately lose their premium features.
        //     // The purchase is no longer valid, regardless of the original expiration date.
        //     $subscription->update([
        //         'status' => 'voided',          // Mark as voided/refunded
        //         'canceled_at' => Carbon::now(), // Treat as immediately canceled
        //         'expires_at' => Carbon::now(),  // Set the expiration to now to remove access
        //         'revoked_at' => Carbon::now(),
        //     ]);

        //     // Optional: Implement a mechanism to handle credit deductions
        //     // If the user was given credits for this purchase, you must now deduct them.
        //     // This logic is highly specific to your credit system.
        //     // Example: $this->deductCreditsForVoidedSubscription($subscription);
            
        //     Log::info("RTDN Job: Subscription successfully voided and access revoked.", [
        //         'user_id' => $subscription->user_id,
        //         'purchaseToken' => $purchaseToken,
        //     ]);
        // } else {
        //     // Handle case where it might be a voided one-time product or a subscription 
        //     // that wasn't properly tracked/created initially.
        //     Log::warning("RTDN Job: Voided Purchase Token not found in database.", [
        //         'purchaseToken' => $purchaseToken,
        //         'orderId' => $orderId,
        //     ]);
        // }
    }

    protected function verifyFromGoogle($token, $parent_id)
    {
        // 1. Access Token generate karein
        $packageName = config('services.google.android_package_name');
        $authService = new GoogleAuthService();
        $accessToken = $authService->getAccessToken();

        if (!$accessToken) {
            Log::error('Cannot proceed, Google Access Token not available.');
            return null;
        }

        // 2. Google Play Developer API ka Endpoint URL
        $url = "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$packageName}/purchases/subscriptions/{$parent_id}/tokens/{$token}";

        try {
            // 3. GET Request bhejhe Access Token header ke saath
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Accept' => 'application/json',
            ])->get($url);

            // 4. Response check karein
            if ($response->successful()) {
                // Success! Response mein subscription details hain
                return $response->json();
            } else {
                // API error (e.g., token invalid, not found)
                Log::warning("Google Verification Failed: " . $response->body());
                return  null;
            }
        } catch (Exception $e) {
            // Connection/Guzzle error
            Log::error("HTTP Request Error to Google: " . $e->getMessage());
            return $e->getMessage();
        }
    }

    private function filter(array $data){
        
        if (isset($data['price'])) {
            $actualPrice = (float) $data['price'] / 1000000; 
            $data['price'] = $actualPrice;
        }
        if (isset($data['start'])) {
            $timestampInSeconds = floor($data['start'] / 1000);
            $data['start'] = Carbon::createFromTimestamp($timestampInSeconds)->toDateTimeString();

        }
        if (isset($data['expiry'])) {
            $timestampInSeconds = floor($data['expiry'] / 1000);
            $data['expiry'] = Carbon::createFromTimestamp($timestampInSeconds)->toDateTimeString();

        }

        return $data;
    }

    private function getOrderDetails($orderId){

        // 1. Access Token generate karein
        $packageName = config('services.google.android_package_name');
        $authService = new GoogleAuthService();
        $accessToken = $authService->getAccessToken();

        if (!$accessToken) {
            Log::error('Cannot proceed, Google Access Token not available.');
            return null;
        }

        // 2. Google Play Developer API ka Endpoint URL
        $url = "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$packageName}/orders/{$orderId}";

        try {
            // 3. GET Request bhejhe Access Token header ke saath
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Accept' => 'application/json',
            ])->get($url);

            // 4. Response check karein
            if ($response->successful()) {
                // Success! Response mein subscription details hain
                return $response->json();
            } else {
                // API error (e.g., token invalid, not found)
                Log::warning("Google Verification Failed: " . $response->body());
                return  null;
            }
        } catch (Exception $e) {
            // Connection/Guzzle error
            Log::error("HTTP Request Error to Google: " . $e->getMessage());
            return $e->getMessage();
        }
    }


    // handle both groups of plans

    public function premium($type, $data, $productId, $plan){

        $premium = Premium::where('transaction_id', $data['obfuscatedExternalAccountId'])->where('platform', 'google')->first();
        switch ((int)$type) {
            case 4: // SUBSCRIPTION_PURCHASED (New subscription) insert new subscription but first check if already exists
                $existingPremium = Premium::where('user_id', $data['obfuscatedExternalAccountId'])
                    ->where('platform', 'google')
                    ->where('plan', $productId)
                    ->first();
                if(!$existingPremium && $plan){
                    // Create new Premium
                    $newPremium = Premium::create([
                        'user_id'           => $data['obfuscatedExternalAccountId'],
                        'plan'              => $productId,
                        'expires_at'        => $data['expiry'],
                        'renewal_period'    => $plan['duration'],
                        'platform'          => 'google',
                        'status'            => 'active',
                    ]);

                    $user = User::find($newPremium->user_id);
                    if($user){
                        $user->update([
                            'is_premium' => true,
                        ]);
                    }
 
                }
                break;
            case 2: // SUBSCRIPTION_RENEWED
                if($premium){
                    $premium->update([
                        'plan'              => $productId,
                        'expires_at'        => $data['expiry'],
                        'status'            => 'active',
                        'canceled_at'      => null,
                    ]);
                }
                break;
            case 3: // SUBSCRIPTION_CANCELED
                if($premium){
                    $premium->update([
                        'canceled_at' => Carbon::now(),
                    ]);
                }
                break;
            case 13: // SUBSCRIPTION_EXPIRED
                if($premium){
                    $premium->update([
                        'status' => 'expired',
                    ]);

                    $user = User::find($premium->user_id);
                    if($user){
                        $user->update([
                            'is_premium' => false,
                        ]);
                    }
                }
                break;
            // ... include other types like RECOVERED, ON_HOLD, etc.
        }
        
    }

    public function subscription($type, $data, $productId, $plan){
        // credit based plan handling logic

        switch ((int)$type) {
            case 4: // SUBSCRIPTION_PURCHASED (New subscription) insert new subscription but first check if already exists
                $existingSubscription = Subscription::where('user_id', $data['obfuscatedExternalAccountId'])
                    ->where('platform', 'google')
                    ->where('plan', $productId)
                    ->first();
                if(!$existingSubscription && $plan){
                    // Create new subscription
                    $newSubscription = Subscription::create([
                        'user_id'           => $data['obfuscatedExternalAccountId'],
                        'plan'              => $productId,
                        'credits_per_month' => $plan['credits'],
                        'released_credits'  => ($plan['type'] === 'credits_annual' ? 10 : 0),
                        'total_credits'     => ($plan['type'] === 'credits_annual' ? 120 : 0),
                        'starts_at'         => $data['start'],
                        'expires_at'        => $data['expiry'],
                        'renewal_period'    => $plan['duration'],
                        'last_released_at'  => ($plan['type'] === 'credits_annual' ? Carbon::now() : null),
                        'platform'          => 'google',
                        'status'            => 'active',
                    ]);

                    CreditsTransaction::create([
                        'user_id'  => $newSubscription->user_id,
                        'type'     => 'plan_release',
                        'credits'  => $plan['credits'],
                        'source'   => 'subscription',
                        'ref' => 'purchased successfully',
                    ]);

                    $wallet = CreditsWallet::where('user_id', $newSubscription->user_id)->first();
                    if($wallet){
                        $wallet->paid_credits = $plan['credits'];
                        $wallet->unlimited_active = $plan['type'] === 'unlimited' ? true : false;
                        $wallet->save();    
                    }
                }
                break;
            case 2: // SUBSCRIPTION_RENEWED
                $subscription = Subscription::where('user_id', $data['obfuscatedExternalAccountId'])->where('platform', 'google')->first();
                if($subscription){
                    $subscription->update([
                        'plan'              => $productId,
                        'credits_per_month' => $plan['credits'],
                        'released_credits'  => ($plan['type'] === 'credits_annual' ? 10 : 0),
                        'total_credits'     => ($plan['type'] === 'credits_annual' ? 120 : 0),
                        'expires_at'        => $data['expiry'],
                        'renewal_period'    => $plan['duration'],
                        'last_released_at'  =>($plan['type'] === 'credits_annual' ? Carbon::now() : null),
                        'status'            => 'active',
                        'canceled_at'      => null,
                    ]);

                    CreditsTransaction::create([
                        'user_id'  => $subscription->user_id,
                        'type'     => 'plan_release',
                        'credits'  => $plan['credits'],
                        'source'   => 'subscription',
                        'ref' => 'Renewal successfull',
                    ]);

                    $wallet = CreditsWallet::where('user_id', $subscription->user_id)->first();
                    if($wallet){
                        $wallet->paid_credits = $plan['credits'];
                        $wallet->unlimited_active = $plan['type'] === 'unlimited' ? true : false;
                        $wallet->save();
                    }
                }
                break;
            case 3: // SUBSCRIPTION_CANCELED
                $subscription = Subscription::where('user_id', $data['obfuscatedExternalAccountId'])->where('platform', 'google')->first();
                if($subscription){
                    $subscription->update([
                        'canceled_at' => Carbon::now(),
                    ]);
                }
                break;
            case 13: // SUBSCRIPTION_EXPIRED
                $subscription = Subscription::where('user_id', $data['obfuscatedExternalAccountId'])->where('plan',$productId)->where('platform', 'google')->first();
                if($subscription){
                    $subscription->update([
                        'status' => 'expired',
                    ]);

                    // update wallet
                    $wallet = CreditsWallet::where('user_id', $subscription->user_id)->first();
                    if($plan['type'] === 'unlimited'){
                        $wallet->update([
                            'unlimited_active' => false
                        ]);
                    }else{
                        $wallet->update([
                            'paid_credits' => 0
                        ]);
                    }
                }
                break;
            // ... include other types like RECOVERED, ON_HOLD, etc.
        }
    }
}
