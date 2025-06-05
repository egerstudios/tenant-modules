<?php

namespace Egerstudios\TenantModules\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Egerstudios\TenantModules\Events\ModuleStateChanged;

class ModuleNavigation extends Component
{
    public array $navigation = [];

    public function mount()
    {
        Log::debug('ModuleNavigation component mounted', [
            'tenant_id' => tenant()->id,
            'component_id' => $this->getId()
        ]);
        $this->loadNavigation();
    }

    public function boot()
    {
        Log::debug('ModuleNavigation component booting', [
            'tenant_id' => tenant()->id,
            'component_id' => $this->getId()
        ]);
    }

    public function loadNavigation()
    {
        $this->navigation = app('navigation')->getFlattenedNavigationItems();
    }

    public function getListeners()
    {
        $channel = 'echo-private:tenant.' . tenant()->id;
        Log::debug('Registering Livewire listeners', [
            'channel' => $channel,
            'component_id' => $this->getId()
        ]);
        return [
            $channel . ',.module-state-changed' => 'handleModuleStateChanged',
        ];
    }

    public function handleModuleStateChanged($event)
    {
        Log::debug('Module state changed event received in Livewire component', [
            'raw_event' => $event,
            'event_type' => gettype($event),
            'event_keys' => is_array($event) ? array_keys($event) : 'not an array',
            'component_id' => $this->getId()
        ]);
        
        // Log the full event structure
        if (is_array($event)) {
            foreach ($event as $key => $value) {
                Log::debug("Event key: {$key}", ['value' => $value]);
            }
        }
        
        $this->dispatch('console-log', [
            'message' => 'Livewire received module state changed event',
            'raw_event' => $event,
            'module' => is_array($event) ? ($event['module']['name'] ?? 'unknown') : 'unknown',
            'tenant_id' => is_array($event) ? ($event['tenant_id'] ?? 'unknown') : 'unknown',
            'action' => is_array($event) ? ($event['action'] ?? 'unknown') : 'unknown',
            'component_id' => $this->getId()
        ]);
        $this->loadNavigation();
    }

    public function render()
    {
        return view('tenant-modules::module-navigation');
    }
}