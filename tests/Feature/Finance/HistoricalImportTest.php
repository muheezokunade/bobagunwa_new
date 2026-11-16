<?php

namespace Tests\Feature\Finance;

use App\Models\Assessment;
use App\Models\ImportBatch;
use App\Models\Payment;
use App\Services\Finance\LedgerQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class HistoricalImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_transactions_import_with_two_officers_and_replay_protection(): void
    {
        $m = $this->member();
        $category = $this->category();
        $uploader = $this->officer('treasurer');
        $confirmer = $this->officer('treasurer');
        $csv = "record_type,membership_number,date,amount_ngn,category_code,external_reference,description\n"
            ."obligation,{$m->membership_number},2025-01-31,10000.00,{$category->code},MINUTE-2025-01,Annual dues 2025 (synthetic)\n"
            ."payment,{$m->membership_number},2025-02-15,4000,,BANK-HIST-001,Transfer (synthetic)\n";

        $this->actingAs($uploader)->post(route('admin.finance.imports.preview'), ['kind' => 'finance_transactions', 'file' => UploadedFile::fake()->createWithContent('hist.csv', $csv), 'source_approval_reference' => 'x'])->assertSessionHasErrors('domain');
        $this->actingAs($uploader)->post(route('admin.finance.imports.preview'), ['kind' => 'finance_transactions', 'file' => UploadedFile::fake()->createWithContent('hist.csv', $csv), 'source_approval_reference' => 'Signed schedule SYN-2025'])->assertRedirect();
        $batch = ImportBatch::where('kind', 'finance_transactions')->latest('id')->sole();
        $this->assertSame([2, 2], [$batch->row_count, $batch->valid_count]);
        $this->assertSame(1_000_000, $batch->summary['obligations_kobo']);
        $this->assertSame(400_000, $batch->summary['payments_kobo']);

        $this->actingAs($uploader)->post(route('admin.finance.imports.commit', $batch), ['confirm' => '1'])->assertSessionHasErrors('domain');
        $this->actingAs($this->officer('membership_officer'))->post(route('admin.finance.imports.commit', $batch), ['confirm' => '1'])->assertForbidden();
        $this->actingAs($confirmer)->post(route('admin.finance.imports.commit', $batch), ['confirm' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($confirmer)->post(route('admin.finance.imports.commit', $batch), ['confirm' => '1'])->assertSessionHas('status', fn ($s) => str_contains($s, 'already completed'));

        $payment = Payment::where('external_reference', 'BANK-HIST-001')->sole();
        $this->assertSame(['verified', 'historical'], [$payment->status, $payment->origin]);
        $this->assertSame('historical', Assessment::where('import_batch_id', $batch->id)->value('origin'));
        $summary = app(LedgerQuery::class)->memberSummary($m->id);
        $this->assertSame([1_000_000, 400_000, 1_000_000, 400_000], [$summary['effective_obligations'], $summary['verified_payments'], $summary['outstanding'], $summary['unallocated_credit']]);
        $this->assertEmpty(array_filter(app(LedgerQuery::class)->exceptions()));

        $this->actingAs($uploader)->post(route('admin.finance.imports.preview'), ['kind' => 'finance_transactions', 'file' => UploadedFile::fake()->createWithContent('hist-again.csv', $csv), 'source_approval_reference' => 'Signed schedule SYN-2025'])->assertSessionHasErrors('domain');
    }

    public function test_invalid_rows_block_commit_and_credit_opening_balances_are_refused(): void
    {
        $m = $this->member();
        $category = $this->category();
        $treasurer = $this->officer('treasurer');
        $bad = "record_type,membership_number,date,amount_ngn,category_code,external_reference,description\n"
            ."refund,{$m->membership_number},2025-01-01,100,,,\n"
            ."payment,NOT-A-MEMBER,2025-01-01,100,,REF-1,\n"
            ."payment,{$m->membership_number},2099-01-01,100,,,\n"
            ."obligation,{$m->membership_number},2025-01-01,abc,NOPE,,\n";
        $this->actingAs($treasurer)->post(route('admin.finance.imports.preview'), ['kind' => 'finance_transactions', 'file' => UploadedFile::fake()->createWithContent('bad.csv', $bad), 'source_approval_reference' => 'Synthetic source 1']);
        $batch = ImportBatch::latest('id')->sole();
        $this->assertSame(4, $batch->invalid_count);
        $messages = $batch->rows()->get()->pluck('messages')->flatten()->implode(' | ');
        foreach (['record_type must be', 'not in the register', 'cannot be in the future', 'need the source reference', 'category_code must match'] as $expected) {
            $this->assertStringContainsString($expected, $messages);
        }
        $this->actingAs($this->officer('treasurer'))->post(route('admin.finance.imports.commit', $batch), ['confirm' => '1'])->assertSessionHasErrors('domain');

        $opening = "membership_number,amount_ngn,description\n{$m->membership_number},-5000,Credit balance\n";
        $this->actingAs($treasurer)->post(route('admin.finance.imports.preview'), ['kind' => 'opening_balances', 'file' => UploadedFile::fake()->createWithContent('open.csv', $opening), 'source_approval_reference' => 'Synthetic source 2', 'cutoff_date' => '2025-12-31', 'fee_category_id' => $category->id]);
        $openBatch = ImportBatch::where('kind', 'opening_balances')->latest('id')->sole();
        $this->assertSame(1, $openBatch->invalid_count);
        $this->assertStringContainsString('Credit balances cannot be imported', $openBatch->rows()->first()->messages[0]);
        $this->assertSame(0, Payment::count());
    }

    public function test_opening_balances_are_labelled_and_reconcile(): void
    {
        [$a, $b] = [$this->member(), $this->member()];
        $category = $this->category();
        $csv = "membership_number,amount_ngn,description\n{$a->membership_number},15000,Owed at cut-off\n{$b->membership_number},2500.50,Owed at cut-off\n";
        $this->actingAs($this->officer('treasurer'))->post(route('admin.finance.imports.preview'), ['kind' => 'opening_balances', 'file' => UploadedFile::fake()->createWithContent('open.csv', $csv), 'source_approval_reference' => 'Signed balance list SYN', 'cutoff_date' => '2025-12-31', 'fee_category_id' => $category->id]);
        $batch = ImportBatch::latest('id')->sole();
        $this->actingAs($this->officer('treasurer'))->post(route('admin.finance.imports.commit', $batch), ['confirm' => '1'])->assertSessionHasNoErrors();
        $assessment = Assessment::where('import_batch_id', $batch->id)->sole();
        $this->assertSame(['opening_balance', 'posted', 1_750_050], [$assessment->origin, $assessment->status, $assessment->total_kobo]);
        $this->assertSame(1_500_000, app(LedgerQuery::class)->memberSummary($a->id)['outstanding']);
        $this->assertSame(250_050, app(LedgerQuery::class)->memberSummary($b->id)['outstanding']);
        $this->assertEmpty(array_filter(app(LedgerQuery::class)->exceptions()));
    }
}
