<?php

use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\Http;

// Exercise Laravel's real verifier and parser with controlled service responses.
test('breach checks verify TLS and send only a hash prefix with padding requested', function () {
    $candidate = 'SyntheticPassword2026!';
    $hash = strtoupper(sha1($candidate));
    $requests = [];
    Http::fake(function ($request, $options) use (&$requests, $hash) {
        $requests[] = [$request, $options];
        return Http::response(substr($hash, 5).":42\n".str_repeat('0', 35).':0', 200);
    });
    $verifier = app(UncompromisedVerifier::class);
    expect($verifier->verify(['value' => $candidate, 'threshold' => 0]))->toBeFalse();
    [$request, $options] = $requests[0];
    expect($request->url())->toBe('https://api.pwnedpasswords.com/range/'.substr($hash, 0, 5))
        ->and($request->method())->toBe('GET')
        ->and($request->body())->toBe('')
        ->and($request->hasHeader('Add-Padding'))->toBeTrue()
        ->and($options['verify'] ?? true)->toBeTrue();
    if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
        expect($options['curl'][CURLOPT_SSL_OPTIONS])->toBe(CURLSSLOPT_NATIVE_CA);
    }
    // The transport adjustment must not leak to unrelated application requests.
    Http::get('https://example.test/unrelated');
    expect($requests[1][1]['curl'][CURLOPT_SSL_OPTIONS] ?? null)->toBeNull();
});

test('clean password and breach-count thresholds keep the existing verifier behavior', function () {
    $candidate = 'SyntheticPassword2026!';
    Http::fake(['api.pwnedpasswords.com/*' => Http::response(substr(strtoupper(sha1($candidate)), 5).':2', 200)]);
    $verifier = app(UncompromisedVerifier::class);
    expect($verifier->verify(['value' => $candidate, 'threshold' => 0]))->toBeFalse()
        ->and($verifier->verify(['value' => $candidate, 'threshold' => 2]))->toBeTrue()
        ->and($verifier->verify(['value' => 'DifferentSynthetic2026!', 'threshold' => 0]))->toBeTrue();
});
