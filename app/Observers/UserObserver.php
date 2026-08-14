<?php

namespace App\Observers;

use App\Models\User;
use App\Services\ActivityLogger;

class UserObserver
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function created(User $user): void
    {
        $this->logger->log('user.created', $user, ['email' => $user->email]);
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged()) {
            $this->logger->log('user.updated', $user, ['changed' => array_keys($user->getChanges())]);
        }
    }

    public function deleted(User $user): void
    {
        $this->logger->log('user.deleted', $user, ['email' => $user->email]);
    }
}
