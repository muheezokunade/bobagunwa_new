<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\FeeCategory;
use App\Models\Member;
use App\Services\Finance\AssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function __construct(private AssessmentService $assessments)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $query = Assessment::with('category')->latest('id');
        if (in_array($status, ['draft', 'posted', 'discarded'], true)) {
            $query->where('status', $status);
        }

        return view('admin.finance.assessments.index', ['assessments' => $query->paginate(25)->withQueryString(), 'status' => $status]);
    }

    public function create(): View
    {
        return view('admin.finance.assessments.create', [
            'categories' => FeeCategory::where('is_active', true)->orderBy('name')->get(),
            'members' => Member::where('status', 'active')->orderBy('membership_number')->get(['id', 'membership_number', 'full_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fee_category_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'string', 'max:20'],
            'effective_on' => ['required', 'date_format:Y-m-d'],
            'due_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_on'],
            'period_label' => ['nullable', 'string', 'max:60'],
            'target_mode' => ['required', 'in:all_active,selected'],
            'member_ids' => ['nullable', 'array', 'max:5000'],
            'member_ids.*' => ['integer'],
        ]);
        $assessment = $this->assessments->createDraft($data, $request->user());

        return redirect()->route('admin.finance.assessments.show', $assessment)->with('status', 'Draft saved. Review the preview, then post it.');
    }

    public function show(Assessment $assessment): View
    {
        $assessment->load('category', 'creator', 'poster');

        return view('admin.finance.assessments.show', [
            'assessment' => $assessment,
            'preview' => $assessment->status === 'draft' ? $this->assessments->preview($assessment) : null,
            'obligations' => $assessment->status === 'posted' ? $assessment->obligations()->with('member:id,membership_number,full_name')->orderBy('member_id')->paginate(50) : null,
        ]);
    }

    public function post(Request $request, Assessment $assessment): RedirectResponse
    {
        $data = $request->validate(['idempotency_key' => ['required', 'string'], 'expected_targets' => ['required', 'integer', 'min:1'], 'confirm' => ['accepted']], ['confirm.accepted' => 'Confirm that the preview is correct before posting.']);
        [$posted, $replayed] = $this->assessments->post($assessment, $request->user(), $data['idempotency_key'], (int) $data['expected_targets']);

        return redirect()->route('admin.finance.assessments.show', $posted)->with('status', $replayed ? 'This assessment had already been posted; nothing was duplicated.' : "Assessment posted to {$posted->target_count} members.");
    }

    public function discard(Request $request, Assessment $assessment): RedirectResponse
    {
        $this->assessments->discard($assessment, $request->user());

        return redirect()->route('admin.finance.assessments.index')->with('status', 'Draft discarded.');
    }
}
