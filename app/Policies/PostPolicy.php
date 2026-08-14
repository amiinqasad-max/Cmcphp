<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PostPolicy
{
    use HandlesAuthorization;

    /** Roles that may manage any post, regardless of authorship. */
    private const ELEVATED_ROLES = ['super_admin', 'admin', 'editor'];

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_post');
    }

    public function view(User $user, Post $post): bool
    {
        return $user->can('view_post');
    }

    public function create(User $user): bool
    {
        return $user->can('create_post');
    }

    /**
     * Authors may only update their own posts; Editors/Admins/Super Admins
     * may update any post. See docs/ARCHITECTURE.md §30 (Users & Permissions).
     */
    public function update(User $user, Post $post): bool
    {
        return $user->can('update_post') && $this->ownsOrElevated($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->can('delete_post') && $this->ownsOrElevated($user, $post);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_post');
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->can('force_delete_post') && $this->ownsOrElevated($user, $post);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_post');
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->can('restore_post') && $this->ownsOrElevated($user, $post);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_post');
    }

    public function replicate(User $user, Post $post): bool
    {
        return $user->can('replicate_post') && $this->ownsOrElevated($user, $post);
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_post');
    }

    /**
     * Publishing is a distinct capability from editing: an Author may be
     * allowed to write and edit their own drafts without being trusted to
     * push them live.
     */
    public function publish(User $user, Post $post): bool
    {
        return $user->can('publish_post') && $this->ownsOrElevated($user, $post);
    }

    private function ownsOrElevated(User $user, Post $post): bool
    {
        return $user->hasAnyRole(self::ELEVATED_ROLES) || $post->author_id === $user->id;
    }
}
