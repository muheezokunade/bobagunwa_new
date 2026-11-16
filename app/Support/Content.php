<?php

namespace App\Support;

use App\Models\Page;
use App\Models\PageRevision;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Managed page content. Bodies are written in Markdown; raw HTML is stripped
 * and unsafe links are removed, so officers cannot inject script.
 */
final class Content
{
    /** Pages officers can manage, with their purpose. */
    public const PAGES = [
        'about' => 'About the association',
        'history' => 'History and heritage',
        'membership' => 'Membership responsibilities and guidance',
        'privacy' => 'Privacy notice',
        'terms' => 'Website and portal terms',
    ];

    public static function published(string $key): ?PageRevision
    {
        $page = Page::with('publishedRevision')->where('key', $key)->first();

        return $page?->publishedRevision;
    }

    public static function markdown(?string $markdown): HtmlString
    {
        return new HtmlString(Str::markdown((string) $markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]));
    }
}
