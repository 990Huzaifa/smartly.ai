<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessGoogleNotification;
use Illuminate\Http\Request;

class WebhookController extends Controller
{


    public function handleGoogle(Request $request)
    {

        $data = $request->input('message.data');
        // here ye need to set a job for better and background processing
        ProcessGoogleNotification::dispatch($data)->onQueue('google-webhooks');
        // Must return a 200 status code to acknowledge receipt
        return response()->json(['status' => 'ok'], 200);
    }
}
