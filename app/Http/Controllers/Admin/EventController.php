<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Media;
use App\Support\Audit;
use App\Support\Csv;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventController extends Controller
{
    private const TRANSITIONS = ['draft' => ['published', 'archived'], 'published' => ['cancelled', 'archived', 'draft'], 'cancelled' => ['archived', 'published'], 'archived' => ['draft']];

    public function index(Request $request): View
    {
        $events = Event::withCount(['rsvps as attending_count' => fn ($q) => $q->where('response', 'attending')])->orderByDesc('starts_at')->paginate(25);

        return view('admin.events.index', ['events' => $events]);
    }

    public function create(): View
    {
        return view('admin.events.form', ['event' => null, 'media' => Media::latest()->limit(60)->get(), 'rsvps' => collect()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $base = Str::slug($data['title']) ?: 'event';
        $slug = $base.'-'.CarbonImmutable::parse($data['starts_at'])->format('Y-m-d');
        $n = 2;
        while (Event::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }
        $event = Event::create($data + ['slug' => $slug, 'status' => 'draft', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        Audit::record('event.created', "Event drafted: {$event->title}", $event);

        return redirect()->route('admin.events.edit', $event)->with('status', 'Event saved as a draft.');
    }

    public function edit(Event $event): View
    {
        return view('admin.events.form', ['event' => $event, 'media' => Media::latest()->limit(60)->get(), 'rsvps' => EventRsvp::with('member:id,membership_number,full_name')->where('event_id', $event->id)->orderBy('response')->get()]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $event->update($this->validated($request) + ['updated_by' => $request->user()->id]);
        Audit::record('event.updated', "Event updated: {$event->title}", $event, ['changes' => array_keys($event->getChanges())]);

        return redirect()->route('admin.events.edit', $event)->with('status', 'Event saved.');
    }

    public function status(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:draft,published,cancelled,archived']]);
        if (! in_array($data['status'], self::TRANSITIONS[$event->status] ?? [], true)) {
            throw new DomainRuleException("An event cannot move from {$event->status} to {$data['status']}.");
        }
        $event->update(['status' => $data['status'], 'published_at' => $data['status'] === 'published' ? ($event->published_at ?? now()) : $event->published_at, 'updated_by' => $request->user()->id]);
        Audit::record('event.status_changed', "Event {$data['status']}: {$event->title}", $event);

        return back()->with('status', 'Event '.$data['status'].'.');
    }

    public function responses(Event $event): StreamedResponse
    {
        Audit::record('event.responses_exported', "Attendance responses exported for {$event->title}", $event);

        return Csv::download('responses-'.$event->slug.'.csv', ['Membership number', 'Name', 'Response', 'Guests', 'Note', 'Updated'],
            EventRsvp::with('member:id,membership_number,full_name')->where('event_id', $event->id)->get()->map(fn ($r) => [$r->member->membership_number, $r->member->full_name, $r->response, $r->guests, $r->note, $r->updated_at?->toDateTimeString()]));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:60000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'audience' => ['required', 'in:public,members'],
            'rsvp_enabled' => ['nullable', 'boolean'],
            'rsvp_closes_at' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],
        ]);
        // Officers enter Lagos local time; storage is UTC.
        foreach (['starts_at', 'ends_at', 'rsvp_closes_at'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = CarbonImmutable::parse($data[$field], config('boba.display_timezone'))->utc();
            }
        }
        $data['rsvp_enabled'] = $request->boolean('rsvp_enabled');

        return $data;
    }
}
