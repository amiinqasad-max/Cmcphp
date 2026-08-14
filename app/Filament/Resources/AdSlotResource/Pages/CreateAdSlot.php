<?php

namespace App\Filament\Resources\AdSlotResource\Pages;

use App\Filament\Resources\AdSlotResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdSlot extends CreateRecord
{
    protected static string $resource = AdSlotResource::class;

    /**
     * ad_client is no longer an admin-entered field (§14/§34 — "environment-
     * only AdSense credentials"; the rendered <ins> always reads
     * config('services.adsense.client_id') regardless of what's stored
     * here, see resources/views/components/ad-slot.blade.php). The column
     * itself stays NOT NULL for backward compatibility, so it's filled in
     * here from the same env value rather than exposing a redundant,
     * inconsistency-prone form field.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['ad_client'] = config('services.adsense.client_id') ?? '';

        return $data;
    }
}
