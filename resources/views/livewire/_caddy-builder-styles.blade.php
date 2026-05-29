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

    [x-cloak] { display: none !important; }

    .cs-body { display: flex; flex: 1; min-height: 0; }

    /* ---- weapon wheel (node picker) ---- */
    .cs-wheel {
        position: absolute;
        transform: translate(-50%, -50%);
        z-index: 50;
        background: var(--cs-surface-2);
        border: 1px solid var(--cs-line);
        border-radius: .6rem;
        box-shadow: 0 12px 34px rgba(0,0,0,.55);
        overflow: hidden;
    }
    .cs-wheel-groups {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 2px;
        padding: 4px;
        width: 240px;
    }
    .cs-wheel-slice {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .15rem;
        padding: .55rem .3rem;
        background: var(--cs-surface);
        border: 1px solid var(--cs-line);
        border-radius: .4rem;
        color: var(--cs-ink);
        cursor: pointer;
        font: inherit;
    }
    .cs-wheel-slice:hover { background: rgba(255,255,255,.07); }
    .cs-wheel-slice-icon { font-size: 1.15rem; }
    .cs-wheel-slice-label { font-size: .62rem; text-transform: uppercase; letter-spacing: .05em; color: var(--cs-ink-dim); }
    .cs-wheel-slice--server   { border-color: rgba(99,102,241,.5); }
    .cs-wheel-slice--route    { border-color: rgba(6,182,212,.5); }
    .cs-wheel-slice--upstream { border-color: rgba(225,29,72,.5); }
    .cs-wheel-slice--handler  { border-color: rgba(44,102,232,.5); }
    .cs-wheel-slice--matcher  { border-color: rgba(245,158,11,.5); }
    .cs-wheel-slice--tls      { border-color: rgba(168,85,247,.5); }

    .cs-wheel-list { width: 220px; max-height: 260px; overflow-y: auto; overscroll-behavior: contain; padding: 4px; }
    .cs-wheel-bar {
        display: flex; align-items: center; gap: .4rem;
        padding: .25rem .35rem .4rem;
        font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: var(--cs-ink-dim);
    }
    .cs-wheel-back {
        background: var(--cs-surface); border: 1px solid var(--cs-line); color: var(--cs-ink);
        border-radius: .3rem; cursor: pointer; padding: 0 .45rem; font-size: .9rem; line-height: 1.4;
    }
    .cs-wheel-item {
        display: flex; align-items: center; gap: .5rem; width: 100%;
        background: transparent; border: 0; color: var(--cs-ink);
        border-radius: .3rem; padding: .35rem .45rem; cursor: pointer; font: inherit; font-size: .78rem; text-align: left;
    }
    .cs-wheel-item:hover { background: rgba(255,255,255,.07); }
    .cs-wheel-item-icon { width: 1.1rem; text-align: center; }

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
