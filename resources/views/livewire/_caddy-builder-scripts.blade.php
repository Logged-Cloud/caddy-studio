@once
<script>
    function caddyBuilder(palette) {
        const GROUP_ICON = {
            server: '🖥️', route: '🌐', upstream: '🎯',
            handler: '🧩', matcher: '🔎', tls: '🔐',
        };
        const groups = {};
        for (const [name, entries] of Object.entries(palette || {})) {
            groups[name] = { icon: GROUP_ICON[name] || '◆', nodes: entries };
        }

        return {
            groups,
            pan: { x: 0, y: 0 },
            zoom: 1,
            panDrag: null,
            nodeDrag: null,
            pending: null,
            longPress: null,
            pointers: {},                              // active pointers by id
            pinch: null,                               // two-finger zoom state
            wheel: { open: false, sx: 0, sy: 0, wx: 0, wy: 0, group: null },

            init() {
                this.scheduleRedraw();
                // Version-independent: redraw whenever the node graph DOM changes
                // (Livewire morph after add / connect / remove / move). A
                // MutationObserver avoids depending on a specific hook name.
                this.$nextTick(() => {
                    const world = this.$refs.world;
                    if (world && window.MutationObserver) {
                        new MutationObserver((muts) => {
                            // Ignore our own SVG rewrites (redraw mutates the
                            // wires layer) · otherwise we'd loop forever.
                            const relevant = muts.some(m => !(m.target.closest && m.target.closest('.cs-wires')));
                            if (relevant) this.scheduleRedraw();
                        }).observe(world, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] });
                    }
                });
                window.addEventListener('resize', () => this.scheduleRedraw());
            },

            resetView() { this.pan = { x: 0, y: 0 }; this.zoom = 1; this.scheduleRedraw(); },

            // Mouse-wheel / trackpad zoom, centred on the cursor.
            onWheel(e) {
                const c = this.$refs.canvas.getBoundingClientRect();
                const mid = { x: e.clientX - c.left, y: e.clientY - c.top };
                const wp = { x: (mid.x - this.pan.x) / this.zoom, y: (mid.y - this.pan.y) / this.zoom };
                const factor = e.deltaY < 0 ? 1.1 : 0.9;
                const next = Math.min(2.5, Math.max(0.3, this.zoom * factor));
                this.zoom = next;
                this.pan = { x: mid.x - wp.x * next, y: mid.y - wp.y * next };
                this.scheduleRedraw();
            },

            // ---- coordinate helpers (account for zoom) ----
            worldPoint(e) {
                const w = this.$refs.world.getBoundingClientRect();
                return { x: Math.round((e.clientX - w.left) / this.zoom), y: Math.round((e.clientY - w.top) / this.zoom) };
            },
            canvasPoint(e) {
                const c = this.$refs.canvas.getBoundingClientRect();
                return { x: e.clientX - c.left, y: e.clientY - c.top };
            },

            // ---- wheel ----
            openWheel(e) {
                const c = this.canvasPoint(e), w = this.worldPoint(e);
                this.wheel = { open: true, sx: c.x, sy: c.y, wx: w.x, wy: w.y, group: null };
            },
            closeWheel() { this.wheel.open = false; this.wheel.group = null; },
            pick(key) { this.$wire.addNode(key, this.wheel.wx, this.wheel.wy); this.closeWheel(); },

            // ---- pointer plumbing ----
            onCanvasDown(e) {
                if (e.target.closest('.cs-node, .cs-wheel')) return;
                if (this.wheel.open) { this.closeWheel(); return; }

                this.pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
                const ids = Object.keys(this.pointers);

                if (ids.length === 2) {            // second finger · start pinch
                    this.cancelLongPress();
                    this.panDrag = null;
                    const [a, b] = ids.map(id => this.pointers[id]);
                    this.pinch = {
                        startDist: Math.hypot(a.x - b.x, a.y - b.y),
                        startZoom: this.zoom,
                    };
                    return;
                }

                try { this.$refs.canvas.setPointerCapture(e.pointerId); } catch (_) {}
                this.panDrag = { sx: e.clientX, sy: e.clientY, ox: this.pan.x, oy: this.pan.y, pid: e.pointerId, moved: false };

                if (e.pointerType === 'touch') {   // long-press opens the wheel
                    const ev = { clientX: e.clientX, clientY: e.clientY };
                    this.longPress = setTimeout(() => { this.panDrag = null; this.openWheel(ev); }, 500);
                }
            },

            onPointerMove(e) {
                if (this.pointers[e.pointerId]) this.pointers[e.pointerId] = { x: e.clientX, y: e.clientY };

                if (this.pinch) { this.handlePinch(); return; }
                if (this.panDrag) {
                    const dx = e.clientX - this.panDrag.sx, dy = e.clientY - this.panDrag.sy;
                    if (!this.panDrag.moved && Math.hypot(dx, dy) > 4) { this.panDrag.moved = true; this.cancelLongPress(); }
                    this.pan = { x: this.panDrag.ox + dx, y: this.panDrag.oy + dy };
                    return;
                }
                if (this.nodeDrag) {
                    const el = this.nodeDrag.el;
                    el.style.left = (this.nodeDrag.ox + (e.clientX - this.nodeDrag.sx) / this.zoom) + 'px';
                    el.style.top  = (this.nodeDrag.oy + (e.clientY - this.nodeDrag.sy) / this.zoom) + 'px';
                    this.scheduleRedraw();
                    return;
                }
                if (this.pending) this.scheduleRedraw(e);
            },

            onPointerUp(e) {
                this.cancelLongPress();
                delete this.pointers[e.pointerId];
                if (Object.keys(this.pointers).length < 2) this.pinch = null;

                if (this.nodeDrag) {
                    const el = this.nodeDrag.el;
                    this.$wire.moveNode(this.nodeDrag.id, Math.round(parseFloat(el.style.left)), Math.round(parseFloat(el.style.top)));
                    try { el.releasePointerCapture(this.nodeDrag.pid); } catch (_) {}
                    this.nodeDrag = null;
                }
                if (this.panDrag) {
                    try { this.$refs.canvas.releasePointerCapture(this.panDrag.pid); } catch (_) {}
                    this.panDrag = null;
                }
                if (this.pending) { this.pending = null; this.scheduleRedraw(); }
            },

            handlePinch() {
                const ids = Object.keys(this.pointers);
                if (ids.length < 2) return;
                const [a, b] = ids.map(id => this.pointers[id]);
                const dist = Math.hypot(a.x - b.x, a.y - b.y);
                const c = this.$refs.canvas.getBoundingClientRect();
                // Pinch midpoint in canvas-local screen coords.
                const mid = { x: (a.x + b.x) / 2 - c.left, y: (a.y + b.y) / 2 - c.top };
                // World point currently under the midpoint.
                const wp = { x: (mid.x - this.pan.x) / this.zoom, y: (mid.y - this.pan.y) / this.zoom };
                const next = Math.min(2.5, Math.max(0.3, this.pinch.startZoom * (dist / this.pinch.startDist)));
                this.zoom = next;
                // Keep that world point pinned under the fingers.
                this.pan = { x: mid.x - wp.x * next, y: mid.y - wp.y * next };
                this.scheduleRedraw();
            },

            cancelLongPress() { if (this.longPress) { clearTimeout(this.longPress); this.longPress = null; } },

            // ---- node drag ----
            startNodeDrag(e, id) {
                if (e.target.closest('.cs-socket, .cs-node-del, .cs-input, button, select, textarea')) return;
                const el = e.currentTarget;
                try { el.setPointerCapture(e.pointerId); } catch (_) {}
                this.nodeDrag = {
                    id, el, pid: e.pointerId,
                    sx: e.clientX, sy: e.clientY,
                    ox: parseFloat(el.style.left) || 0,
                    oy: parseFloat(el.style.top) || 0,
                };
            },

            // ---- wiring ----
            startConnect(e, node, socket) { this.pending = { node, socket }; },
            endConnect(node, socket) {
                if (this.pending) {
                    this.$wire.connect(this.pending.node, this.pending.socket, node, socket);
                    this.pending = null;
                }
            },

            // ---- wire rendering ----
            edges() {
                // Live edges from the Livewire component · always current, unlike
                // a value baked in at first render.
                try { return this.$wire.get('edges') || []; } catch (_) { return []; }
            },
            socketCenter(sel) {
                const world = this.$refs.world;
                const el = world && world.querySelector(`[data-socket="${sel}"]`);
                if (!el) return null;
                const r = el.getBoundingClientRect(), w = world.getBoundingClientRect();
                return { x: (r.left + r.width / 2 - w.left) / this.zoom, y: (r.top + r.height / 2 - w.top) / this.zoom };
            },
            path(a, b) {
                const dx = Math.max(40, Math.abs(b.x - a.x) / 2);
                return `M ${a.x} ${a.y} C ${a.x + dx} ${a.y}, ${b.x - dx} ${b.y}, ${b.x} ${b.y}`;
            },
            scheduleRedraw(liveEvent = null) {
                // Double rAF · wait for layout so getBoundingClientRect is settled.
                requestAnimationFrame(() => requestAnimationFrame(() => this.redraw(liveEvent)));
            },
            redraw(liveEvent = null) {
                const svg = this.$refs.wires;
                if (!svg) return;
                let html = '';
                for (const edge of this.edges()) {
                    const a = this.socketCenter(`${edge.from_node}|out|${edge.from_socket}`);
                    const b = this.socketCenter(`${edge.to_node}|in|${edge.to_socket}`);
                    if (a && b) html += `<path class="cs-wire" d="${this.path(a, b)}" />`;
                }
                if (this.pending && liveEvent) {
                    const a = this.socketCenter(`${this.pending.node}|out|${this.pending.socket}`);
                    const w = this.$refs.world.getBoundingClientRect();
                    const b = { x: (liveEvent.clientX - w.left) / this.zoom, y: (liveEvent.clientY - w.top) / this.zoom };
                    if (a) html += `<path class="cs-wire" style="opacity:.5;stroke-dasharray:5 4" d="${this.path(a, b)}" />`;
                }
                svg.innerHTML = html;
            },
        };
    }
</script>
@endonce
