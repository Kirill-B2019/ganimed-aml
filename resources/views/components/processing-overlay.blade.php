{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<div
    x-cloak
    x-show="open"
    x-bind:aria-hidden="(!open).toString()"
    class="processing-overlay"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="processing-title"
    aria-describedby="processing-body"
    aria-busy="true"
>
    <div class="processing-card">
        <div class="processing-spinner" aria-hidden="true"></div>
        <h2 id="processing-title" class="mt-4 text-base font-semibold text-ink" x-text="title"></h2>
        <p id="processing-body" class="mt-2 text-sm leading-6 text-ink-muted" x-text="body"></p>
    </div>
</div>
