<?php

namespace App\Support;

use App\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Typed organisation settings stored in the `settings` table.
 *
 * Settings never hold secrets. Keys prefixed `approval.` are launch gates: in
 * the production environment the guarded action is refused until an
 * authorised administrator records the association's approval.
 */
final class Settings
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /** @return array<string, array{type: string, group: string, label: string, help?: string, default: mixed}> */
    public static function definitions(): array
    {
        return [
            'org.name' => ['type' => 'string', 'group' => 'Organisation', 'label' => 'Official name', 'default' => 'Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu'],
            'org.short_name' => ['type' => 'string', 'group' => 'Organisation', 'label' => 'Short name', 'default' => 'Bobagunwa'],
            'org.motto' => ['type' => 'string', 'group' => 'Organisation', 'label' => 'Motto (as shown on the crest)', 'default' => 'Fun Itesiwaju'],
            'contact.publish_details' => ['type' => 'bool', 'group' => 'Contact', 'label' => 'Publish the contact details below on the public website', 'help' => 'Leave off until the association confirms the details (decision D-014).', 'default' => false],
            'contact.email' => ['type' => 'email', 'group' => 'Contact', 'label' => 'Secretariat email', 'default' => null],
            'contact.phone' => ['type' => 'string', 'group' => 'Contact', 'label' => 'Secretariat telephone', 'default' => null],
            'contact.address' => ['type' => 'string', 'group' => 'Contact', 'label' => 'Secretariat address', 'default' => null],

            'membership.applications_open' => ['type' => 'bool', 'group' => 'Membership', 'label' => 'Accept public membership applications', 'default' => true],
            'membership.number_prefix' => ['type' => 'string', 'group' => 'Membership', 'label' => 'Membership number prefix', 'help' => 'Applies to numbers issued after a change. Issued numbers never change.', 'default' => 'BOB-'],
            'membership.number_width' => ['type' => 'int', 'group' => 'Membership', 'label' => 'Membership number digits', 'default' => 6],

            'finance.require_distinct_verifier' => ['type' => 'bool', 'group' => 'Finance', 'label' => 'A different officer must verify each recorded payment', 'help' => 'Safe default pending decision D-007. Applies to payments recorded after a change.', 'default' => true],

            'accounts.email_delivery_enabled' => ['type' => 'bool', 'group' => 'Accounts', 'label' => 'Also send account links by email through the configured mail server', 'help' => 'Requires working SMTP settings. Administrator-assisted delivery remains available.', 'default' => false],
            'accounts.self_service_reset' => ['type' => 'bool', 'group' => 'Accounts', 'label' => 'Email password-reset links automatically to verified member email addresses', 'help' => 'Off by default: recovery requests go to the officer queue.', 'default' => false],

            'alerts.email_enabled' => ['type' => 'bool', 'group' => 'Alerts', 'label' => 'Also email integrity alerts to officers with the alerts permission', 'help' => 'Only takes effect after the mail channel has been verified below. Emails contain no financial details.', 'default' => false],
            'security.require_two_factor' => ['type' => 'bool', 'group' => 'Security', 'label' => 'Require two-factor sign-in for privileged officers', 'help' => 'Off until the association approves it and every privileged officer has enrolled and stored recovery codes (see docs/32-two-factor-authentication.md).', 'default' => false],
            'alerts.email_verified' => ['type' => 'internal', 'group' => 'Alerts', 'label' => 'Alert email channel verification', 'default' => null],

            'approval.membership_number_format' => ['type' => 'approval', 'group' => 'Launch approvals', 'label' => 'Membership-number format approved by the association', 'default' => null],
            'approval.member_import' => ['type' => 'approval', 'group' => 'Launch approvals', 'label' => 'Verified existing-member list approved for import', 'default' => null],
            'approval.historical_finance' => ['type' => 'approval', 'group' => 'Launch approvals', 'label' => 'Historical financial records approved for import', 'default' => null],
        ];
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return array_map(fn ($d) => $d['default'], self::definitions());
    }

    public static function get(string $key): mixed
    {
        self::$cache ??= DB::table('settings')->pluck('value', 'key')->map(fn ($v) => json_decode($v, true))->all();

        return array_key_exists($key, self::$cache) ? self::$cache[$key] : (self::definitions()[$key]['default'] ?? null);
    }

    public static function bool(string $key): bool
    {
        return (bool) self::get($key);
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function set(string $key, mixed $value, ?int $userId): void
    {
        if (! array_key_exists($key, self::definitions())) {
            throw new DomainRuleException("Unknown setting {$key}.");
        }
        DB::table('settings')->upsert(
            [['key' => $key, 'value' => json_encode($value), 'updated_by' => $userId, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]],
            ['key'],
            ['value' => json_encode($value), 'updated_by' => $userId, 'updated_at' => now(), 'version' => DB::raw('version + 1')]
        );
        self::flush();
    }

    /**
     * Launch gate. Outside production the gate is open so synthetic acceptance
     * tests can run; in production the action is refused until approved.
     */
    public static function requireApproval(string $key, string $action): void
    {
        if (! app()->environment('production')) {
            return;
        }
        $approval = self::get($key);
        if (! is_array($approval) || empty($approval['approved'])) {
            throw new DomainRuleException("{$action} is disabled until the association records its approval in Settings → Launch approvals.");
        }
    }
}
