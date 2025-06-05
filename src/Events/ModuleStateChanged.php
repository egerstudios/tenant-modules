<?php

namespace Egerstudios\TenantModules\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;

/**
 * ModuleStateChanged - Unified event for all module state changes
 * 
 * This single event handles both enable and disable operations,
 * reducing complexity and ensuring consistent behavior.
 */
class ModuleStateChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Tenant $tenant,
        public Module $module,
        public array $moduleData = []
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->tenant->id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'module' => [
                'name' => $this->module->name,
                'description' => $this->module->description,
                'version' => $this->module->version,
                'is_core' => $this->module->is_core,
            ],
            'tenant_id' => $this->tenant->id,
            'timestamp' => now()->toIso8601String(),
            'action' => $this->module->is_active ? 'enabled' : 'disabled'
        ];
    }

    /**
     * The event's broadcast name
     */
    public function broadcastAs(): string
    {
        return 'module-state-changed';
    }

    /**
     * Determine if this event should broadcast
     */
    public function broadcastWhen(): bool
    {
        \Log::debug('ModuleStateChanged broadcastWhen', [
            'module' => $this->module->name,
            'is_active' => $this->module->is_active
        ]);
        return true;
    }

    /**
     * Get the queue connection that should handle the broadcast
     */
    public function broadcastConnection(): ?string
    {
        $connection = config('broadcasting.default');
        \Log::debug('ModuleStateChanged broadcastConnection', [
            'connection' => $connection
        ]);
        return $connection;
    }

    /**
     * Get the queue that should handle the broadcast
     */
    public function broadcastQueue(): ?string
    {
        \Log::debug('ModuleStateChanged broadcastQueue', [
            'queue' => 'broadcasts'
        ]);
        return 'broadcasts';
    }

    /**
     * Helper methods for checking state
     */
    public function isEnabled(): bool
    {
        return $this->module->is_active;
    }

    public function isDisabled(): bool
    {
        return !$this->module->is_active;
    }

    public function getAction(): string
    {
        return $this->module->is_active ? 'enabled' : 'disabled';
    }
}