<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Document;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\Leader;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Post;
use App\Services\UploadService;
use App\Support\Audit;
use App\Support\Content;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Website content: versioned pages plus draft/publish collections.
 * Editing needs content.manage; publishing, unpublishing and archiving need
 * content.publish. Content cannot touch finance or security settings.
 */
class ContentController extends Controller
{
    /** @return array<string, array<string, mixed>> */
    private function types(): array
    {
        return [
            'news' => ['label' => 'News', 'singular' => 'news article', 'model' => Post::class, 'scope' => ['type' => 'news', 'audience' => 'public'], 'title' => 'title', 'fields' => [
                ['title', 'Title', 'text', true], ['excerpt', 'Summary (shown in lists)', 'textarea', false], ['body', 'Article', 'markdown', true], ['cover_media_id', 'Cover image', 'media', false],
            ]],
            'announcements' => ['label' => 'Member notices', 'singular' => 'notice', 'model' => Post::class, 'scope' => ['type' => 'announcement', 'audience' => 'members'], 'title' => 'title', 'fields' => [
                ['title', 'Title', 'text', true], ['excerpt', 'Summary', 'textarea', false], ['body', 'Notice', 'markdown', true],
            ]],
            'activities' => ['label' => 'Activities', 'singular' => 'activity', 'model' => Activity::class, 'scope' => [], 'title' => 'title', 'fields' => [
                ['title', 'Title', 'text', true], ['category', 'Category', 'text', false], ['summary', 'Summary', 'textarea', true], ['body', 'Description', 'markdown', false], ['media_id', 'Image', 'media', false], ['sort_order', 'Display order', 'number', false],
            ]],
            'leaders' => ['label' => 'Leadership', 'singular' => 'leadership entry', 'model' => Leader::class, 'scope' => [], 'title' => 'name', 'fields' => [
                ['name', 'Name (as approved for publication)', 'text', true], ['office', 'Office or title', 'text', true],
                ['group', 'Group', 'select', true, ['executive' => 'Executive', 'council' => 'Council', 'chiefs' => 'Chiefs', 'other' => 'Other']],
                ['term_label', 'Term (e.g. 2025–2027)', 'text', false], ['bio', 'Short biography', 'textarea', false], ['photo_media_id', 'Approved photograph', 'media', false], ['sort_order', 'Display order', 'number', false],
            ]],
            'galleries' => ['label' => 'Galleries', 'singular' => 'gallery', 'model' => Gallery::class, 'scope' => [], 'title' => 'title', 'fields' => [
                ['title', 'Title', 'text', true], ['description', 'Description', 'textarea', false],
            ]],
            'documents' => ['label' => 'Member documents', 'singular' => 'document', 'model' => Document::class, 'scope' => [], 'title' => 'title', 'fields' => [
                ['title', 'Title', 'text', true], ['description', 'Description', 'textarea', false], ['private_file_id', 'File (PDF, JPEG or PNG)', 'private_file', true],
            ]],
        ];
    }

    private function type(string $type): array
    {
        return $this->types()[$type] ?? abort(404);
    }

    public function index(): View
    {
        $pages = collect(Content::PAGES)->map(fn ($label, $key) => Page::firstOrCreate(['key' => $key], ['label' => $label])->load('publishedRevision', 'latestRevision'));

        return view('admin.content.index', ['pages' => $pages, 'types' => $this->types()]);
    }

    public function editPage(Page $page): View
    {
        return view('admin.content.page', ['page' => $page->load('publishedRevision'), 'revisions' => $page->revisions()->with('author')->limit(20)->get()]);
    }

    public function saveRevision(Request $request, Page $page): RedirectResponse
    {
        $this->can($request, 'content.manage');
        $data = $request->validate(['title' => ['required', 'string', 'max:190'], 'summary' => ['nullable', 'string', 'max:500'], 'body' => ['required', 'string', 'max:60000'], 'change_note' => ['nullable', 'string', 'max:255']]);
        $revision = PageRevision::create($data + ['page_id' => $page->id, 'created_by' => $request->user()->id]);
        Audit::record('page.revision_saved', "Draft revision saved for {$page->key}", $page, ['revision' => $revision->id]);

        return redirect()->route('admin.content.pages.edit', $page)->with('status', 'Draft saved. It is not public until a publisher publishes it.');
    }

