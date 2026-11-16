<?php

namespace App\Support;

/**
 * The authoritative permission catalogue and default role templates.
 *
 * Roles are assignment templates; every protected route and service checks a
 * named permission. Final role assignments require association approval
 * (docs/23-client-decisions.md, D-005, D-007, D-008).
 */
final class PermissionCatalog
{
    /** @return array<string, array{group: string, description: string}> */
    public static function permissions(): array
    {
        return [
            'applications.review' => ['group' => 'Membership', 'description' => 'Review, request information on, approve or reject membership applications'],
            'members.read' => ['group' => 'Membership', 'description' => 'View the membership register'],
            'members.manage' => ['group' => 'Membership', 'description' => 'Correct permitted member fields; suspend and reactivate members'],
            'members.import' => ['group' => 'Membership', 'description' => 'Preview and commit existing-member imports'],
            'members.export' => ['group' => 'Membership', 'description' => 'Export register information'],
            'accounts.issue' => ['group' => 'Membership', 'description' => 'Issue member activation and password reset links'],

            'finance.read' => ['group' => 'Finance', 'description' => 'View assessments, payments, balances and reports'],
            'finance.categories.manage' => ['group' => 'Finance', 'description' => 'Manage dues and levy categories'],
            'finance.assessments.manage' => ['group' => 'Finance', 'description' => 'Create, preview and post assessments'],
            'finance.payments.record' => ['group' => 'Finance', 'description' => 'Record received payments for verification'],
            'finance.payments.verify' => ['group' => 'Finance', 'description' => 'Verify or reject recorded payments'],
            'finance.allocate' => ['group' => 'Finance', 'description' => 'Allocate verified payments to obligations'],
            'finance.adjust' => ['group' => 'Finance', 'description' => 'Record obligation waivers, corrections and cancellations'],
            'finance.reverse' => ['group' => 'Finance', 'description' => 'Reverse allocations and verified payments'],
            'finance.import' => ['group' => 'Finance', 'description' => 'Import approved historical records and opening balances'],
            'finance.reports.export' => ['group' => 'Finance', 'description' => 'Export financial reports and statements'],

            'content.manage' => ['group' => 'Content', 'description' => 'Create and edit pages, news, notices, activities, leadership and media'],
            'content.publish' => ['group' => 'Content', 'description' => 'Publish, unpublish and archive content'],
            'events.manage' => ['group' => 'Content', 'description' => 'Manage events and view attendance responses'],
            'enquiries.manage' => ['group' => 'Content', 'description' => 'Review and resolve contact enquiries'],

            'access.manage' => ['group' => 'Administration', 'description' => 'Manage officer accounts, role assignments and account recovery'],
            'audit.read' => ['group' => 'Administration', 'description' => 'Read audit and security history'],
            'integrity.alerts' => ['group' => 'Administration', 'description' => 'See, acknowledge and resolve integrity alerts (check names and counts only; no financial records)'],
            'settings.manage' => ['group' => 'Administration', 'description' => 'Manage organisation settings and launch approvals'],
            'backups.manage' => ['group' => 'Administration', 'description' => 'Create and review encrypted backups'],
        ];
    }

    /** @return array<string, array{name: string, description: string, is_staff: bool, permissions: list<string>}> */
    public static function roles(): array
    {
        return [
            'member' => [
                'name' => 'Member',
                'description' => 'Approved member with portal access to their own records',
                'is_staff' => false,
                'permissions' => [],
            ],
            'system_admin' => [
                'name' => 'System Administrator',
                'description' => 'Technical administration. Has no membership-approval or treasury powers.',
                'is_staff' => true,
                'permissions' => ['access.manage', 'audit.read', 'settings.manage', 'backups.manage', 'integrity.alerts'],
            ],
            'membership_officer' => [
                'name' => 'Membership Officer',
                'description' => 'Reviews applications and maintains the register. No financial access.',
                'is_staff' => true,
                'permissions' => ['applications.review', 'members.read', 'members.manage', 'accounts.issue'],
            ],
            'secretariat' => [
                'name' => 'Secretariat Officer',
                'description' => 'Register imports, publishing, events and enquiries.',
                'is_staff' => true,
                'permissions' => ['members.read', 'members.import', 'members.export', 'content.manage', 'content.publish', 'events.manage', 'enquiries.manage'],
            ],
            'treasurer' => [
                'name' => 'Treasurer',
                'description' => 'Assessments, payment recording, allocation, adjustments and reports. Cannot verify own entries or reverse.',
                'is_staff' => true,
                'permissions' => ['members.read', 'finance.read', 'finance.categories.manage', 'finance.assessments.manage', 'finance.payments.record', 'finance.allocate', 'finance.adjust', 'finance.import', 'finance.reports.export', 'integrity.alerts'],
            ],
            'payment_verifier' => [
                'name' => 'Payment Verifier',
                'description' => 'Independently verifies or rejects recorded payments and authorises reversals.',
                'is_staff' => true,
                'permissions' => ['members.read', 'finance.read', 'finance.payments.verify', 'finance.reverse', 'integrity.alerts'],
            ],
            'executive' => [
                'name' => 'Executive (read-only oversight)',
                'description' => 'Read-only register, finance and audit oversight.',
                'is_staff' => true,
                'permissions' => ['members.read', 'finance.read', 'finance.reports.export', 'audit.read', 'integrity.alerts'],
            ],
        ];
    }
}
