<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Leader;
use App\Models\Post;
use App\Support\Content;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Public pages. Only published, public-audience records are ever shown.
 */
class SiteController extends Controller
{
    public function home(): View
    {
        return view('public.home', [
            'news' => Post::where('type', 'news')->where('audience', 'public')->where('status', 'published')->latest('published_at')->limit(3)->get(),
            'events' => $this->publicEvents()->where('starts_at', '>=', now())->orderBy('starts_at')->limit(3)->get(),
            'leaders' => Leader::with('photo')->where('status', 'published')->where('group', 'council')->orderBy('sort_order')->limit(4)->get(),
            'about' => Content::published('about'),
            'welcome' => Content::published('welcome'),
        ]);
    }

    public function about(): View
    {
        return view('public.about', ['page' => Content::published('about')]);
    }

    public function history(): View
    {
        return view('public.history', ['page' => Content::published('history')]);
    }

    public function leadership(): View
    {
        $leaders = Leader::with('photo')->where('status', 'published')->orderByRaw("FIELD(`group`, 'council', 'chiefs', 'executive', 'other')")->orderBy('sort_order')->orderBy('name')->get()->groupBy('group');

        return view('public.leadership', ['groups' => $leaders]);
    }

    public function membership(): View
    {
        return view('public.membership', ['page' => Content::published('membership')]);
    }

    public function activities(): View
    {
        return view('public.activities', ['activities' => Activity::with('media')->where('status', 'published')->orderBy('sort_order')->orderBy('title')->get()]);
    }

    public function events(): View
    {
        return view('public.events', [
            'upcoming' => $this->publicEvents()->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->get(),
            'past' => $this->publicEvents()->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at')->limit(12)->get(),
        ]);
    }

    public function event(string $slug): View
    {
        $event = $this->publicEvents()->where('slug', $slug)->firstOrFail();

        return view('public.event', ['event' => $event]);
    }

    public function news(): View
    {
        return view('public.news', ['posts' => Post::with('cover')->where('type', 'news')->where('audience', 'public')->where('status', 'published')->latest('published_at')->paginate(10)]);
    }

    public function article(string $slug): View
    {
        $post = Post::with('cover')->where('type', 'news')->where('audience', 'public')->where('status', 'published')->where('slug', $slug)->firstOrFail();

        return view('public.article', ['post' => $post]);
    }

    public function galleries(): View
    {
        return view('public.galleries', ['galleries' => Gallery::with('items.media')->where('status', 'published')->latest()->get()]);
    }

    public function gallery(string $slug): View
    {
        return view('public.gallery', ['gallery' => Gallery::with('items.media')->where('status', 'published')->where('slug', $slug)->firstOrFail()]);
    }

    public function legal(string $key): View
    {
        return view('public.legal', ['key' => $key, 'page' => Content::published($key)]);
    }

    public function sitemap(): Response
    {
        $urls = collect(['home', 'about', 'history', 'leadership', 'membership', 'membership.apply', 'activities', 'events.index', 'news.index', 'gallery.index', 'contact', 'privacy', 'terms'])->map(fn ($r) => $r === 'home' ? rtrim(route('home'), '/').'/' : route($r));
        $urls = $urls->merge(Post::where('type', 'news')->where('audience', 'public')->where('status', 'published')->pluck('slug')->map(fn ($s) => route('news.show', $s)))
            ->merge($this->publicEvents()->pluck('slug')->map(fn ($s) => route('events.show', $s)));
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .$urls->map(fn ($u) => '<url><loc>'.e($u).'</loc></url>')->implode('').'</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /** robots.txt needs an absolute Sitemap URL, so it is generated from APP_URL. */
    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        foreach (['/admin', '/portal', '/account', '/login', '/membership/status', '/membership/receipt'] as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function publicEvents()
    {
        return Event::with('cover')->where('audience', 'public')->whereIn('status', ['published', 'cancelled']);
    }
}
