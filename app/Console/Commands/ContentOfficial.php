<?php

namespace App\Console\Commands;

use App\Models\Leader;
use App\Models\Page;
use App\Models\PageRevision;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads the website content supplied by the association
 * (database/content/official-2026-10.php): pages as new revisions, so every
 * change stays in the page history, and leadership entries. Re-running it
 * changes nothing that is already identical. Pages marked 'publish' => false
 * stay unpublished drafts even with --publish. Members are never loaded.
 */
class ContentOfficial extends Command
{
    protected $signature = 'boba:content:official {--publish : Publish the pages and leadership entries the association approved for publication} {--file= : Content file (default database/content/official-2026-10.php)}';

    protected $description = 'Load the association-supplied website content (pages and leadership)';

    public function handle(): int
    {
        $file = $this->option('file') ?: database_path('content/official-2026-10.php');
        $content = require $file;
        $publish = (bool) $this->option('publish');

        DB::transaction(function () use ($content, $publish) {
            foreach ($content['pages'] as $key => $data) {
                $page = Page::firstOrCreate(['key' => $key], ['label' => ucfirst($key)]);
                $latest = $page->revisions()->latest('id')->first();
                $same = $latest && $latest->title === $data['title'] && $latest->summary === $data['summary'] && $latest->body === $data['body'];
                $revision = $same ? $latest : PageRevision::create([
                    'page_id' => $page->id, 'title' => $data['title'], 'summary' => $data['summary'], 'body' => $data['body'],
                    'change_note' => mb_substr('Association-supplied content: '.$content['source'], 0, 255),
                ]);
                $this->line(sprintf('%-8s %s', $key, $same ? 'unchanged' : "revision {$revision->id} saved"));
                if ($publish && $data['publish'] && $page->published_revision_id !== $revision->id) {
                    $page->update(['published_revision_id' => $revision->id, 'published_at' => now(), 'published_by' => null]);
                    Audit::record('page.published', "Page {$key} published from association-supplied content (revision {$revision->id})", $page);
                    $this->info('         published');
                } elseif ($publish && ! $data['publish']) {
                    $this->warn('         kept as an unpublished draft (needs the association to confirm the facts first)');
                }
            }

            $order = ['council' => 0, 'chiefs' => 0, 'executive' => 0];
            foreach ($content['leaders'] as [$group, $name, $office]) {
                $order[$group] += 10;
                $leader = Leader::firstOrNew(['group' => $group, 'office' => $office]);
                $leader->fill([
                    'name' => $name,
                    'term_label' => $group === 'executive' ? $content['executive_term'] : $leader->term_label,
                    'sort_order' => $order[$group],
                    'status' => $publish ? 'published' : ($leader->exists ? $leader->status : 'draft'),
                ]);
                if ($leader->isDirty()) {
                    $created = ! $leader->exists;
                    $leader->save();
                    Audit::record($created ? 'leader.created' : 'leader.updated', "Leadership entry {$office}: {$name} (association-supplied)", $leader);
                }
            }
            $this->line(count($content['leaders']).' leadership entries loaded'.($publish ? ' and published' : ' (drafts unless already published)').'.');
            if (! empty($content['vacant_titles'])) {
                $this->line('Titles without a named holder (not loaded): '.implode(', ', $content['vacant_titles']).'.');
            }
            Audit::record('content.official_loaded', 'Association-supplied website content loaded'.($publish ? ' and published' : ''), null, ['source' => $content['source']]);
        });

        return self::SUCCESS;
    }
}
