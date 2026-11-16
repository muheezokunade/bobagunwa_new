<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\SecurityEvent;
use App\Support\Audit;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['action' => ['nullable', 'string', 'max:100'], 'subject' => ['nullable', 'string', 'max:60'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'], 'log' => ['nullable', 'in:audit,security']]);
        if (($filters['log'] ?? 'audit') === 'security') {
            $query = SecurityEvent::with('user:id,name,email')->latest('id');
            if (! empty($filters['action'])) {
                $query->where('event', 'like', $filters['action'].'%');
            }
        } else {
            $query = AuditEvent::with('actor:id,name')->latest('id');
            if (! empty($filters['action'])) {
                $query->where('action', 'like', $filters['action'].'%');
            }
            if (! empty($filters['subject'])) {
                $query->where('subject_type', $filters['subject']);
            }
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', \Carbon\CarbonImmutable::parse($filters['to'])->addDay());
        }

        return view('admin.audit', ['events' => $query->paginate(50)->withQueryString(), 'filters' => $filters, 'log' => $filters['log'] ?? 'audit']);
    }

    public function export(Request $request): StreamedResponse
    {
        Audit::record('audit.exported', 'Audit history exported');

        return Csv::download('audit-'.now()->format('Ymd').'.csv', ['Time (UTC)', 'Officer', 'Action', 'Subject', 'Summary', 'Request'],
            AuditEvent::with('actor:id,name')->orderBy('id')->cursor()->map(fn ($e) => [$e->created_at->toDateTimeString(), $e->actor?->name ?? 'System', $e->action, $e->subject_type ? "{$e->subject_type} #{$e->subject_id}" : '', $e->summary, $e->request_id]));
    }
}
