<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Prepared notifications with honest delivery states.
 *
 * Default channel is "manual": an officer copies the prepared message into an
 * approved private channel and then attests delivery. If SMTP delivery is
 * enabled, a send attempt is recorded as "accepted by mail server" — never as
 * "delivered", because acceptance is all SMTP can prove.
 */
class MessageService
{
    public function prepare(string $purpose, string $recipientName, ?string $email, ?string $phone, string $subject, string $body, ?object $related = null, ?int $createdBy = null): OutboundMessage
    {
        return OutboundMessage::create([
            'purpose' => $purpose,
            'related_type' => $related ? class_basename($related) : null,
            'related_id' => $related?->getKey(),
            'recipient_name' => $recipientName,
            'recipient_email' => $email,
            'recipient_phone' => $phone,
            'subject' => $subject,
            'body' => $body,
            'channel' => 'manual',
            'status' => 'pending',
            'created_by' => $createdBy ?? auth()->id(),
        ]);
    }

    public function emailEnabled(): bool
    {
        return Settings::bool('accounts.email_delivery_enabled') && config('mail.default') !== 'log' && config('mail.default') !== 'array';
    }

    /**
     * Attempt SMTP delivery. $secretBody may contain a one-time link; it is
     * sent but never stored.
     */
    public function attemptEmail(OutboundMessage $message, ?string $secretBody = null): bool
    {
        if (! $this->emailEnabled() || ! $message->recipient_email) {
            return false;
        }
        try {
            Mail::raw($secretBody ?? $message->body, function ($mail) use ($message) {
                $mail->to($message->recipient_email, $message->recipient_name)->subject($message->subject);
            });
            $message->update(['channel' => 'smtp', 'status' => 'accepted_by_mail_server', 'attempted_at' => now(), 'error' => null]);

            return true;
        } catch (Throwable $e) {
            Log::warning('Outbound email failed', ['message_id' => $message->id, 'error' => $e->getMessage()]);
            $message->update(['channel' => 'smtp', 'status' => 'failed', 'attempted_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 250)]);

            return false;
        }
    }

    public function attestManualDelivery(OutboundMessage $message, User $officer, string $note): void
    {
        if (in_array($message->status, ['delivered_manually', 'cancelled'], true)) {
            throw new DomainRuleException('This message has already been closed.');
        }
        $message->update(['status' => 'delivered_manually', 'attested_by' => $officer->id, 'attested_at' => now(), 'attestation_note' => $note]);
        Audit::record('message.delivery_attested', "Delivery attested for {$message->purpose} message to {$message->recipient_name}", $message, ['note' => $note]);
    }

    public function cancel(OutboundMessage $message, string $note): void
    {
        $message->update(['status' => 'cancelled', 'attested_by' => auth()->id(), 'attested_at' => now(), 'attestation_note' => $note]);
        Audit::record('message.cancelled', 'Prepared message cancelled', $message, ['note' => $note]);
    }
}
