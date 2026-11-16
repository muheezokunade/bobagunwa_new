<?php

namespace App\Support;

use App\Exceptions\DomainRuleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Free, self-hosted anti-automation for public forms: a hidden honeypot
 * field plus an encrypted render timestamp (too fast or stale submissions are
 * refused). Combined with per-address rate limits; no third-party service.
 */
final class FormGuard
{
    public const MIN_SECONDS = 3;

    public static function stamp(): string
    {
        return Crypt::encryptString((string) time());
    }

    public static function check(Request $request, string $form): void
    {
        $blocked = false;
        if (trim((string) $request->input('website')) !== '') {
            $blocked = true;
        } else {
            try {
                $elapsed = time() - (int) Crypt::decryptString((string) $request->input('form_stamp'));
                $blocked = $elapsed < self::MIN_SECONDS || $elapsed > 6 * 3600;
            } catch (Throwable) {
                $blocked = true;
            }
        }
        if ($blocked) {
            Audit::security('public_form.blocked', null, ['form' => $form]);
            throw new DomainRuleException('We could not accept this submission. Please wait a moment and try again; if the page has been open for many hours, reload it first.');
        }
    }
}
