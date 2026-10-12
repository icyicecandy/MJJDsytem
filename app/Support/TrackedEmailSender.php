<?php

namespace App\Support;

use App\Models\EmailDeliveryLog;
use App\Models\SystemLog;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TrackedEmailSender
{
    public static function attempt(
        ?string $recipient,
        string $notificationType,
        Closure $send,
        array $context = [],
        ?int $userId = null,
        bool $rethrow = false
    ): bool {
        if (blank($recipient)) {
            return false;
        }

        $delivery = null;

        try {
            if (Schema::hasTable('email_delivery_logs')) {
                $delivery = EmailDeliveryLog::create([
                    'user_id' => $userId,
                    'notification_type' => $notificationType,
                    'recipient' => $recipient,
                    'status' => 'pending',
                    'attempts' => 1,
                    'retry_status' => 'not_needed',
                    'last_attempted_at' => now(),
                    'context' => $context,
                ]);
            }

            $send();

            $delivery?->update([
                'status' => 'sent',
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ]);

            self::recordAuditEvent(
                'Email Sent',
                "{$notificationType} email sent to {$recipient}."
            );

            return true;
        } catch (Throwable $exception) {
            $delivery?->update([
                'status' => 'failed',
                'retry_status' => 'available',
                'failed_at' => now(),
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            self::recordAuditEvent(
                'Email Failed',
                "{$notificationType} email to {$recipient} could not be sent."
            );

            Log::warning($notificationType.' email failed to send.', $context + [
                'recipient' => $recipient,
                'error' => $exception->getMessage(),
            ]);

            if ($rethrow) {
                throw $exception;
            }

            return false;
        }
    }

    private static function recordAuditEvent(string $action, string $description): void
    {
        try {
            if (! Schema::hasTable('system_logs')) {
                return;
            }

            $actor = auth()->user();

            SystemLog::create([
                'user_id' => $actor?->id,
                'user_role' => $actor?->role ?? 'system',
                'action' => $action,
                'description' => $description,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Email audit event could not be recorded.', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
