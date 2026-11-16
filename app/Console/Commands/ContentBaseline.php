<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\PageRevision;
use App\Support\Audit;
use Illuminate\Console\Command;

/**
 * Loads the copy already shown on the association's existing public website
 * as UNPUBLISHED draft revisions, so officers can review, correct and publish
 * it. Never publishes anything and never overwrites existing revisions.
 */
class ContentBaseline extends Command
{
    protected $signature = 'boba:content:baseline';

    protected $description = 'Create unpublished draft page revisions from the existing public website copy';

    public function handle(): int
    {
        $drafts = [
            'about' => ['About Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu', 'A fellowship of Ijebu men who believe that heritage is strongest when it is lived, shared and carried forward with honour.', <<<'MD'
Ẹgbẹ́ Bobagunwa Okunrin Akile Ijebu brings together Ijebu men who believe that heritage is strongest when it is lived, shared and carried forward with honour.

Through fellowship, responsible leadership and community service, the association creates enduring bonds between its members while contributing to the progress of Ijebuland.

## What we stand for

- Respect for Ijebu heritage
- Responsible leadership
- Unity across generations
- Community development
- Integrity and honour
- Service to our people

## Honouring the past, building the future

Our history gives us roots. Our brotherhood gives us strength. Our service ensures that the values we inherited continue to matter.
MD],
            'membership' => ['Member responsibilities', null, <<<'MD'
Members are expected to take part in the life of the association, to keep their contact details up to date and to meet the dues and levies the association approves.

The official list of member responsibilities is awaiting the association's approval.
MD],
        ];
        foreach ($drafts as $key => [$title, $summary, $body]) {
            $page = Page::where('key', $key)->firstOrFail();
            if ($page->revisions()->exists()) {
                $this->line("{$key}: already has revisions, skipped.");

                continue;
            }
            PageRevision::create(['page_id' => $page->id, 'title' => $title, 'summary' => $summary, 'body' => $body, 'change_note' => 'Carried over from the existing public website; requires approval before publishing']);
            $this->info("{$key}: draft created (not published).");
        }
        foreach (['privacy', 'terms'] as $key) {
            $page = Page::where('key', $key)->firstOrFail();
            if (! $page->revisions()->exists()) {
                PageRevision::create(['page_id' => $page->id, 'title' => $key === 'privacy' ? 'Privacy notice' : 'Terms of use', 'body' => view('public.legal-'.$key)->render(), 'change_note' => 'Working draft for legal review']);
                $this->info("{$key}: draft created (not published).");
            }
        }
        Audit::record('content.baseline_loaded', 'Existing public copy loaded as unpublished drafts');

        return self::SUCCESS;
    }
}
