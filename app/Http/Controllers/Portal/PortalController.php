<?php

namespace App\Http\Controllers\Portal;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Member;
use App\Models\Post;
use App\Services\AccountService;
use App\Services\Finance\LedgerQuery;
use App\Services\MemberService;
use App\Services\UploadService;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Member self-service. The member is always resolved from the session —
 * never from a request parameter — so one member cannot reach another's data.
 */
class PortalController extends Controller
{
    public function __construct(private LedgerQuery $ledger) {}

    private function member(Request $request): Member
    {
        return $request->user()->member;
    }

    public function dashboard(Request $request): View
    {
        $member = $this->member($request);
        $readIds = DB::table('announcement_reads')->where('member_id', $member->id)->pluck('post_id');

        return view('portal.dashboard', [
            'member' => $member,
            'summary' => $this->ledger->memberSummary($member->id),
            'notices' => $this->noticeQuery()->limit(3)->get(),
            'unread' => $this->noticeQuery()->whereNotIn('id', $readIds)->count(),
            'events' => $this->eventQuery()->where('starts_at', '>=', now())->orderBy('starts_at')->limit(3)->get(),
        ]);
    }

    public function profile(Request $request): View
    {
        return view('portal.profile', ['member' => $this->member($request)]);
    }

    public function updateProfile(Request $request, MemberService $members): RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'integer'],
            'preferred_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{7,40}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
        ], ['phone.regex' => 'Enter a valid phone number.']);
        $members->correct($this->member($request), $data, (int) $data['version'], $request->user(), 'Member self-service update', true);

        return redirect()->route('portal.profile')->with('status', 'Your profile has been updated.');
    }

    public function finances(Request $request): View
    {
        $member = $this->member($request);

        return view('portal.finances', [
            'member' => $member,
            'summary' => $this->ledger->memberSummary($member->id),
            'obligations' => $this->ledger->openObligations($member->id),
            'payments' => $this->ledger->memberLedgerDetail($member->id),
        ]);
    }

    public function statement(Request $request): View
    {
        [$from, $to] = $this->period($request);
        $member = $this->member($request);

        return view('portal.statement', ['member' => $member, 'from' => $from, 'to' => $to, 'statement' => $this->ledger->statement($member->id, $from, $to)]);
    }

    public function statementCsv(Request $request): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $member = $this->member($request);
        $statement = $this->ledger->statement($member->id, $from, $to);
        Audit::record('statement.exported', 'Member exported own statement', $member);
        $rows = [['', 'Opening position', '', '', '', Money::decimal($statement['opening'])]];
        foreach ($statement['lines'] as $line) {
            $rows[] = [$line['date'], $line['type'], $line['reference'], Money::decimal($line['debit']), Money::decimal($line['credit']), Money::decimal($line['balance'])];
        }
        $rows[] = ['', 'Closing position', '', '', '', Money::decimal($statement['closing'])];

        return Csv::download("statement-{$member->membership_number}-{$from->toDateString()}-{$to->toDateString()}.csv", ['Date', 'Type', 'Reference', 'Charges (NGN)', 'Payments (NGN)', 'Position (NGN)'], $rows);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $to = isset($data['to']) ? CarbonImmutable::parse($data['to']) : CarbonImmutable::now(config('boba.display_timezone'))->startOfDay();
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from']) : $to->subYear()->addDay();

        return [$from, $to];
    }

    public function notices(Request $request): View
    {
        $member = $this->member($request);

        return view('portal.notices', [
            'notices' => $this->noticeQuery()->paginate(15),
            'read' => DB::table('announcement_reads')->where('member_id', $member->id)->pluck('post_id')->flip(),
        ]);
    }

    public function markRead(Request $request, Post $post): RedirectResponse
    {
        abort_unless($this->noticeQuery()->whereKey($post->id)->exists(), 404);
        DB::table('announcement_reads')->insertOrIgnore(['post_id' => $post->id, 'member_id' => $this->member($request)->id, 'read_at' => now()]);

        return back()->with('status', 'Marked as read.');
    }

    public function events(Request $request): View
    {
        $member = $this->member($request);

        return view('portal.events', [
            'upcoming' => $this->eventQuery()->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->get(),
            'past' => $this->eventQuery()->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at')->limit(10)->get(),
            'responses' => EventRsvp::where('member_id', $member->id)->pluck('response', 'event_id'),
        ]);
    }

    public function event(Request $request, Event $event): View
    {
        abort_unless($this->eventQuery()->whereKey($event->id)->exists(), 404);

        return view('portal.event', [
            'event' => $event,
            'rsvp' => EventRsvp::where('event_id', $event->id)->where('member_id', $this->member($request)->id)->first(),
            'attending' => EventRsvp::where('event_id', $event->id)->where('response', 'attending')->count(),
        ]);
    }

    public function rsvp(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->eventQuery()->whereKey($event->id)->exists(), 404);
        $data = $request->validate(['response' => ['required', 'in:attending,not_attending,maybe'], 'guests' => ['nullable', 'integer', 'min:0', 'max:10'], 'note' => ['nullable', 'string', 'max:255']]);
        if (! $event->rsvpOpen()) {
            return back()->withErrors(['domain' => 'Responses are closed for this event.']);
        }
        $member = $this->member($request);
        DB::transaction(function () use ($event, $member, $data) {
            $locked = Event::lockForUpdate()->findOrFail($event->id);
            if ($data['response'] === 'attending' && $locked->capacity) {
                $taken = EventRsvp::where('event_id', $locked->id)->where('response', 'attending')->where('member_id', '!=', $member->id)->sum(DB::raw('1 + guests'));
                if ($taken + 1 + (int) ($data['guests'] ?? 0) > $locked->capacity) {
                    throw new DomainRuleException('The event has reached its capacity.');
                }
            }
            EventRsvp::updateOrCreate(['event_id' => $locked->id, 'member_id' => $member->id], ['response' => $data['response'], 'guests' => (int) ($data['guests'] ?? 0), 'note' => $data['note'] ?? null]);
        });
        Audit::record('event.rsvp', "RSVP {$data['response']} for {$event->title}", $event);

        return back()->with('status', 'Your response has been saved.');
    }

    public function documents(): View
    {
        return view('portal.documents', ['documents' => Document::with('file')->where('status', 'published')->latest()->get()]);
    }

    public function downloadDocument(Request $request, Document $document, UploadService $uploads): BinaryFileResponse
    {
        abort_unless($document->status === 'published', 404);
        Audit::record('document.downloaded', "Member downloaded {$document->title}", $document);

        return response()->download($uploads->privatePath($document->file), $document->file->original_name, ['Content-Type' => $document->file->mime, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function security(Request $request): View
    {
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity']);

        return view('portal.security', ['sessions' => $sessions, 'current' => $request->session()->getId()]);
    }

    public function changePassword(Request $request, AccountService $accounts): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', 'string', 'confirmed', 'max:128']]);
        $accounts->changePassword($request->user(), $data['current_password'], $data['password'], $request->session()->getId());
        // true = delete the old session record too, so a copied old cookie stops working.
        $request->session()->regenerate(true);

        return redirect()->route('portal.security')->with('status', 'Your password has been changed and your other sessions have been signed out.');
    }

    public function endOtherSessions(Request $request, AccountService $accounts): RedirectResponse
    {
        $count = $accounts->endAllSessions($request->user(), $request->session()->getId());
        Audit::security('sessions.ended_by_user', $request->user()->id, ['count' => $count]);

        return redirect()->route('portal.security')->with('status', "Signed out of {$count} other session(s).");
    }

    private function noticeQuery()
    {
        return Post::where('type', 'announcement')->where('status', 'published')->latest('published_at');
    }

    private function eventQuery()
    {
        return Event::whereIn('status', ['published', 'cancelled']);
    }
}
