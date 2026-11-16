<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\FeeCategory;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.finance.categories', ['categories' => FeeCategory::orderBy('kind')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $category = FeeCategory::create($data + ['created_by' => $request->user()->id, 'is_active' => true]);
        Audit::record('category.created', "Category {$category->code} created", $category);

        return redirect()->route('admin.finance.categories.index')->with('status', 'Category created.');
    }

    public function update(Request $request, FeeCategory $category): RedirectResponse
    {
        $data = $this->validated($request, $category) + ['is_active' => $request->boolean('is_active')];
        if ((int) $request->input('version') !== $category->version) {
            throw new DomainRuleException('This category was changed by someone else. Reload and try again.');
        }
        $category->update($data + ['version' => $category->version + 1]);
        Audit::record('category.updated', "Category {$category->code} updated", $category, ['changes' => $category->getChanges()]);

        return redirect()->route('admin.finance.categories.index')->with('status', 'Category updated.');
    }

    private function validated(Request $request, ?FeeCategory $category = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('fee_categories', 'code')->ignore($category?->id)],
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:dues,levy,other'],
            'description' => ['nullable', 'string', 'max:255'],
            'default_amount' => ['nullable', 'string', 'max:20'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['default_amount_kobo'] = ($data['default_amount'] ?? '') !== '' ? Money::parse($data['default_amount']) : null;
        unset($data['default_amount']);

        return $data;
    }
}
