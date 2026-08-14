<?php

/*
 * Minimal cURL wrapper around Stripe's REST API. No SDK, no Composer —
 * this app has neither, so we talk to https://api.stripe.com directly.
 * Auth is HTTP Basic with the secret key as the username (Stripe's own
 * convention), no password.
 */
function stripe_request($method, $path, array $params = []) {
    $url = 'https://api.stripe.com/v1/' . $path;

    if ($method === 'GET' && $params) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['code' => 0, 'body' => (object) ['error' => (object) ['message' => $error]]];
    }

    return ['code' => $code, 'body' => json_decode($response)];
}
