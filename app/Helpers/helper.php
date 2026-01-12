<?php


function myMailSend($to, $name, $subject, $message, $link = null, $data = null){
    $payload = [
        "to"      => $to,
        "subject" => $subject,
        "name"    => $name,
        "message" => $message,
        "link"    => $link,
        "data"    => $data,
        "logo"    => 'https://tempmail.techvince.com/assets/images/logo.png',
        "from"    => 'TempMail Support',
    ];

    // Send using Guzzle HTTP client
    $client = new \GuzzleHttp\Client([
        'timeout' => 10,
        'verify'  => false, // if you have self‑signed certs
    ]);

    $response = $client->post('https://apluspass.zetdigi.com/form.php', [
        'json' => $payload,
    ]);

    // Optionally check for a successful response (e.g. HTTP 200 + success flag)
    if ($response->getStatusCode() !== 200) {
        // log, rollback, or throw
        throw new Exception('External mail API error: '.$response->getBody());
    }
    return true;
}