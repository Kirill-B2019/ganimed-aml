/* | KB @CerberRus00 - Nexus Invest Team */
import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('processingGate', () => ({
        open: false,
        title: '',
        body: '',
        init() {
            const root = this.$el;
            this.title = root.dataset.processingTitle || '';
            this.body = root.dataset.processingBody || '';

            window.addEventListener('processing-open', (event) => {
                const detail = event.detail || {};
                this.title = detail.title || root.dataset.processingTitle || this.title;
                this.body = detail.body || root.dataset.processingBody || this.body;
                this.show();
            });
            window.addEventListener('processing-close', () => this.hide());

            window.addEventListener('submit', (event) => {
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-processing')) {
                    return;
                }
                this.title = root.dataset.processingTitle || this.title;
                this.body = root.dataset.processingBody || this.body;
                this.show();
            }, true);
        },
        show() {
            this.open = true;
            document.documentElement.classList.add('overflow-hidden');
        },
        hide() {
            this.open = false;
            document.documentElement.classList.remove('overflow-hidden');
        },
        retry(url, body) {
            this.body = body || this.body;
            this.show();
            window.axios.post(url).finally(() => window.location.reload());
        },
    }));

    Alpine.data('checkWaiter', (opts) => ({
        init() {
            const title = opts.scanTitle || '';
            const body = opts.pending ? (opts.scanBody || '') : (opts.enrichBody || '');
            window.dispatchEvent(new CustomEvent('processing-open', { detail: { title, body } }));

            if (opts.pending) {
                this.poll();
                return;
            }
            if (opts.enrich) {
                this.enrich();
            }
        },
        poll() {
            this.timer = window.setInterval(() => {
                window.axios.get(opts.statusUrl).then((response) => {
                    if (response.data.status !== 'pending') {
                        window.location.reload();
                    }
                });
            }, 4000);
        },
        enrich() {
            window.axios.post(opts.enrichUrl).finally(() => {
                window.location.reload();
            });
        },
        destroy() {
            if (this.timer) {
                window.clearInterval(this.timer);
            }
        },
    }));
});

Alpine.start();