    public function publishRevision(Request $request, Page $page, PageRevision $revision): RedirectResponse
    {
        $this->can($request, 'content.publish');
        abort_unless($revision->page_id === $page->id, 404);
        $page->update(['published_revision_id' => $revision->id, 'published_at' => now(), 'published_by' => $request->user()->id]);
        Audit::record('page.published', "Page {$page->key} published (revision {$revision->id})", $page);

        return redirect()->route('admin.content.pages.edit', $page)->with('status', 'Published.');
    }

    public function unpublishPage(Request $request, Page $page): RedirectResponse
    {
        $this->can($request, 'content.publish');
        $page->update(['published_revision_id' => null, 'published_at' => null, 'published_by' => null]);
        Audit::record('page.unpublished', "Page {$page->key} unpublished", $page);

        return redirect()->route('admin.content.pages.edit', $page)->with('status', 'Unpublished.');
    }

    public function list(string $type): View
    {
        $spec = $this->type($type);
        $records = $spec['model']::query()->where($spec['scope'])->latest('updated_at')->paginate(30);

        return view('admin.content.list', ['type' => $type, 'spec' => $spec, 'records' => $records]);
    }

    public function create(string $type): View
    {
        return view('admin.content.form', ['type' => $type, 'spec' => $this->type($type), 'record' => null, 'media' => Media::latest()->limit(60)->get()]);
    }

    public function store(Request $request, string $type, UploadService $uploads): RedirectResponse
    {
        $this->can($request, 'content.manage');
        $spec = $this->type($type);
        $data = $this->validated($request, $spec, $uploads, null);
        /** @var Model $record */
        $record = new $spec['model'];
        $record->fill($data + $spec['scope'] + ['status' => 'draft']);
        $this->stamp($record, $request, true);
        if (in_array($type, ['news', 'announcements', 'activities', 'galleries'], true)) {
            $record->slug = $this->slug($spec['model'], $data[$spec['title']]);
        }
        $record->save();
        Audit::record('content.created', ucfirst($spec['singular']).' drafted: '.$data[$spec['title']], $record);

        return redirect()->route('admin.content.edit', [$type, $record->id])->with('status', 'Draft saved. It is not public until published.');
    }

    public function edit(string $type, int $id): View
    {
        $spec = $this->type($type);
        $record = $spec['model']::query()->where($spec['scope'])->findOrFail($id);

        return view('admin.content.form', ['type' => $type, 'spec' => $spec, 'record' => $record, 'media' => Media::latest()->limit(60)->get()]);
    }

    public function update(Request $request, string $type, int $id, UploadService $uploads): RedirectResponse
    {
        $this->can($request, 'content.manage');
        $spec = $this->type($type);
        $record = $spec['model']::query()->where($spec['scope'])->findOrFail($id);
        $record->fill($this->validated($request, $spec, $uploads, $record));
        $this->stamp($record, $request, false);
        $record->save();
        Audit::record('content.updated', ucfirst($spec['singular']).' updated: '.$record->{$spec['title']}, $record, ['changes' => array_keys($record->getChanges())]);

        return redirect()->route('admin.content.edit', [$type, $id])->with('status', 'Saved.');
    }

    public function status(Request $request, string $type, int $id): RedirectResponse
    {
        $this->can($request, 'content.publish');
        $spec = $this->type($type);
        $record = $spec['model']::query()->where($spec['scope'])->findOrFail($id);
        $data = $request->validate(['status' => ['required', 'in:draft,published,archived']]);
        $record->status = $data['status'];
        if ($record instanceof Post && $data['status'] === 'published' && ! $record->published_at) {
            $record->published_at = now();
        }
        $record->save();
        Audit::record('content.status_changed', ucfirst($spec['singular'])." {$data['status']}: ".$record->{$spec['title']}, $record);

        return back()->with('status', 'Status changed to '.$data['status'].'.');
    }

