<?php

namespace App\Providers;

use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('boba.force_https')) {
            URL::forceScheme('https');
        }
        Paginator::defaultView('partials.pagination');

        $tooMany = fn (string $message) => fn (Request $request, array $headers) => back()->withInput($request->except(['password', 'token']))->withErrors(['domain' => $message])->withHeaders($headers);

        RateLimiter::for('logins', fn (Request $request) => [
            Limit::perMinute((int) config('boba.accounts.login_attempts_per_minute'))->by('login:'.strtolower((string) $request->input('email')).'|'.$request->ip())
                ->response($tooMany('Too many sign-in attempts. Wait a minute and try again.')),
            Limit::perMinute(20)->by('login-ip:'.$request->ip())->response($tooMany('Too many sign-in attempts. Wait a minute and try again.')),
        ]);
        RateLimiter::for('applications', fn (Request $request) => Limit::perHour((int) config('boba.membership.applications_per_hour'))->by('apply:'.$request->ip())
            ->response($tooMany('Too many applications have been sent from this connection. Please try again later.')));
        RateLimiter::for('status-lookups', fn (Request $request) => Limit::perMinute(10)->by('status:'.$request->ip())
            ->response($tooMany('Too many attempts. Wait a minute and try again.')));
        RateLimiter::for('enquiries', fn (Request $request) => Limit::perHour(6)->by('enquiry:'.$request->ip())
            ->response($tooMany('Too many messages have been sent from this connection. Please try again later.')));
        RateLimiter::for('recovery', fn (Request $request) => Limit::perHour(5)->by('recover:'.$request->ip())
            ->response($tooMany('Too many requests. Please try again later.')));
        RateLimiter::for('tokens', fn (Request $request) => Limit::perMinute(10)->by('token:'.$request->ip())
            ->response($tooMany('Too many attempts. Wait a minute and try again.')));

        View::composer(['layouts.public', 'public.*'], function ($view) {
            $view->with('org', [
                'name' => Settings::get('org.name'),
                'short' => Settings::get('org.short_name'),
                'motto' => Settings::get('org.motto'),
                'publish_contact' => Settings::bool('contact.publish_details'),
                'email' => Settings::get('contact.email'),
                'phone' => Settings::get('contact.phone'),
                'address' => Settings::get('contact.address'),
            ]);
        });
    }
}
