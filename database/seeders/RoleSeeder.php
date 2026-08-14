<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Base roles + permission matrix from docs/ARCHITECTURE.md §30 (Users & Permissions).
 *
 * - super_admin bypasses all policy checks (Filament Shield registers a
 *   Gate::before for it), so it needs no explicit grants here.
 * - admin gets every permission that exists at the time this seeder runs
 *   (including ones added by later phases, since it re-syncs from the DB).
 * - editor gets full content management (posts/pages/categories/tags/media/
 *   ad slots — "Manage ads", §30) but not user/role management. Ad
 *   *placements* are edited inline on the post form and are governed by
 *   the post's own update permission, so they don't need a separate grant.
 * - author gets create/update on their own posts only (enforced by
 *   PostPolicy's ownership check, not by the permission itself) plus
 *   read access to the media/category/tag pickers used while writing.
 *
 * Re-run safely any time new Filament resources add permissions
 * (`php artisan shield:generate --all` then `php artisan db:seed --class=RoleSeeder`).
 */
class RoleSeeder extends Seeder
{
    // Note: Filament Shield names multi-word resources with "::" (e.g. "ad::slot"
    // for the AdSlot model), not snake_case — verified against the actual
    // generated permission rows rather than assumed.
    private const CONTENT_RESOURCES = ['post', 'page', 'category', 'tag', 'media', 'ad::slot'];

    private const EDITOR_ABILITIES = ['view_any', 'view', 'create', 'update', 'delete', 'replicate', 'restore', 'restore_any'];

    public function run(): void
    {
        foreach (['super_admin', 'admin', 'editor', 'author'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Custom ability beyond Shield's generated CRUD set — see PostPolicy::publish().
        Permission::findOrCreate('publish_post', 'web');

        Role::findByName('admin')->syncPermissions(Permission::all());
        Role::findByName('editor')->syncPermissions($this->editorPermissionNames());
        Role::findByName('author')->syncPermissions($this->authorPermissionNames());
    }

    private function editorPermissionNames(): Collection
    {
        $names = collect(self::CONTENT_RESOURCES)
            ->crossJoin(self::EDITOR_ABILITIES)
            ->map(fn ($pair) => "{$pair[1]}_{$pair[0]}")
            ->push('publish_post');

        return Permission::whereIn('name', $names)->get();
    }

    private function authorPermissionNames(): Collection
    {
        $names = [
            'view_any_post', 'view_post', 'create_post', 'update_post', 'replicate_post',
            'view_any_media', 'view_media', 'create_media',
            'view_any_category', 'view_category',
            'view_any_tag', 'view_tag',
        ];

        return Permission::whereIn('name', $names)->get();
    }
}
