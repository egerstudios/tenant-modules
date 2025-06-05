<div>
    <!-- Debug output -->
    <div class="p-2 bg-yellow-100 text-yellow-800 text-sm mb-4">
        <div>Navigation Items: {{ count($navigation) }}</div>
    </div>

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
    // Debug log to verify Echo is available
    console.log('Echo instance available:', !!window.Echo);
    console.log('Current tenant ID:', @js(tenant()->id));
    
    const channelName = `tenant.${@js(tenant()->id)}`;
    console.log('Subscribing to channel:', channelName);
    
    // Listen for the module-enabled event
    window.Echo.private(channelName)
        .listen('.module-enabled', (e) => {
            console.log('Module enabled event received:', e);
            // You can add additional UI updates here if needed
        })
        .error((error) => {
            console.error('Error listening to module-enabled event:', error);
        });
</script>
@endscript