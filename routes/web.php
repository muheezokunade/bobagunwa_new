<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Public\ApplicationController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\SiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
*/
Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/about', [SiteController::class, 'about'])->name('about');
Route::get('/history', [SiteController::class, 'history'])->name('history');
Route::get('/leadership', [SiteController::class, 'leadership'])->name('leadership');
Route::get('/membership', [SiteController::class, 'membership'])->name('membership');
Route::get('/activities', [SiteController::class, 'activities'])->name('activities');
Route::get('/events', [SiteController::class, 'events'])->name('events.index');
Route::get('/events/{slug}', [SiteController::class, 'event'])->name('events.show');
Route::get('/news', [SiteController::class, 'news'])->name('news.index');
Route::get('/news/{slug}', [SiteController::class, 'article'])->name('news.show');
Route::get('/gallery', [SiteController::class, 'galleries'])->name('gallery.index');
Route::get('/gallery/{slug}', [SiteController::class, 'gallery'])->name('gallery.show');
Route::get('/privacy', [SiteController::class, 'legal'])->defaults('key', 'privacy')->name('privacy');
Route::get('/terms', [SiteController::class, 'legal'])->defaults('key', 'terms')->name('terms');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SiteController::class, 'robots'])->name('robots');
// Retain the established public URL of the previous website.
Route::redirect('/members', '/membership', 301);

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:enquiries')->name('contact.store');

Route::get('/membership/apply', [ApplicationController::class, 'create'])->name('membership.apply');
Route::post('/membership/apply', [ApplicationController::class, 'store'])->middleware('throttle:applications')->name('membership.apply.store');
Route::get('/membership/receipt', [ApplicationController::class, 'receipt'])->name('membership.receipt');
Route::get('/membership/status', [ApplicationController::class, 'statusForm'])->name('membership.status');
Route::post('/membership/status', [ApplicationController::class, 'status'])->middleware('throttle:status-lookups')->name('membership.status.lookup');
Route::get('/membership/status/respond', [ApplicationController::class, 'respondRedirect'])->name('membership.status.respond.redirect');
Route::post('/membership/status/respond', [ApplicationController::class, 'respond'])->middleware('throttle:status-lookups')->name('membership.status.respond');

