<?php

namespace Tests\Feature\Membership;

use App\Models\ImportBatch;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MemberImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(int $rows, int $offset = 0, string $extra = ''): UploadedFile
    {
        $csv = "full_name,email,phone,date_of_birth,membership_number,joined_on,ijebu_town,city,occupation\n";
        for ($i = $offset + 1; $i <= $offset + $rows; $i++) {
            $csv .= sprintf("Synthetic Member %05d,import%05d@example.test,0807%07d,%s,,2014-02-01,Ijebu Ode,Lagos,Synthetic\n", $i, $i, $i, ['1980-03-01', '14/07/1981', '1982-12-31'][$i % 3]);
        }

        return UploadedFile::fake()->createWithContent("synthetic-{$rows}-{$offset}.csv", $csv.$extra);
    }

    public static function volumes(): array
    {
        return [[2], [86], [100], [500], [1000]];
    }

    #[DataProvider('volumes')]
    public function test_import_volumes_commit_atomically_without_accounts(int $rows): void
    {
        $officer = $this->officer('secretariat');
        $start = microtime(true);
        $this->actingAs($officer)->post(route('admin.imports.members.preview'), ['file' => $this->csv($rows)])->assertRedirect();
        $batch = ImportBatch::latest('id')->first();
        $this->assertSame($rows, $batch->valid_count);
        $this->actingAs($officer)->get(route('admin.imports.members.show', $batch))->assertOk()->assertSee('Synthetic Member 00001');
        $this->actingAs($officer)->post(route('admin.imports.members.commit', $batch), ['confirm' => '1'])->assertSessionHasNoErrors();
        $elapsed = microtime(true) - $start;

        $this->assertSame($rows, Member::where('import_batch_id', $batch->id)->count());
        $this->assertSame(0, User::whereNotNull('member_id')->count(), 'Imports must never create login accounts');
        $this->assertSame($rows, Member::distinct()->count('membership_number'));
        fwrite(STDERR, sprintf("\n[evidence] member import %d rows: preview+commit %.2fs, peak memory %.1f MB\n", $rows, $elapsed, memory_get_peak_usage(true) / 1048576));

        // Idempotent replay and duplicate file protection.
        $this->actingAs($officer)->post(route('admin.imports.members.commit', $batch), ['confirm' => '1'])->assertSessionHas('status', fn ($s) => str_contains($s, 'already completed'));
        $this->actingAs($officer)->post(route('admin.imports.members.preview'), ['file' => $this->csv($rows)])->assertSessionHasErrors('domain');
        $this->assertSame($rows, Member::count());
    }

    public function test_invalid_and_duplicate_rows_are_reported_and_block_commit(): void
    {
        $officer = $this->officer('secretariat');
        $existing = $this->member(['full_name' => 'Existing Synthetic Person', 'email' => 'existing@example.test']);
        $extra = "A,bad-email,123,31/02/1981,,,,,\n"
            ."Duplicate In File,dup@example.test,,,,,,,\n"
            ."Duplicate In File Two,dup@example.test,,,,,,,\n"
            ."Existing Synthetic Person,,,,,,,,\n"
            ."Somebody Else,EXISTING@example.test,,,,,,,\n"
            ."Formula Person,=cmd|' /C calc'!A0@example.test,,,,,,,\n";
        $this->actingAs($officer)->post(route('admin.imports.members.preview'), ['file' => $this->csv(3, 0, $extra)]);
        $batch = ImportBatch::latest('id')->first();
        $this->assertSame(9, $batch->row_count);
        $this->assertSame(4, $batch->valid_count, 'The first of two rows sharing an email is valid; the second is the duplicate');
        $messages = $batch->rows()->get()->pluck('messages')->flatten()->implode(' | ');
        foreach (['Full name is required', 'Email address is not valid', 'Phone number is not valid', 'not a valid date', 'Same email as row', 'Name matches existing member', 'Email already belongs to member'] as $expected) {
            $this->assertStringContainsString($expected, $messages);
        }
        $this->actingAs($officer)->get(route('admin.imports.members.show', $batch))->assertOk();
        $this->actingAs($officer)->post(route('admin.imports.members.commit', $batch), ['confirm' => '1'])->assertSessionHasErrors('domain');
        $this->assertSame(1, Member::count(), 'Existing records unchanged and nothing partially imported');
        $this->actingAs($officer)->post(route('admin.imports.members.cancel', $batch))->assertRedirect();
        $this->assertSame('cancelled', $batch->fresh()->status);
    }

    public function test_provided_legacy_numbers_are_kept_and_sequence_skips_them(): void
    {
        $officer = $this->officer('secretariat');
        $csv = "full_name,membership_number\nLegacy Synthetic One,BOB-000001\nLegacy Synthetic Two,\n";
        $this->actingAs($officer)->post(route('admin.imports.members.preview'), ['file' => UploadedFile::fake()->createWithContent('legacy.csv', $csv)]);
        $batch = ImportBatch::latest('id')->first();
        $this->actingAs($officer)->post(route('admin.imports.members.commit', $batch), ['confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['BOB-000001', 'BOB-000002'], Member::pluck('membership_number')->all());
    }

    public function test_import_requires_permission_and_production_approval(): void
    {
        $this->post(route('admin.imports.members.preview'))->assertRedirect(route('login'));
        $this->actingAs($this->officer('membership_officer'))->post(route('admin.imports.members.preview'), ['file' => $this->csv(2)])->assertForbidden();
        $this->actingAs($this->officer('treasurer'))->get(route('admin.imports.members.index'))->assertForbidden();

        $officer = $this->officer('secretariat');
        $this->actingAs($officer)->post(route('admin.imports.members.preview'), ['file' => $this->csv(2)]);
        $batch = ImportBatch::latest('id')->first();
        $this->app['env'] = 'production';
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class); // CSRF/origin checks are only auto-skipped in the testing environment
        $this->actingAs($officer)->post(route('admin.imports.members.commit', $batch), ['confirm' => '1'])->assertSessionHasErrors('domain');
        $this->assertSame(0, Member::count());
        \App\Support\Settings::set('approval.member_import', ['approved' => true, 'reference' => 'Synthetic minute 1'], $officer->id);
        \App\Support\Settings::set('approval.membership_number_format', ['approved' => true, 'reference' => 'Synthetic minute 2'], $officer->id);
        $this->actingAs($officer)->post(route('admin.imports.members.commit', $batch), ['confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(2, Member::count());
    }
}
