<?php

namespace Egerstudios\TenantModules\Events;

use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;

class ModuleDisabled extends ModuleStateChanged
{
    /**
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'module.disabled';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge(parent::broadcastWith(), [
            'action' => 'disabled',
        ]);
    }
} 