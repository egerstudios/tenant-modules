<?php

namespace Egerstudios\TenantModules\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Tenant;
use Egerstudios\TenantModules\Models\Module;

class ModuleEnabled extends ModuleStateChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Tenant $tenant,
        public string $moduleName,
        public array $moduleData = []
    ) {
        // Find or create the module
        $module = Module::firstOrCreate(
            ['name' => $moduleName],
            [
                'description' => "Module {$moduleName}",
                'version' => '1.0.0',
                'is_core' => false
            ]
        );

        // Initialize parent with the Module object
        parent::__construct($tenant, $module, $moduleData);
    }

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
     * Get the broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'module.enabled';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge(parent::broadcastWith(), [
            'action' => 'enabled',
        ]);
    }
} 