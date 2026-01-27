<?php

namespace App\Console\Commands;

use App\Models\CreditsWallet;
use Illuminate\Console\Command;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class TrackSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:track-subscription';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $subscriptions = Subscription::where('status', 'active')->get();
        $expiry_count = 0;
        foreach ($subscriptions as $subscription) {
            // check if the subscription is expired by expires_at date reached
            if(Now()->greaterThan($subscription->expires_at)) {
                $expiry_count++;
                $subscription->status = 'expired';
                $subscription->save();

                // notify user by email
                // $user = $subscription->user;
                // Mail::to($user->email)->send(new SubscriptionExpired($user));
            }
        }
    }
}