    public function addGalleryItem(Request $request, int $id, UploadService $uploads): RedirectResponse
    {
        $this->can($request, 'content.manage');
        $gallery = Gallery::findOrFail($id);
        $data = $request->validate(['image' => ['required', 'file', 'max:'.config('boba.uploads.image_max_kb')], 'alt_text' => ['required', 'string', 'max:255'], 'caption' => ['nullable', 'string', 'max:255']]);
        $media = $uploads->storePublicImage($request->file('image'), $data['alt_text'], $request->user());
        GalleryItem::create(['gallery_id' => $gallery->id, 'media_id' => $media->id, 'caption' => $data['caption'] ?? null, 'sort_order' => $gallery->items()->count()]);
        Audit::record('gallery.item_added', "Image added to gallery {$gallery->title}", $gallery);

        return back()->with('status', 'Image added.');
    }

    public function media(): View
    {
        return view('admin.content.media', ['media' => Media::latest()->paginate(40)]);
    }

    public function uploadMedia(Request $request, UploadService $uploads): RedirectResponse
    {
        $this->can($request, 'content.manage');
        $data = $request->validate(['image' => ['required', 'file', 'max:'.config('boba.uploads.image_max_kb')], 'alt_text' => ['required', 'string', 'max:255'], 'rights_confirmed' => ['accepted']], ['rights_confirmed.accepted' => 'Confirm that the association has permission to publish this image.']);
        $uploads->storePublicImage($request->file('image'), $data['alt_text'], $request->user());

        return redirect()->route('admin.content.media')->with('status', 'Image uploaded.');
    }

    private function validated(Request $request, array $spec, UploadService $uploads, ?Model $record): array
    {
        $rules = [];
        foreach ($spec['fields'] as $field) {
            [$name, , $kind, $required] = $field;
            $rules[$name] = match ($kind) {
                'text' => [$required ? 'required' : 'nullable', 'string', 'max:190'],
                'textarea' => [$required ? 'required' : 'nullable', 'string', 'max:2000'],
                'markdown' => [$required ? 'required' : 'nullable', 'string', 'max:60000'],
                'number' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
                'select' => ['required', 'in:'.implode(',', array_keys($field[4]))],
                'media' => ['nullable', 'integer', 'exists:media,id'],
                'private_file' => [$record ? 'nullable' : 'required', 'file', 'max:'.config('boba.uploads.document_max_kb')],
            };
        }
        $data = $request->validate($rules);
        foreach ($spec['fields'] as $field) {
            if ($field[2] === 'private_file') {
                if ($request->hasFile($field[0])) {
                    $data[$field[0]] = $uploads->storePrivate($request->file($field[0]), 'member_document', $request->user())->id;
                } else {
                    unset($data[$field[0]]);
                }
            }
            if ($field[2] === 'number') {
                $data[$field[0]] = (int) ($data[$field[0]] ?? 0);
            }
        }

        return $data;
    }

    private function stamp(Model $record, Request $request, bool $creating): void
    {
        $columns = $record->getConnection()->getSchemaBuilder()->getColumnListing($record->getTable());
        if ($creating && in_array('created_by', $columns, true)) {
            $record->created_by = $request->user()->id;
        }
        if (in_array('updated_by', $columns, true)) {
            $record->updated_by = $request->user()->id;
        }
    }

    private function slug(string $model, string $title): string
    {
        $base = Str::slug($title) ?: 'item';
        $slug = $base;
        $i = 2;
        while ($model::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function can(Request $request, string $permission): void
    {
        if (! $request->user()->hasPermission($permission)) {
            throw new DomainRuleException($permission === 'content.publish' ? 'You can prepare content, but publishing needs the publishing permission.' : 'You do not have permission to edit content.');
        }
    }
}
