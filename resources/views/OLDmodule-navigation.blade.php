<div>
    <flux:navlist>
        @forelse($navigation as $item)
            @if(app('navigation')->canViewNavigationItem($item))
                @if(!empty($item['children']))
                    <flux:navlist.group 
                        :heading="$item['label']" 
                        expandable 
                        :expanded="request()->routeIs($item['route'] ?? '') || collect($item['children'] ?? [])->contains(function($child) { return request()->routeIs($child['route'] ?? ''); })"
                        :icon="$item['icon'] ?? 'folder'"
                    >
                        @foreach($item['children'] as $child)
                            @if(app('navigation')->canViewNavigationItem($child))
                                <flux:navlist.item 
                                    :href="Route::has($child['route'] ?? '') ? route($child['route']) : '#'" 
                                    :icon="$child['icon'] ?? 'document'"
                                    :current="request()->routeIs($child['route'] ?? '')" 
                                    wire:navigate
                                >
                                    {{ $child['label'] }}
                                </flux:navlist.item>
                            @endif
                        @endforeach
                    </flux:navlist.group>
                @else
                    <flux:navlist.item 
                        :href="Route::has($item['route'] ?? '') ? route($item['route']) : '#'" 
                        :icon="$item['icon'] ?? 'circle'"
                        :current="request()->routeIs($item['route'] ?? '')" 
                        wire:navigate
                    >
                        {{ $item['label'] }}
                    </flux:navlist.item>
                @endif
            @endif
        @empty
            <div class="flex flex-col items-center py-4 text-gray-500">
                <flux:icon.puzzle-piece class="w-8 h-8 mb-2" />
                <span class="text-sm">No modules available</span>
            </div>
        @endforelse
    </flux:navlist>
</div> 
@script
<script>
    const channelName = `tenant.${@js(tenant()->id)}`;
    console.log('Subscribing to channel:', channelName);
    
    // Debug logs only
    console.log('Echo instance available:', !!window.Echo);
    console.log('Current tenant ID:', @js(tenant()->id));
    console.log('Component ID:', @js($this->getId()));

    // Listen for Livewire console logs
    Livewire.on('console-log', (data) => {
        // Handle array-wrapped data
        const eventData = Array.isArray(data) ? data[0] : data;
        console.log('Raw event data:', eventData);
        console.log(`[Component ${eventData.component_id}] ${eventData.message} - Module: ${eventData.module}, Tenant: ${eventData.tenant_id}`);
    });

    // Listen for both module events
    window.Echo.private(channelName)
        .listen('.module-state-changed', (e) => {
            console.log('Module state changed event received:', e);
            // The Livewire component will handle the navigation update
        })
        .error((error) => {
            console.error('Error listening to module events:', error);
        });
</script>
@endscript