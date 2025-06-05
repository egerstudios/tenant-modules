<?php

namespace Egerstudios\TenantModules\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NavigationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tenantId;
    public $navigation;

    public function __construct(string $tenantId, array $navigation)
    {
        $this->tenantId = $tenantId;
        $this->navigation = $navigation;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("tenant.{$this->tenantId}.navigation")
        ];
    }

    public function broadcastAs(): string
    {
        return 'navigation.updated';
    }
} 