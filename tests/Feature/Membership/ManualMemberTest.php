<?php

namespace Tests\Feature\Membership;

use App\Models\ImportBatch;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ManualMemberTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $overrides = []): array
    {
        return $overrides + ['full_name' => 'Ọlá Synthetic Manual', 'email' => 'manual@example.test', 'phone' => '08055550001', 'date_of_birth' => '1981-02-02', 'reason' => 'Verified against the synthetic register', 'idempotency_key' => $this->key()];
    }

    public function test_officer_adds_a_member_without_creating_an_account_and_replay_is_safe(): void
    {
        $officer = $this->officer('membership_officer');
        $data = $this->form();
        $this->actingAs($officer)->get(route('admin.members.create'))->assertOk();
        $this->actingAs($officer)->post(route('admin.members.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($officer)->post(route('admin.members.store'), $data)->assertSessionHas('status', fn ($s) => str_contains($s, 'already added'));
        $member = Member::sole();
        $this->assertSame(['manual', 'active'], [$member->origin, $member->status]);
        $this->assertMatchesRegularExpression('/^BOB-\d{6}$/', $member->membership_number);
        $this->assertSame(0, User::whereNotNull('member_id')->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'member.created_manually', 'subject_id' => $member->id]);
    }

    public function test_email_and_phone_duplicates_are_refused(): void
    {
        $officer = $this->officer('membership_officer');
        $this->member(['email' => 'taken@example.test', 'phone' => '08055550009', 'phone_normalized' => '2348055550009']);
        $this->actingAs($officer)->post(route('admin.members.store'), $this->form(['email' => 'TAKEN@example.test']))->assertSessionHasErrors('domain');
        $this->actingAs($officer)->post(route('admin.members.store'), $this->form(['email' => null, 'phone' => '+234 805 555 0009']))->assertSessionHasErrors('domain');
        $this->assertSame(1, Member::count());
    }

    public function test_same_name_requires_confirmation_and_note_which_are_recorded(): void
    {
        $officer = $this->officer('membership_officer');
        $existing = $this->member(['full_name' => 'Adebayo Synthetic Same']);
        $this->actingAs($officer)->post(route('admin.members.store'), $this->form(['full_name' => 'ADEBAYO  synthetic same']))->assertSessionHasErrors('domain');
        $this->actingAs($officer)->post(route('admin.members.store'), $this->form(['full_name' => 'Adebayo Synthetic Same', 'confirm_distinct_person' => '1', 'distinguishing_note' => 'short']))->assertSessionHasErrors('domain');
        $this->actingAs($officer)->post(route('admin.members.store'), $this->form(['full_name' => 'Adebayo Synthetic Same', 'confirm_distinct_person' => '1', 'distinguishing_note' => 'Different date of birth and town, confirmed by phone']))->assertSessionHasNoErrors();
        $new = Member::where('id', '!=', $existing->id)->sole();
        $this->assertStringContainsString($existing->membership_number, $new->admin_notes);
        $this->assertDatabaseHas('audit_events', ['action' => 'member.created_manually', 'subject_id' => $new->id]);
    }

    public function test_permission_and_validation(): void
    {
        foreach (['treasurer', 'secretariat', 'system_admin', 'executive'] as $role) {
            $this->actingAs($this->officer($role))->post(route('admin.members.store'), $this->form())->assertForbidden();
        }
        $this->actingAs($this->officer('membership_officer'))->post(route('admin.members.store'), ['full_name' => 'x', 'email' => 'bad', 'idempotency_key' => $this->key()])->assertSessionHasErrors(['full_name', 'email', 'reason']);
        $this->assertSame(0, Member::count());
    }

    public function test_import_same_name_rows_need_explicit_confirmation(): void
    {
        $officer = $this->officer('secretariat');
        $this->member(['full_name' => 'Shared Name Synthetic']);
        $csv = "full_name,email\nShared Name Synthetic,other1@example.test\nUnique Person Synthetic,other2@example.test\n";
        $this->actingAs($officer)->post(route('admin.imports.members.preview'), ['file' => UploadedFile::fake()->createWithContent('names.csv', $csv)]);
        $batch = ImportBatch::latest('id')->sole();
        $this->assertSame([1, 1, 0], [$batch->valid_count, $batch->name_match_count, $batch->duplicate_count]);
        $this->actingAs($officer)->get(route('admin.imports.members.show', $batch))->assertSee('Name match');
        $this->actingAs($officer)->post(route('admin.imports.members.commit', $batch), ['confirm' => '1'])->assertSessionHasErrors('domain');
        $this->assertSame(1, Member::count());
        $this->actingAs($officer)->post(route('admin.imports.members.commit', $batch), ['confirm' => '1', 'confirm_same_names' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(3, Member::count());
    }
}
