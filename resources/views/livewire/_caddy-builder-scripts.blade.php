@once
<script>
    function caddyBuilder() {
        return {
            pan: { x: 0, y: 0 },
            panDrag: null,
            nodeDrag: null,
            pending: null, // { node, socket } while dragging a new wire

            init() {
                this.$nextTick(() => this.redraw());
                // Redraw wires after every Livewire DOM patch.
                if (window.Livewire) {
                    Livewire.hook('morphed', () => this.$nextTick(() => this.redraw()));
                }
                window.addEventListener('resize', () => this.redraw());
            },

            // ---- pan ----
            onCanvasDown(e) {
                if (e.target.closest('.cs-node')) return;
                this.panDrag = { sx: e.clientX, sy: e.clientY, ox: this.pan.x, oy: this.pan.y };
            },

            onPointerMove(e) {
                if (this.panDrag) {
                    this.pan = {
                        x: this.panDrag.ox + (e.clientX - this.panDrag.sx),
                        y: this.panDrag.oy + (e.clientY - this.panDrag.sy),
                    };
                    return;
                }
                if (this.nodeDrag) {
                    const el = this.nodeDrag.el;
                    el.style.left = (this.nodeDrag.ox + (e.clientX - this.nodeDrag.sx)) + 'px';
                    el.style.top  = (this.nodeDrag.oy + (e.clientY - this.nodeDrag.sy)) + 'px';
                    this.redraw();
                    return;
                }
                if (this.pending) {
                    this.redraw(e);
                }
            },

            onPointerUp() {
                if (this.nodeDrag) {
                    const el = this.nodeDrag.el;
                    const x = Math.round(parseFloat(el.style.left));
                    const y = Math.round(parseFloat(el.style.top));
                    this.$wire.moveNode(this.nodeDrag.id, x, y);
                    this.nodeDrag = null;
                }
                this.panDrag = null;
                // A dropped wire that didn't land on an input just cancels.
                if (this.pending) {
                    this.pending = null;
                    this.redraw();
                }
            },

            // ---- node drag ----
            startNodeDrag(e, id) {
                if (e.target.closest('.cs-socket, .cs-node-del, .cs-input, button, select, textarea')) return;
                const el = e.currentTarget;
                this.nodeDrag = {
                    id, el,
                    sx: e.clientX, sy: e.clientY,
                    ox: parseFloat(el.style.left) || 0,
                    oy: parseFloat(el.style.top) || 0,
                };
            },

            // ---- wiring ----
            startConnect(e, node, socket) {
                this.pending = { node, socket };
            },
            endConnect(node, socket) {
                if (this.pending) {
                    this.$wire.connect(this.pending.node, this.pending.socket, node, socket);
                    this.pending = null;
                }
            },

            // ---- wire rendering ----
            socketCenter(sel) {
                const world = this.$refs.world;
                const el = world.querySelector(`[data-socket="${sel}"]`);
                if (!el) return null;
                const r = el.getBoundingClientRect();
                const w = world.getBoundingClientRect();
                return { x: r.left + r.width / 2 - w.left, y: r.top + r.height / 2 - w.top };
            },

            path(a, b) {
                const dx = Math.max(40, Math.abs(b.x - a.x) / 2);
                return `M ${a.x} ${a.y} C ${a.x + dx} ${a.y}, ${b.x - dx} ${b.y}, ${b.x} ${b.y}`;
            },

            redraw(liveEvent = null) {
                const svg = this.$refs.wires;
                if (!svg) return;
                const edges = @js($edges);
                let html = '';
                for (const edge of edges) {
                    const a = this.socketCenter(`${edge.from_node}|out|${edge.from_socket}`);
                    const b = this.socketCenter(`${edge.to_node}|in|${edge.to_socket}`);
                    if (a && b) html += `<path class="cs-wire" d="${this.path(a, b)}" />`;
                }
                // Live wire being dragged.
                if (this.pending && liveEvent) {
                    const a = this.socketCenter(`${this.pending.node}|out|${this.pending.socket}`);
                    const w = this.$refs.world.getBoundingClientRect();
                    const b = { x: liveEvent.clientX - w.left, y: liveEvent.clientY - w.top };
                    if (a) html += `<path class="cs-wire" style="opacity:.5;stroke-dasharray:5 4" d="${this.path(a, b)}" />`;
                }
                svg.innerHTML = html;
            },
        };
    }
</script>
@endonce
