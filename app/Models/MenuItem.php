<?php

namespace App\Models;

use App\Enums\LinkTarget;
use App\Enums\MenuItemType;
use App\Enums\PageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MenuItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'menu_id',
        'parent_id',
        'type',
        'linkable_type',
        'linkable_id',
        'label',
        'url',
        'target',
        'rel',
        'is_visible',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'type' => MenuItemType::class,
            'target' => LinkTarget::class,
            'is_visible' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::tags([Menu::CACHE_TAG])->flush());
        static::deleted(fn () => Cache::tags([Menu::CACHE_TAG])->flush());
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The href to render. Custom items use `url` verbatim; internal items
     * resolve their linkable's public route. Returns null when there's
     * nothing safe to link to (see isBroken()) — callers should skip
     * rendering the item entirely rather than emit a dead link.
     */
    public function resolvedUrl(): ?string
    {
        if ($this->type === MenuItemType::Custom) {
            return $this->url;
        }

        if ($this->isBroken()) {
            return null;
        }

        return match ($this->type) {
            MenuItemType::Page => route('pages.show', $this->linkable),
            MenuItemType::Post => route('articles.show', $this->linkable),
            MenuItemType::Category => route('categories.show', $this->linkable),
            MenuItemType::Custom => $this->url,
        };
    }

    /**
     * True when an internal reference no longer resolves to safe, public
     * content — the linked record was deleted, or (for posts/pages) is no
     * longer published. §1 "prevent broken internal references where
     * possible": we can't stop a referenced post being unpublished later,
     * so we instead guarantee it's never rendered while in that state.
     */
    public function isBroken(): bool
    {
        if ($this->type === MenuItemType::Custom) {
            return blank($this->url);
        }

        $linkable = $this->linkable;

        if (! $linkable) {
            return true;
        }

        return match (true) {
            $linkable instanceof Post => ! $linkable->isPublished(),
            $linkable instanceof Page => $linkable->status !== PageStatus::Published,
            default => false, // Category — existing is enough
        };
    }

    /**
     * Walk up the parent chain starting at $parentId (a *prospective*
     * parent for $itemId, which may not be persisted yet) and return true
     * if $itemId is found — i.e. assigning that parent would create a
     * cycle. Also rejects the trivial self-parent case.
     */
    public static function wouldCreateCycle(?string $itemId, ?string $prospectiveParentId, ?string $menuId = null): bool
    {
        if ($prospectiveParentId === null) {
            return false;
        }

        if ($itemId !== null && $prospectiveParentId === $itemId) {
            return true;
        }

        $parents = static::query()
            ->when($menuId, fn ($q) => $q->where('menu_id', $menuId))
            ->get(['id', 'parent_id'])
            ->keyBy('id');

        $current = $parents->get($prospectiveParentId);
        $guard = 0;

        while ($current !== null && $guard < 1000) {
            if ($current->id === $itemId) {
                return true;
            }

            $current = $current->parent_id ? $parents->get($current->parent_id) : null;
            $guard++;
        }

        return false;
    }

    /**
     * Build a nested tree (each node gains a `children` Collection) from a
     * flat collection of items, in PHP — one query in, no N+1 recursion.
     */
    public static function buildTree(Collection $flat, ?string $parentId): Collection
    {
        return $flat
            ->where('parent_id', $parentId)
            ->sortBy('position')
            ->values()
            ->map(function (self $item) use ($flat) {
                $item->setRelation('children', static::buildTree($flat, $item->id));

                return $item;
            });
    }
}
