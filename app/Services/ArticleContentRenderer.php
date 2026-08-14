<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostVideo;
use Illuminate\Support\Collection;

/**
 * Splits an article's rendered HTML into an ordered sequence of top-level
 * blocks, resolving `[[VIDEO_1]]`/`[[VIDEO_2]]`/`[[VIDEO_3]]` marker tokens
 * (typed directly into the RichEditor content by the author — see §5/§6)
 * into their corresponding PostVideo. Ad placement (Phase 7) operates on
 * this same block sequence, inserting ad blocks between paragraphs.
 *
 * "Paragraph" for spacing-rule purposes (§23/§24) means a top-level <p>
 * block specifically — headings/lists/quotes/videos pass through the
 * sequence but aren't counted when an admin configures "after paragraph 3".
 */
class ArticleContentRenderer
{
    private const BLOCK_PATTERN = '/<(p|h[1-6]|ul|ol|blockquote|pre|table|figure)\b[^>]*>.*?<\/\1>|<hr\s*\/?>/is';

    private const VIDEO_MARKER_PATTERN = '/^\s*(?:<[^>]+>\s*)?\[\[VIDEO_(\d)\]\](?:\s*<\/[^>]+>)?\s*$/i';

    /**
     * @return Collection<int, array{type: 'content'|'video', html: ?string, video: ?PostVideo, isParagraph: bool}>
     */
    public function blocks(Post $post): Collection
    {
        $html = (string) $post->content;

        preg_match_all(self::BLOCK_PATTERN, $html, $matches);
        $rawBlocks = $matches[0] ?: array_filter([trim($html)]);

        $videosByPosition = $post->relationLoaded('videos')
            ? $post->videos->keyBy('position')
            : $post->videos()->get()->keyBy('position');

        return collect($rawBlocks)->map(fn (string $blockHtml) => $this->toBlock($blockHtml, $videosByPosition))->values();
    }

    private function toBlock(string $blockHtml, Collection $videosByPosition): array
    {
        if (preg_match(self::VIDEO_MARKER_PATTERN, $blockHtml, $m)) {
            $video = $videosByPosition->get((int) $m[1]);

            if ($video && $video->isActive()) {
                return ['type' => 'video', 'html' => null, 'video' => $video, 'isParagraph' => false];
            }

            // Unresolved marker (video removed/inactive) — drop it silently
            // rather than leaking "[[VIDEO_2]]" into the public page.
            return ['type' => 'content', 'html' => '', 'video' => null, 'isParagraph' => false];
        }

        return [
            'type' => 'content',
            'html' => $blockHtml,
            'video' => null,
            'isParagraph' => (bool) preg_match('/^<p\b/i', trim($blockHtml)),
        ];
    }
}
