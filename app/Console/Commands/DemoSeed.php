<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Event;
use App\Models\FeeCategory;
use App\Models\Page;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\Finance\AssessmentService;
use App\Services\Finance\PaymentService;
use App\Services\MemberImportService;
use App\Support\Idempotency;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

/**
 * Local demonstration data. SYNTHETIC ONLY — every person, amount and record
 * created here is fictitious. Refuses to run in production or staging.
 */
class DemoSeed extends Command
{
    protected $signature = 'boba:demo:seed {--members=40 : Number of synthetic imported members}';

    protected $description = 'Populate a LOCAL database with clearly synthetic demonstration data';

    public const PASSWORD = 'REMOVED-DEMO-PASSWORD';

    public function handle(ApplicationService $applications, MemberImportService $imports, AssessmentService $assessments, PaymentService $payments): int
    {
        if (app()->environment(['production', 'staging'])) {
            $this->error('Refused: demonstration data may only be created in local or testing environments.');

            return self::FAILURE;
        }

        $officers = [];
        foreach ([
            'admin' => ['System Administrator (synthetic)', ['system_admin']],
            'membership' => ['Membership Officer (synthetic)', ['membership_officer']],
            'secretariat' => ['Secretariat Officer (synthetic)', ['secretariat']],
            'treasurer' => ['Treasurer (synthetic)', ['treasurer']],
            'verifier' => ['Payment Verifier (synthetic)', ['payment_verifier']],
            'executive' => ['Executive (synthetic)', ['executive']],
        ] as $key => [$name, $roles]) {
            $user = User::firstOrCreate(['email' => "{$key}@example.test"], ['name' => $name, 'status' => 'active', 'activated_at' => now()]);
            $user->forceFill(['password' => self::PASSWORD, 'status' => 'active'])->save();
            $user->roles()->syncWithoutDetaching(Role::whereIn('key', $roles)->pluck('id'));
            $officers[$key] = $user->fresh();
        }

        // Existing members through the real import workflow.
        $csv = "full_name,email,phone,date_of_birth,joined_on,ijebu_town,city,occupation\n";
        $first = ['Adebayo', 'Babatunde', 'Olumide', 'Kayode', 'Segun', 'Femi', 'Tunde', 'Wale', 'Gbenga', 'Dapo'];
        $last = ['Example', 'Sample', 'Specimen', 'Testcase', 'Placeholder'];
        for ($i = 1; $i <= (int) $this->option('members'); $i++) {
            $csv .= sprintf("%s %s %d,member%03d@example.test,0803%07d,198%d-%02d-%02d,2015-01-%02d,Ijebu Ode,Lagos,Synthetic occupation\n",
                $first[$i % 10], $last[$i % 5], $i, $i, $i, $i % 3, ($i % 12) + 1, ($i % 27) + 1, ($i % 27) + 1);
        }
        $path = tempnam(sys_get_temp_dir(), 'demo').'.csv';
        file_put_contents($path, $csv);
        Auth::login($officers['secretariat']);
        $batch = $imports->preview(new UploadedFile($path, 'synthetic-members.csv', 'text/csv', null, true), $officers['secretariat']);
        $imports->commit($batch, $officers['secretariat']);
        @unlink($path);

        // One application approved through the real workflow, one awaiting review.
        Auth::login($officers['membership']);
        [$application] = $applications->submit(['first_name' => 'Demo', 'last_name' => 'Applicant', 'other_names' => null, 'email' => 'applicant@example.test', 'phone' => '08029990001', 'date_of_birth' => '1981-05-20', 'ijebu_town' => 'Ijebu Igbo', 'city' => 'Ibadan', 'occupation' => 'Synthetic teacher', 'reason' => 'Synthetic demonstration application for the local environment.']);
        $applications->transition($application, 'under_review', $officers['membership'], 'Synthetic review started.', null);
        [$member] = $applications->approve($application->fresh(), $officers['membership'], 'Synthetic approval for demonstration.', null);
        $memberUser = $member->user;
        $memberUser->forceFill(['password' => self::PASSWORD, 'status' => 'active', 'activated_at' => now()])->save();
        $applications->submit(['first_name' => 'Pending', 'last_name' => 'Applicant', 'other_names' => null, 'email' => 'pending@example.test', 'phone' => '08029990002', 'date_of_birth' => '1979-02-11', 'ijebu_town' => 'Ago Iwoye', 'city' => 'Abuja', 'occupation' => 'Synthetic engineer', 'reason' => 'Synthetic application with a birth date outside the range, to show the flagged review.']);

        // Finance: a synthetic assessment, payments, verification and allocation.
        Auth::login($officers['treasurer']);
        $category = FeeCategory::firstOrCreate(['code' => 'DEMO-DUES'], ['name' => 'Annual dues (synthetic)', 'kind' => 'dues', 'description' => 'Synthetic category for demonstration', 'is_active' => true, 'created_by' => $officers['treasurer']->id]);
        $assessment = $assessments->createDraft(['fee_category_id' => $category->id, 'title' => 'Synthetic annual dues', 'amount' => '50000', 'effective_on' => now()->subMonths(2)->toDateString(), 'due_on' => now()->addMonth()->toDateString(), 'period_label' => 'DEMO', 'target_mode' => 'all_active'], $officers['treasurer']);
        $count = $assessments->preview($assessment)['targets']->count();
        $assessments->post($assessment, $officers['treasurer'], Idempotency::newKey(), $count);
        [$payment] = $payments->record(['member_id' => $member->id, 'amount' => '20000', 'method' => 'bank_transfer', 'received_on' => now()->subMonth()->toDateString(), 'external_reference' => 'DEMO-TRF-0001'], $officers['treasurer'], Idempotency::newKey());
        $payments->verify($payment, $officers['verifier'], 'Synthetic verification');
        $obligation = $member->obligations()->first();
        $payments->allocate($payment->fresh(), [$obligation->id => 2_000_000], $officers['treasurer'], Idempotency::newKey());
        $payments->record(['member_id' => $member->id, 'amount' => '5000', 'method' => 'cash', 'received_on' => now()->toDateString(), 'external_reference' => 'DEMO-CASH-0002'], $officers['treasurer'], Idempotency::newKey());

        // Content: baseline drafts, published for the local demonstration only.
        Artisan::call('boba:content:baseline');
        foreach (Page::with('latestRevision')->get() as $page) {
            if ($page->latestRevision && in_array($page->key, ['about', 'privacy', 'terms'], true)) {
                $page->update(['published_revision_id' => $page->latestRevision->id, 'published_at' => now(), 'published_by' => $officers['secretariat']->id]);
            }
        }
        Post::firstOrCreate(['slug' => 'synthetic-news-item'], ['type' => 'news', 'audience' => 'public', 'title' => 'Synthetic news item for the demonstration site', 'excerpt' => 'This item exists only in the local demonstration database.', 'body' => 'This is **synthetic** demonstration content. Real news is written and published by the secretariat.', 'status' => 'published', 'published_at' => now()->subDays(3), 'created_by' => $officers['secretariat']->id]);
        Post::firstOrCreate(['slug' => 'synthetic-member-notice'], ['type' => 'announcement', 'audience' => 'members', 'title' => 'Synthetic notice to members', 'excerpt' => 'Demonstration only.', 'body' => 'This notice demonstrates member-only announcements.', 'status' => 'published', 'published_at' => now()->subDay(), 'created_by' => $officers['secretariat']->id]);
        Event::firstOrCreate(['slug' => 'synthetic-general-meeting'], ['title' => 'Synthetic general meeting', 'summary' => 'A demonstration event.', 'body' => 'Synthetic event for the local demonstration.', 'location' => 'Demonstration venue', 'starts_at' => now()->addWeeks(3)->setTime(15, 0), 'ends_at' => now()->addWeeks(3)->setTime(18, 0), 'audience' => 'public', 'status' => 'published', 'rsvp_enabled' => true, 'published_at' => now(), 'created_by' => $officers['secretariat']->id]);
        Activity::firstOrCreate(['slug' => 'synthetic-activity'], ['title' => 'Synthetic activity', 'category' => 'Demonstration', 'summary' => 'Activities are published by the secretariat once approved.', 'status' => 'published']);
        Auth::logout();

        $this->info('Synthetic demonstration data created.');
        $this->table(['Role', 'Email', 'Password'], collect($officers)->map(fn ($u, $k) => [$k, $u->email, self::PASSWORD])->push(['member', $memberUser->email, self::PASSWORD])->values()->all());

        return self::SUCCESS;
    }
}
