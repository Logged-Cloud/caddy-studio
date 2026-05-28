@once
<style>
    .cs-root {
        --cs-surface: #16171a;
        --cs-surface-2: #1E1F22;
        --cs-line: #3A3D40;
        --cs-ink: #F0EDE5;
        --cs-ink-dim: #A3A099;
        --cs-accent: #2C66E8;
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 540px;
        background: var(--cs-surface);
        color: var(--cs-ink);
        font: 14px/1.4 ui-sans-serif, system-ui, sans-serif;
        border: 1px solid var(--cs-line);
        border-radius: .5rem;
        overflow: hidden;
    }
    .cs-topbar {
        display: flex;
        align-items: center;
        gap: .6rem;
        padding: .5rem .75rem;
        background: var(--cs-surface-2);
        border-bottom: 1px solid var(--cs-line);
    }
    .cs-brand { font-weight: 600; letter-spacing: .01em; }
    .cs-count { color: var(--cs-ink-dim); font-size: .8rem; }
    .cs-spacer { flex: 1; }
    .cs-status { color: var(--cs-ink-dim); font-size: .8rem; }
    .cs-btn {
        background: var(--cs-surface);
        color: var(--cs-ink);
        border: 1px solid var(--cs-line);
        border-radius: .35rem;
        padding: .3rem .7rem;
        cursor: pointer;
        font: inherit;
        font-size: .82rem;
    }
    .cs-btn:hover { background: rgba(255,255,255,.06); }
    .cs-btn--primary { background: var(--cs-accent); border-color: var(--cs-accent); }
    .cs-btn--primary:hover { filter: brightness(1.1); }

    .cs-drift { padding: .4rem .75rem; font-size: .82rem; border-bottom: 1px solid var(--cs-line); }
    .cs-drift--ok   { background: rgba(22,163,74,.15);  color: #86efac; }
    .cs-drift--warn { background: rgba(245,158,11,.15); color: #fde68a; }

    .cs-body { display: flex; flex: 1; min-height: 0; }

    .cs-palette {
        width: 190px;
        flex-shrink: 0;
        background: var(--cs-surface-2);
        border-right: 1px solid var(--cs-line);
        overflow-y: auto;
        padding: .5rem;
    }
    .cs-palette-group {
        margin: .6rem 0 .3rem;
        font-size: .62rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--cs-ink-dim);
    }
    .cs-palette-item {
        display: flex;
        align-items: center;
        gap: .45rem;
        width: 100%;
        background: var(--cs-surface);
        border: 1px solid var(--cs-line);
        color: var(--cs-ink);
        border-radius: .3rem;
        padding: .3rem .45rem;
        margin-bottom: .25rem;
        cursor: pointer;
        font: inherit;
        font-size: .78rem;
        text-align: left;
    }
    .cs-palette-item:hover { background: rgba(255,255,255,.06); }
    .cs-palette-icon { width: 1.1rem; text-align: center; }

    .cs-canvas {
        position: relative;
        flex: 1;
        overflow: hidden;
        background-image: radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px);
        background-size: 22px 22px;
        cursor: grab;
        touch-action: none;
    }
    .cs-canvas:active { cursor: grabbing; }
    .cs-world { position: absolute; inset: 0; transform-origin: 0 0; }
    .cs-wires { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; pointer-events: none; }
    .cs-wire { fill: none; stroke: var(--cs-accent); stroke-width: 2; opacity: .8; }

    .cs-empty {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--cs-ink-dim);
        font-style: italic;
        pointer-events: none;
    }

    .cs-node {
        position: absolute;
        width: 210px;
        background: var(--cs-surface-2);
        border: 1px solid var(--cs-line);
        border-radius: .45rem;
        box-shadow: 0 6px 18px rgba(0,0,0,.4);
        user-select: none;
    }
    .cs-node.is-selected { border-color: var(--cs-accent); }
    .cs-node-head {
        display: flex;
        align-items: center;
        gap: .4rem;
        padding: .35rem .5rem;
        border-bottom: 1px solid var(--cs-line);
        border-radius: .45rem .45rem 0 0;
        cursor: move;
    }
    .cs-node-head--server   { background: rgba(99,102,241,.22); }
    .cs-node-head--route    { background: rgba(6,182,212,.22); }
    .cs-node-head--upstream { background: rgba(225,29,72,.22); }
    .cs-node-head--handler  { background: rgba(44,102,232,.22); }
    .cs-node-head--matcher  { background: rgba(245,158,11,.22); }
    .cs-node-head--tls      { background: rgba(168,85,247,.22); }
    .cs-node-icon { font-size: .95rem; }
    .cs-node-title { flex: 1; font-size: .8rem; font-weight: 600; }
    .cs-node-del {
        background: transparent; border: 0; color: var(--cs-ink-dim);
        cursor: pointer; font-size: .75rem; padding: 0 .15rem;
    }
    .cs-node-del:hover { color: #fca5a5; }

    .cs-row { display: flex; align-items: center; gap: .4rem; padding: .15rem .5rem; font-size: .72rem; }
    .cs-row--out { justify-content: flex-end; }
    .cs-socket {
        width: 11px; height: 11px; border-radius: 50%;
        background: var(--cs-surface);
        border: 2px solid var(--cs-accent);
        cursor: crosshair;
        flex-shrink: 0;
    }
    .cs-socket--in  { margin-left: -11px; }
    .cs-socket--out { margin-right: -11px; }
    .cs-socket:hover { background: var(--cs-accent); }
    .cs-socket-label { color: var(--cs-ink-dim); }

    .cs-field { padding: .2rem .5rem .35rem; }
    .cs-field-label { display: block; font-size: .62rem; color: var(--cs-ink-dim); margin-bottom: .12rem; }
    .cs-input {
        width: 100%;
        background: var(--cs-surface);
        color: var(--cs-ink);
        border: 1px solid var(--cs-line);
        border-radius: .25rem;
        padding: .2rem .35rem;
        font: inherit;
        font-size: .74rem;
    }

    .cs-preview {
        border-top: 1px solid var(--cs-line);
        background: var(--cs-surface-2);
        max-height: 30%;
        overflow: auto;
    }
    .cs-preview summary { padding: .4rem .75rem; cursor: pointer; font-size: .78rem; color: var(--cs-ink-dim); }
    .cs-preview pre { margin: 0; padding: 0 .75rem .75rem; font-size: .72rem; white-space: pre-wrap; }
</style>
@endonce
