<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MembershipNumberService;
use App\Services\TwoFactorService;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(MembershipNumberService $numbers): View
    {
        return view('admin.settings', [
            'definitions' => collect(Settings::definitions())->groupBy('group', true),
            'values' => collect(Settings::definitions())->mapWithKeys(fn ($d, $k) => [$k => Settings::get($k)]),
            'nextNumber' => $numbers->preview(),
            'environment' => app()->environment(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (Settings::definitions() as $key => $def) {
            $field = str_replace('.', '__', $key);
            $rules[$field] = match ($def['type']) {
                'bool' => ['nullable', 'boolean'],
                'int' => ['required', 'integer', 'min:3', 'max:10'],
                'email' => ['nullable', 'email', 'max:190'],
                'string' => ['nullable', 'string', 'max:255'],
                'approval', 'internal' => [],
            };
        }
        $data = $request->validate(array_filter($rules));
        $changed = [];
        foreach (Settings::definitions() as $key => $def) {
            if (in_array($def['type'], ['approval', 'internal'], true)) {
                continue;
            }
            $field = str_replace('.', '__', $key);
            $value = match ($def['type']) {
                'bool' => $request->boolean($field),
                'int' => (int) $data[$field],
                default => ($data[$field] ?? null) !== null ? trim((string) $data[$field]) : null,
            };
            if ($key === 'membership.number_prefix' && $value !== null && ! preg_match('/^[A-Z0-9\/-]{0,12}$/', $value)) {
                return back()->withInput()->withErrors(['membership__number_prefix' => 'Use up to 12 capital letters, digits, “-” or “/”.']);
            }
            if ($key === 'security.require_two_factor' && $value === true && Settings::get($key) !== true) {
                $missing = TwoFactorService::privilegedOfficersWithoutTwoFactor();
                if ($missing->isNotEmpty()) {
                    return back()->withInput()->withErrors(['security__require_two_factor' => 'Two-factor sign-in cannot be required yet: these privileged officers have not enrolled: '.$missing->pluck('name')->implode(', ').'. Enforcing it now would lock them out.']);
                }
            }
            if (Settings::get($key) !== $value) {
                $changed[$key] = ['from' => Settings::get($key), 'to' => $value];
                Settings::set($key, $value, $request->user()->id);
            }
        }
        if ($changed) {
            Audit::record('settings.updated', 'Organisation settings updated', null, ['changes' => $changed]);
        }

        return redirect()->route('admin.settings.index')->with('status', $changed ? 'Settings saved.' : 'No changes.');
    }

    public function approval(Request $request, string $key): RedirectResponse
    {
        abort_unless(str_starts_with($key, 'approval.') && isset(Settings::definitions()[$key]), 404);
        $data = $request->validate(['approved' => ['required', 'boolean'], 'reference' => ['required_if:approved,1', 'nullable', 'string', 'max:190']]);
        $value = $data['approved'] ? ['approved' => true, 'reference' => $data['reference'], 'recorded_by' => $request->user()->id, 'recorded_at' => now()->toIso8601String()] : null;
        Settings::set($key, $value, $request->user()->id);
        Audit::record('settings.approval_recorded', ($data['approved'] ? 'Approval recorded: ' : 'Approval withdrawn: ').Settings::definitions()[$key]['label'], null, ['key' => $key, 'reference' => $data['reference'] ?? null]);

        return redirect()->route('admin.settings.index')->with('status', 'Launch approval updated.');
    }
}
