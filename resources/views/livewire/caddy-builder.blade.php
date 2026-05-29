@php($palette = $this->palette)
<div class="cs-root"
     x-data="caddyBuilder(@js($palette))"
     x-init="init()"
     wire:ignore.self>
    @include('caddy-studio::livewire._caddy-builder-styles')

    {{-- Top bar --}}
    <div class="cs-topbar">
        <span class="cs-brand">Caddy Studio</span>
        <span class="cs-count">{{ count($this->compiledRoutes) }} route(s)</span>
        <span class="cs-spacer"></span>
        @if($status)
            <span class="cs-status">{{ $status }}</span>
        @endif
        <button class="cs-btn" @click="resetView()" title="Recentre the canvas">Recentre</button>
        <button class="cs-btn" wire:click="refreshDrift" wire:loading.attr="disabled">Check drift</button>
        <button class="cs-btn cs-btn--primary" wire:click="apply" wire:loading.attr="disabled"
                wire:confirm="Push this graph live to Caddy?">Apply</button>
    </div>

    {{-- Drift banner --}}
    @if($drift)
        <div class="cs-drift {{ $drift['in_sync'] ? 'cs-drift--ok' : 'cs-drift--warn' }}">
            @if($drift['in_sync'])
                In sync with Caddy ({{ $drift['live_count'] }} live route(s)).
            @else
                Drift:
                @if($drift['only_in_graph'])<b>not applied:</b> {{ implode(', ', $drift['only_in_graph']) }}. @endif
                @if($drift['only_in_live'])<b>unmanaged live:</b> {{ implode(', ', $drift['only_in_live']) }}. @endif
                @if($drift['changed'])<b>changed:</b> {{ implode(', ', $drift['changed']) }}. @endif
            @endif
        </div>
    @endif

    <div class="cs-body">
        {{-- Canvas (full width · no palette · right-click / long-press for the node wheel) --}}
        <div class="cs-canvas" x-ref="canvas"
             @pointerdown="onCanvasDown($event)"
             @pointermove="onPointerMove($event)"
             @pointerup="onPointerUp($event)"
             @pointercancel="onPointerUp($event)"
             @wheel.prevent="onWheel($event)"
             @contextmenu.prevent="openWheel($event)">
            <div class="cs-world" x-ref="world" :style="`transform: translate(${pan.x}px, ${pan.y}px) scale(${zoom})`">
                <svg class="cs-wires" x-ref="wires"></svg>

                @foreach($nodes as $i => $node)
                    @php($lib = \LoggedCloud\CaddyStudio\Nodes\NodeRegistry::library()[$node['type']] ?? null)
                    @if($lib)
                        <div class="cs-node @if($selectedNodeId === $node['id']) is-selected @endif"
                             data-node-id="{{ $node['id'] }}"
                             wire:key="node-{{ $node['id'] }}"
                             style="left: {{ $node['position']['x'] ?? 100 }}px; top: {{ $node['position']['y'] ?? 100 }}px"
                             @pointerdown.stop="startNodeDrag($event, '{{ $node['id'] }}')"
                             @click.stop="$wire.set('selectedNodeId', '{{ $node['id'] }}')">
                            <div class="cs-node-head cs-node-head--{{ $lib['group'] }}">
                                <span class="cs-node-icon">{!! $lib['icon'] !!}</span>
                                <span class="cs-node-title">{{ $lib['label'] }}</span>
                                <button type="button" class="cs-node-del" wire:click.stop="removeNode('{{ $node['id'] }}')">✕</button>
                            </div>

                            {{-- Input sockets --}}
                            @foreach($lib['inputs'] ?? [] as $sk => $sock)
                                <div class="cs-row cs-row--in">
                                    <span class="cs-socket cs-socket--in"
                                          data-socket="{{ $node['id'] }}|in|{{ $sk }}"
                                          @pointerup.stop="endConnect('{{ $node['id'] }}', '{{ $sk }}')"></span>
                                    <span class="cs-socket-label">{{ $sock['label'] ?? $sk }}</span>
                                </div>
                            @endforeach

                            {{-- Settings --}}
                            @foreach($lib['settings'] ?? [] as $sk => $def)
                                <div class="cs-field">
                                    <label class="cs-field-label">{{ $def['label'] ?? $sk }}</label>
                                    @if(($def['kind'] ?? 'text') === 'bool')
                                        <input type="checkbox" wire:model.live="nodes.{{ $i }}.settings.{{ $sk }}">
                                    @elseif(($def['kind'] ?? '') === 'select')
                                        <select wire:model.live="nodes.{{ $i }}.settings.{{ $sk }}" class="cs-input">
                                            @foreach($def['options'] ?? [] as $opt)
                                                <option value="{{ $opt }}">{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    @elseif(($def['kind'] ?? '') === 'textarea')
                                        <textarea wire:model.blur="nodes.{{ $i }}.settings.{{ $sk }}" class="cs-input" rows="2"></textarea>
                                    @elseif(($def['kind'] ?? '') === 'number')
                                        <input type="number" wire:model.blur="nodes.{{ $i }}.settings.{{ $sk }}" class="cs-input">
                                    @else
                                        <input type="text" wire:model.blur="nodes.{{ $i }}.settings.{{ $sk }}" class="cs-input">
                                    @endif
                                </div>
                            @endforeach

                            {{-- Output sockets --}}
                            @foreach($lib['outputs'] ?? [] as $sk => $sock)
                                <div class="cs-row cs-row--out">
                                    <span class="cs-socket-label">{{ $sock['label'] ?? $sk }}</span>
                                    <span class="cs-socket cs-socket--out"
                                          data-socket="{{ $node['id'] }}|out|{{ $sk }}"
                                          @pointerdown.stop="startConnect($event, '{{ $node['id'] }}', '{{ $sk }}')"></span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>

            @if(count($nodes) === 0)
                <div class="cs-empty">Right-click (or long-press) the canvas to add a node.</div>
            @endif

            {{-- Weapon wheel · node picker --}}
            <div class="cs-wheel" x-show="wheel.open" x-cloak
                 :style="`left:${wheel.sx}px; top:${wheel.sy}px`"
                 @pointerdown.stop @click.outside="closeWheel()">
                {{-- Stage 1 · group donut --}}
                <template x-if="!wheel.group">
                    <div class="cs-wheel-groups">
                        <template x-for="(g, name) in groups" :key="name">
                            <button type="button" class="cs-wheel-slice" :class="`cs-wheel-slice--${name}`"
                                    @click.stop="wheel.group = name">
                                <span class="cs-wheel-slice-icon" x-text="g.icon"></span>
                                <span class="cs-wheel-slice-label" x-text="name"></span>
                            </button>
                        </template>
                    </div>
                </template>
                {{-- Stage 2 · node list for the chosen group --}}
                <template x-if="wheel.group">
                    <div class="cs-wheel-list">
                        <div class="cs-wheel-bar">
                            <button type="button" class="cs-wheel-back" @click.stop="wheel.group = null">‹</button>
                            <span x-text="wheel.group"></span>
                        </div>
                        <template x-for="(entry, key) in groups[wheel.group].nodes" :key="key">
                            <button type="button" class="cs-wheel-item" @click.stop="pick(key)">
                                <span class="cs-wheel-item-icon" x-html="entry.icon"></span>
                                <span class="cs-wheel-item-label" x-text="entry.label"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Compiled preview --}}
    <details class="cs-preview">
        <summary>Compiled Caddy JSON</summary>
        <pre>{{ json_encode($this->compiledRoutes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </details>

    @include('caddy-studio::livewire._caddy-builder-scripts')
</div>