/*
|--------------------------------------------------------------------------
| Authentication and account lifecycle
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:logins')->name('login.attempt');
    Route::get('/login/two-factor', [TwoFactorChallengeController::class, 'show'])->name('login.two-factor');
    Route::post('/login/two-factor', [TwoFactorChallengeController::class, 'verify'])->middleware('throttle:two-factor')->name('login.two-factor.verify');
    Route::get('/account/recover', [AccountController::class, 'recoverForm'])->name('password.request');
    Route::post('/account/recover', [AccountController::class, 'recover'])->middleware('throttle:recovery')->name('password.email');
});
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/account/activate/{token}', [AccountController::class, 'activateLink'])->name('account.activate');
Route::get('/account/activate', [AccountController::class, 'activateForm'])->name('account.activate.form');
Route::post('/account/activate', [AccountController::class, 'activate'])->middleware('throttle:tokens')->name('account.activate.store');
Route::get('/account/reset/{token}', [AccountController::class, 'resetLink'])->name('password.reset');
Route::get('/account/reset', [AccountController::class, 'resetForm'])->name('password.reset.form');
Route::post('/account/reset', [AccountController::class, 'reset'])->middleware('throttle:tokens')->name('password.update');

/*
|--------------------------------------------------------------------------
| Member portal — every query is scoped to the signed-in member
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'member'])->prefix('portal')->name('portal.')->controller(PortalController::class)->group(function () {
    Route::get('/', 'dashboard')->name('dashboard');
    Route::get('/profile', 'profile')->name('profile');
    Route::post('/profile', 'updateProfile')->name('profile.update');
    Route::get('/finances', 'finances')->name('finances');
    Route::get('/finances/statement', 'statement')->name('statement');
    Route::get('/finances/statement.csv', 'statementCsv')->name('statement.csv');
    Route::get('/notices', 'notices')->name('notices');
    Route::post('/notices/{post}/read', 'markRead')->name('notices.read');
    Route::get('/events', 'events')->name('events');
    Route::get('/events/{event}', 'event')->name('events.show');
    Route::post('/events/{event}/rsvp', 'rsvp')->name('events.rsvp');
    Route::get('/documents', 'documents')->name('documents');
    Route::get('/documents/{document}/download', 'downloadDocument')->name('documents.download');
    Route::get('/security', 'security')->name('security');
    Route::post('/security/password', 'changePassword')->name('security.password');
    Route::post('/security/sessions/end-others', 'endOtherSessions')->name('security.sessions.end');
});

/*
|--------------------------------------------------------------------------
| Administration — authorised officers; each area checks a named permission
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff', 'two-factor'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/account', [Admin\AccountController::class, 'show'])->name('account');
    Route::post('/account/password', [Admin\AccountController::class, 'password'])->name('account.password');
    Route::post('/account/two-factor/start', [Admin\AccountController::class, 'twoFactorStart'])->name('account.two-factor.start');
    Route::post('/account/two-factor/confirm', [Admin\AccountController::class, 'twoFactorConfirm'])->name('account.two-factor.confirm');
    Route::post('/account/two-factor/recovery-codes', [Admin\AccountController::class, 'twoFactorRecovery'])->name('account.two-factor.recovery');
    Route::post('/account/two-factor/disable', [Admin\AccountController::class, 'twoFactorDisable'])->name('account.two-factor.disable');

    Route::middleware('permission:integrity.alerts')->controller(Admin\AlertController::class)->group(function () {
        Route::get('/alerts', 'index')->name('alerts.index');
        Route::post('/alerts/check', 'check')->name('alerts.check');
        Route::get('/alerts/{alert}', 'show')->whereNumber('alert')->name('alerts.show');
        Route::post('/alerts/{alert}/acknowledge', 'acknowledge')->whereNumber('alert')->name('alerts.acknowledge');
        Route::post('/alerts/{alert}/resolve', 'resolve')->whereNumber('alert')->name('alerts.resolve');
    });
    Route::middleware('permission:settings.manage')->controller(Admin\AlertController::class)->group(function () {
        Route::post('/alerts/email/test', 'emailTest')->name('alerts.email.test');
        Route::post('/alerts/email/verify', 'emailVerify')->name('alerts.email.verify');
    });

    Route::middleware('permission:applications.review')->controller(Admin\ApplicationController::class)->group(function () {
        Route::get('/applications', 'index')->name('applications.index');
        Route::get('/applications/{application}', 'show')->name('applications.show');
        Route::post('/applications/{application}/decision', 'decide')->name('applications.decide');
    });

    Route::middleware('permission:applications.review|accounts.issue|access.manage')->controller(Admin\MessageController::class)->group(function () {
        Route::get('/messages', 'index')->name('messages.index');
        Route::get('/messages/{message}', 'show')->name('messages.show');
        Route::post('/messages/{message}/delivered', 'delivered')->name('messages.delivered');
        Route::post('/messages/{message}/cancel', 'cancel')->name('messages.cancel');
    });

    Route::controller(Admin\MemberController::class)->group(function () {
        Route::get('/members', 'index')->middleware('permission:members.read')->name('members.index');
        Route::get('/members/export', 'export')->middleware('permission:members.export')->name('members.export');
        Route::get('/members/create', 'create')->middleware('permission:members.manage')->name('members.create');
        Route::post('/members', 'store')->middleware('permission:members.manage')->name('members.store');
        Route::get('/members/{member}', 'show')->middleware('permission:members.read')->name('members.show');
        Route::post('/members/{member}', 'update')->middleware('permission:members.manage')->name('members.update');
        Route::post('/members/{member}/status', 'status')->middleware('permission:members.manage')->name('members.status');
        Route::post('/members/{member}/access', 'access')->middleware('permission:accounts.issue')->name('members.access');
    });

    Route::middleware('permission:members.import')->controller(Admin\MemberImportController::class)->group(function () {
        Route::get('/imports/members', 'index')->name('imports.members.index');
        Route::get('/imports/members/template.csv', 'template')->name('imports.members.template');
        Route::post('/imports/members', 'preview')->name('imports.members.preview');
        Route::get('/imports/members/{batch}', 'show')->name('imports.members.show');
        Route::post('/imports/members/{batch}/commit', 'commit')->name('imports.members.commit');
        Route::post('/imports/members/{batch}/cancel', 'cancel')->name('imports.members.cancel');
    });

    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('/', [Admin\FinanceController::class, 'index'])->middleware('permission:finance.read')->name('index');
        Route::get('/members/{member}', [Admin\FinanceController::class, 'member'])->middleware('permission:finance.read')->name('members.show');
        Route::get('/members/{member}/statement.csv', [Admin\FinanceController::class, 'statementCsv'])->middleware('permission:finance.reports.export')->name('members.statement.csv');
        Route::post('/obligations/{obligation}/adjust', [Admin\FinanceController::class, 'adjust'])->middleware('permission:finance.adjust')->name('obligations.adjust');

        Route::controller(Admin\CategoryController::class)->group(function () {
            Route::get('/categories', 'index')->middleware('permission:finance.read')->name('categories.index');
            Route::post('/categories', 'store')->middleware('permission:finance.categories.manage')->name('categories.store');
            Route::post('/categories/{category}', 'update')->middleware('permission:finance.categories.manage')->name('categories.update');
        });

        Route::controller(Admin\AssessmentController::class)->group(function () {
            Route::get('/assessments', 'index')->middleware('permission:finance.read')->name('assessments.index');
            Route::get('/assessments/create', 'create')->middleware('permission:finance.assessments.manage')->name('assessments.create');
            Route::post('/assessments', 'store')->middleware('permission:finance.assessments.manage')->name('assessments.store');
            Route::get('/assessments/{assessment}', 'show')->middleware('permission:finance.read')->name('assessments.show');
            Route::post('/assessments/{assessment}/post', 'post')->middleware('permission:finance.assessments.manage')->name('assessments.post');
            Route::post('/assessments/{assessment}/discard', 'discard')->middleware('permission:finance.assessments.manage')->name('assessments.discard');
        });

        Route::controller(Admin\PaymentController::class)->group(function () {
            Route::get('/payments', 'index')->middleware('permission:finance.read')->name('payments.index');
            Route::get('/payments/create', 'create')->middleware('permission:finance.payments.record')->name('payments.create');
            Route::post('/payments', 'store')->middleware('permission:finance.payments.record')->name('payments.store');
            Route::get('/payments/{payment}', 'show')->middleware('permission:finance.read')->name('payments.show');
            Route::get('/payments/{payment}/evidence', 'evidence')->middleware('permission:finance.read')->name('payments.evidence');
            Route::post('/payments/{payment}/verify', 'verify')->middleware('permission:finance.payments.verify')->name('payments.verify');
            Route::post('/payments/{payment}/reject', 'reject')->middleware('permission:finance.payments.verify')->name('payments.reject');
            Route::post('/payments/{payment}/allocate', 'allocate')->middleware('permission:finance.allocate')->name('payments.allocate');
            Route::post('/payments/{payment}/reverse', 'reverse')->middleware('permission:finance.reverse')->name('payments.reverse');
            Route::post('/allocations/{allocation}/reverse', 'reverseAllocation')->middleware('permission:finance.reverse')->name('allocations.reverse');
        });

        Route::controller(Admin\ReportController::class)->group(function () {
            Route::get('/reports', 'index')->middleware('permission:finance.read')->name('reports.index');
            Route::get('/reports/{report}.csv', 'export')->middleware('permission:finance.reports.export')->name('reports.export');
        });

        Route::middleware('permission:finance.import')->controller(Admin\FinanceImportController::class)->group(function () {
            Route::get('/imports', 'index')->name('imports.index');
            Route::get('/imports/template/{kind}.csv', 'template')->name('imports.template');
            Route::post('/imports', 'preview')->name('imports.preview');
            Route::get('/imports/{batch}', 'show')->name('imports.show');
            Route::post('/imports/{batch}/commit', 'commit')->name('imports.commit');
            Route::post('/imports/{batch}/cancel', 'cancel')->name('imports.cancel');
        });
    });

    Route::middleware('permission:content.manage|content.publish')->prefix('content')->name('content.')->controller(Admin\ContentController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/pages/{page}', 'editPage')->name('pages.edit');
        Route::post('/pages/{page}', 'saveRevision')->name('pages.save');
        Route::post('/pages/{page}/publish/{revision}', 'publishRevision')->name('pages.publish');
        Route::post('/pages/{page}/unpublish', 'unpublishPage')->name('pages.unpublish');
        Route::get('/media', 'media')->name('media');
        Route::post('/media', 'uploadMedia')->name('media.upload');
        Route::get('/{type}', 'list')->whereIn('type', ['news', 'announcements', 'activities', 'leaders', 'galleries', 'documents'])->name('list');
        Route::get('/{type}/create', 'create')->whereIn('type', ['news', 'announcements', 'activities', 'leaders', 'galleries', 'documents'])->name('create');
        Route::post('/{type}', 'store')->whereIn('type', ['news', 'announcements', 'activities', 'leaders', 'galleries', 'documents'])->name('store');
        Route::get('/{type}/{id}', 'edit')->whereIn('type', ['news', 'announcements', 'activities', 'leaders', 'galleries', 'documents'])->whereNumber('id')->name('edit');
        Route::post('/{type}/{id}', 'update')->whereIn('type', ['news', 'announcements', 'activities', 'leaders', 'galleries', 'documents'])->whereNumber('id')->name('update');
        Route::post('/{type}/{id}/status', 'status')->whereIn('type', ['news', 'announcements', 'activities', 'leaders', 'galleries', 'documents'])->whereNumber('id')->name('status');
        Route::post('/galleries/{id}/items', 'addGalleryItem')->whereNumber('id')->name('galleries.items');
    });

    Route::middleware('permission:events.manage')->controller(Admin\EventController::class)->group(function () {
        Route::get('/events', 'index')->name('events.index');
        Route::get('/events/create', 'create')->name('events.create');
        Route::post('/events', 'store')->name('events.store');
        Route::get('/events/{event}', 'edit')->name('events.edit');
        Route::post('/events/{event}', 'update')->name('events.update');
        Route::post('/events/{event}/status', 'status')->name('events.status');
        Route::get('/events/{event}/responses.csv', 'responses')->name('events.responses');
    });

    Route::middleware('permission:enquiries.manage')->controller(Admin\EnquiryController::class)->group(function () {
        Route::get('/enquiries', 'index')->name('enquiries.index');
        Route::get('/enquiries/{enquiry}', 'show')->name('enquiries.show');
        Route::post('/enquiries/{enquiry}', 'update')->name('enquiries.update');
    });

    Route::middleware('permission:access.manage')->controller(Admin\AccessController::class)->group(function () {
        Route::get('/access', 'index')->name('access.index');
        Route::post('/access', 'store')->name('access.store');
        Route::get('/access/users/{user}', 'show')->name('access.show');
        Route::post('/access/users/{user}/roles', 'roles')->name('access.roles');
        Route::post('/access/users/{user}/status', 'status')->name('access.status');
        Route::post('/access/users/{user}/link', 'link')->name('access.link');
        Route::post('/access/users/{user}/two-factor-reset', 'twoFactorReset')->name('access.two-factor.reset');
        Route::post('/access/recovery/{recovery}', 'recovery')->name('access.recovery');
    });

    Route::middleware('permission:audit.read')->controller(Admin\AuditController::class)->group(function () {
        Route::get('/audit', 'index')->name('audit.index');
        Route::get('/audit/export.csv', 'export')->name('audit.export');
    });

    Route::middleware('permission:settings.manage')->controller(Admin\SettingsController::class)->group(function () {
        Route::get('/settings', 'index')->name('settings.index');
        Route::post('/settings', 'update')->name('settings.update');
        Route::post('/settings/approvals/{key}', 'approval')->name('settings.approval');
    });

    Route::middleware('permission:backups.manage')->controller(Admin\BackupController::class)->group(function () {
        Route::get('/backups', 'index')->name('backups.index');
        Route::post('/backups', 'store')->name('backups.store');
        Route::get('/backups/{run}/download', 'download')->name('backups.download');
    });
});
