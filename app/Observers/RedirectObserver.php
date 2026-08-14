<?php

namespace App\Observers;

use App\Models\Redirect;
use App\Services\ActivityLogger;

/**
 * Redirects control site-wide URL behavior for anonymous visitors, so a
 * change here is exactly the kind of admin action §33's audit trail exists
 * for (see ActivityLogger's own docblock). Deliberately not on every save
 * (incrementHit() uses withoutEvents() precisely so *using* a redirect
 * never fires this) — only real create/update/delete.
 */
class RedirectObserver
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function created(Redirect $redirect): void
    {
        $this->logger->log('redirect.created', $redirect, [
            'source_path' => $redirect->source_path,
            'destination' => $redirect->destination,
            'status_code' => $redirect->status_code->value,
        ]);
    }

    public function updated(Redirect $redirect): void
    {
        if ($redirect->wasChanged()) {
            $this->logger->log('redirect.updated', $redirect, [
                'source_path' => $redirect->source_path,
                'changed' => array_keys($redirect->getChanges()),
            ]);
        }
    }

    public function deleted(Redirect $redirect): void
    {
        $this->logger->log('redirect.deleted', $redirect, ['source_path' => $redirect->source_path]);
    }
}
