# Password breach-check certificate fix

Resolved 2026-09-15.

## Cause and change

The active Herd Lite PHP runtime uses cURL 8.11.0 with OpenSSL on Windows. Its curl.cainfo and openssl.cafile settings are empty. A direct verified HTTPS request to the breach-check service failed with cURL error 60; the same request using Windows native CA trust returned HTTP 200.

AppServiceProvider now extends Laravel's UncompromisedVerifier binding on Windows runtimes that expose CURLSSLOPT_NATIVE_CA. It supplies the existing NotPwnedVerifier with a cloned HTTP factory configured with verify=true and CURLOPT_SSL_OPTIONS=CURLSSLOPT_NATIVE_CA. The operating system's trusted certificates can therefore validate the service's HTTPS connection.

The change is limited to the password breach checker. It does not change global HTTP defaults, install certificates, modify php.ini or disable certificate/hostname verification. Other operating systems retain Laravel's default verifier. Laravel's existing hashing, range lookup, padding, breach-count threshold, password policy and service-outage behavior are retained. Only the five-character SHA-1 prefix is sent to the service; full passwords and full hashes are not sent.

cURL documents that the native CA option adds the operating system trust store to certificate verification and is supported by OpenSSL on Windows: [CURLOPT_SSL_OPTIONS](https://curl.se/libcurl/c/CURLOPT_SSL_OPTIONS.html). The PHP configuration options inspected during diagnosis are documented in the [PHP cURL configuration reference](https://www.php.net/curl.configuration.php).

## Verification

- Live application verifier, without mocks: two HTTPS 200 responses; known leaked sample rejected, generated clean sample accepted. Requiring actual recorded successful responses prevents a network failure being mistaken for a successful clean-password check.
- Live Chrome profile-form checks, without HTTP fakes: 15 interaction checks passed across admin, technician and customer. A leaked password was rejected while the original password remained usable; a generated clean password saved; old-password login failed and new-password login succeeded.
- 34 focused regression cases passed, including profile/password behavior, breach thresholds, request privacy, padding, TLS verification options and proof that unrelated HTTP requests do not inherit this option.
- Full regression suite: **195 tests passed, 1,252 assertions**.
- PHP syntax and git diff --check passed.

The live tests used newly created synthetic accounts and isolated SQLite/storage. No real user passwords or customer records were changed. This fixes the observed Windows trust problem for this service; general PHP HTTP clients retain their existing configuration. Future hosting environments still need a functioning certificate trust store and network access. The existing framework behavior during an unrelated future service outage was not redesigned.

Local evidence:

- .qa/password-trust-live-results.json
- .qa/password-live-browser-results.json
- .qa/password-trust-tests.txt
- .qa/password-trust-full-suite.txt

## Files and rollback

Modified: app/Providers/AppServiceProvider.php and docs/profile-settings-review.md.
Added: tests/Feature/PasswordBreachTransportTest.php and this report.

Original archive: .checkpoints/before-password-breach-trust-20260915.zip
Manifest: .checkpoints/password-breach-trust-20260915-manifest.json
Updated archive: .checkpoints/implemented-password-breach-trust-20260915.zip
Exact patch: .qa/password-breach-trust.diff

The original archive was verified before finishing. To revert only this change, first preserve any later edits, restore the two modified files from the archive and remove the two added files listed in the manifest. Earlier profile, camera and ID-upload changes remain in place. No database rollback is required. Restoring the old provider also restores the original certificate issue on this runtime.
