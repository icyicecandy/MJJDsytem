<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Cookie;

class StaffTwoFactorService
{
    public const TRUST_COOKIE = 'mjjd_staff_trusted_device';

    public const TRUST_DAYS = 30;

    public const PENDING_TTL_MINUTES = 10;

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly CookieJar $cookies,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function provisioningUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name', 'MJJD Telecom'),
            $user->email,
            $secret,
        );
    }

    public function qrSvg(User $user, string $secret): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(240, 1),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($this->provisioningUri($user, $secret));
    }

    public function verifyTotp(User $user, string $code, ?string $secret = null): bool
    {
        $normalized = preg_replace('/\D+/', '', $code) ?? '';
        $secret ??= $user->two_factor_secret;

        return filled($secret)
            && strlen($normalized) === 6
            && $this->google2fa->verifyKey($secret, $normalized, 1);
    }

    /**
     * @return array{plain: array<int, string>, hashed: array<int, string>}
     */
    public function generateRecoveryCodes(): array
    {
        $plain = collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();

        return [
            'plain' => $plain,
            'hashed' => array_map(
                fn (string $code) => Hash::make($this->normalizeRecoveryCode($code)),
                $plain,
            ),
        ];
    }

    public function consumeRecoveryCode(User $user, string $candidate): bool
    {
        $normalized = $this->normalizeRecoveryCode($candidate);

        if ($normalized === '') {
            return false;
        }

        $codes = array_values($user->two_factor_recovery_codes ?? []);

        foreach ($codes as $index => $hash) {
            if (! is_string($hash) || ! Hash::check($normalized, $hash)) {
                continue;
            }

            unset($codes[$index]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

            return true;
        }

        return false;
    }

    /**
     * @return array{device: TrustedDevice, cookie: Cookie}
     */
    public function trustBrowser(User $user, Request $request): array
    {
        $plainToken = Str::random(80);
        $device = $user->trustedDevices()->create([
            'token_hash' => hash('sha256', $plainToken),
            'name' => $this->deviceName((string) $request->userAgent()),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(self::TRUST_DAYS),
        ]);

        return [
            'device' => $device,
            'cookie' => $this->cookies->make(
                self::TRUST_COOKIE,
                $device->id.'|'.$plainToken,
                self::TRUST_DAYS * 24 * 60,
                '/',
                null,
                app()->environment('production') || $request->isSecure(),
                true,
                false,
                'lax',
            ),
        ];
    }

    public function trustedDevice(User $user, Request $request): ?TrustedDevice
    {
        $value = (string) $request->cookie(self::TRUST_COOKIE, '');

        if (! str_contains($value, '|')) {
            return null;
        }

        [$id, $plainToken] = explode('|', $value, 2);

        if (! ctype_digit($id) || strlen($plainToken) < 40) {
            return null;
        }

        $device = $user->trustedDevices()->active()->find((int) $id);

        if (! $device || ! hash_equals($device->token_hash, hash('sha256', $plainToken))) {
            return null;
        }

        $device->forceFill([
            'last_used_at' => now(),
            'ip_address' => $request->ip(),
        ])->saveQuietly();

        return $device;
    }

    public function currentTrustedDeviceId(Request $request): ?int
    {
        $value = (string) $request->cookie(self::TRUST_COOKIE, '');
        $id = str_contains($value, '|') ? explode('|', $value, 2)[0] : null;

        return is_string($id) && ctype_digit($id) ? (int) $id : null;
    }

    public function forgetTrustedDeviceCookie(): Cookie
    {
        return $this->cookies->forget(self::TRUST_COOKIE);
    }

    public function revokeAllTrustedDevices(User $user): void
    {
        $user->trustedDevices()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', trim($code)) ?? '');
    }

    private function deviceName(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains($userAgent, 'Chrome/') => 'Google Chrome',
            str_contains($userAgent, 'Firefox/') => 'Mozilla Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Web browser',
        };

        $platform = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iPhone or iPad',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown device',
        };

        return $browser.' on '.$platform;
    }
}
