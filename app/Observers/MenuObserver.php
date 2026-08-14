<?php

namespace App\Observers;

use App\Models\Menu;
use App\Services\ActivityLogger;

/**
 * Logs Menu-level field changes (rename/relocate/activate). Item-tree
 * edits (add/remove/reorder/nest) are logged separately, once per save,
 * by EditMenu::afterSave() as 'menu.items_updated' — not per-MenuItem row,
 * since a single drag-and-drop reorder can touch dozens of rows and a log
 * entry per row would be noise, not signal (see ActivityLogger's own
 * docblock on staying meaningful rather than exhaustive).
 */
class MenuObserver
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function created(Menu $menu): void
    {
        $this->logger->log('menu.created', $menu, ['name' => $menu->name, 'location' => $menu->location]);
    }

    public function updated(Menu $menu): void
    {
        if ($menu->wasChanged()) {
            $this->logger->log('menu.updated', $menu, [
                'name' => $menu->name,
                'changed' => array_keys($menu->getChanges()),
            ]);
        }
    }

    public function deleted(Menu $menu): void
    {
        $this->logger->log('menu.deleted', $menu, ['name' => $menu->name]);
    }
}
