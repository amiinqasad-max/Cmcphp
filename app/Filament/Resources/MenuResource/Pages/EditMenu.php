<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Enums\LinkTarget;
use App\Enums\MenuItemType;
use App\Filament\Resources\MenuResource;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Services\ActivityLogger;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Saade\FilamentAdjacencyList\Forms\Components\AdjacencyList;

/**
 * The nested/reorderable menu-item editor (§1, §8). AdjacencyList (from
 * saade/filament-adjacency-list) manages `items_tree` as a plain nested
 * array — deliberately *not* bound via its own ->relationship() mode,
 * which would require the MenuItem model to adopt
 * staudenmeir/laravel-adjacency-list's recursive-CTE trait just for this
 * one editor. Instead this page owns the translation itself: load the DB
 * tree into array shape in mutateFormDataBeforeFill(), and flatten the
 * edited array back into menu_items rows in afterSave() — full control
 * over validation (cycle prevention already happened in MenuItem, though
 * a tree UI structurally can't produce a cycle in the first place) and no
 * extra dependency.
 */
class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    /** Captured in mutateFormDataBeforeSave(), consumed in afterSave() — see the class docblock. */
    private array $pendingItemsTree = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            ...MenuResource::form($form)->getComponents(),

            Forms\Components\Section::make('Menu items')
                ->description('Drag to reorder or nest. Add a top-level item, or use a child item\'s menu to add nested items beneath it.')
                ->schema([
                    AdjacencyList::make('items_tree')
                        ->hiddenLabel()
                        ->labelKey('label')
                        ->childrenKey('children')
                        ->maxDepth(6)
                        ->form([
                            Forms\Components\Select::make('type')
                                ->label('Type')
                                ->options(collect(MenuItemType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                                ->default(MenuItemType::Custom->value)
                                ->live()
                                ->required(),
                            Forms\Components\TextInput::make('label')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\Select::make('linkable_id')
                                ->label(fn (Get $get) => MenuItemType::tryFrom((string) $get('type'))?->label() ?? 'Content')
                                ->visible(fn (Get $get) => MenuItemType::tryFrom((string) $get('type'))?->isInternal() ?? false)
                                ->required(fn (Get $get) => MenuItemType::tryFrom((string) $get('type'))?->isInternal() ?? false)
                                ->searchable()
                                ->options(fn (Get $get) => match (MenuItemType::tryFrom((string) $get('type'))) {
                                    MenuItemType::Page => Page::query()->orderBy('title')->pluck('title', 'id'),
                                    MenuItemType::Post => Post::query()->orderBy('title')->limit(200)->pluck('title', 'id'),
                                    MenuItemType::Category => Category::query()->orderBy('name')->pluck('name', 'id'),
                                    default => [],
                                }),
                            Forms\Components\TextInput::make('url')
                                ->label('URL')
                                ->placeholder('/some-path or https://example.com')
                                ->visible(fn (Get $get) => MenuItemType::tryFrom((string) $get('type')) === MenuItemType::Custom)
                                ->required(fn (Get $get) => MenuItemType::tryFrom((string) $get('type')) === MenuItemType::Custom)
                                ->maxLength(2048)
                                ->rules([
                                    fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                        if (filled($value) && ! MenuItem::isSafeUrl((string) $value)) {
                                            $fail('Enter an internal path starting with "/" or a full http(s):// URL — other URL schemes (e.g. "javascript:") are not allowed.');
                                        }
                                    },
                                ]),
                            Forms\Components\Select::make('target')
                                ->options(collect(LinkTarget::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                                ->default(LinkTarget::SameWindow->value)
                                ->required(),
                            Forms\Components\TextInput::make('rel')
                                ->helperText('Optional, e.g. "nofollow" or "noopener noreferrer".')
                                ->maxLength(255),
                            Forms\Components\Toggle::make('is_visible')
                                ->label('Visible')
                                ->default(true),
                        ]),
                ]),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['items_tree'] = $this->treeToArray($this->getRecord()->fullTree());

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingItemsTree = $data['items_tree'] ?? [];
        unset($data['items_tree']);

        return $data;
    }

    protected function afterSave(): void
    {
        $menu = $this->getRecord();
        $keptIds = [];

        DB::transaction(function () use ($menu, &$keptIds) {
            $this->persistTree($menu, $this->pendingItemsTree, null, $keptIds);

            app(ActivityLogger::class)->log('menu.items_updated', $menu, ['items_count' => count($keptIds)]);

            $menu->items()
                ->when($keptIds, fn ($query) => $query->whereNotIn('id', $keptIds), fn ($query) => $query)
                ->delete();
        });
    }

    /** @param  array<string, array<string, mixed>>  $tree */
    private function persistTree(Menu $menu, array $tree, ?string $parentId, array &$keptIds): void
    {
        $position = 0;

        foreach ($tree as $key => $node) {
            $type = MenuItemType::tryFrom((string) ($node['type'] ?? '')) ?? MenuItemType::Custom;
            $isInternal = $type->isInternal();

            MenuItem::updateOrCreate(['id' => $key], [
                'menu_id' => $menu->id,
                'parent_id' => $parentId,
                'type' => $type->value,
                'linkable_type' => $isInternal && filled($node['linkable_id'] ?? null) ? $type->modelClass() : null,
                'linkable_id' => $isInternal ? ($node['linkable_id'] ?? null) : null,
                'label' => $node['label'] ?? 'Untitled',
                'url' => $type === MenuItemType::Custom ? ($node['url'] ?? null) : null,
                'target' => $node['target'] ?? '_self',
                'rel' => $node['rel'] ?? null,
                'is_visible' => (bool) ($node['is_visible'] ?? true),
                'position' => $position++,
            ]);

            $keptIds[] = $key;

            if (! empty($node['children'])) {
                $this->persistTree($menu, $node['children'], $key, $keptIds);
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function treeToArray(Collection $items): array
    {
        return $items->mapWithKeys(fn (MenuItem $item) => [
            $item->id => [
                'type' => $item->type->value,
                'label' => $item->label,
                'linkable_id' => $item->linkable_id,
                'url' => $item->url,
                'target' => $item->target->value,
                'rel' => $item->rel,
                'is_visible' => $item->is_visible,
                'children' => $this->treeToArray($item->children instanceof Collection ? $item->children : collect()),
            ],
        ])->all();
    }
}
