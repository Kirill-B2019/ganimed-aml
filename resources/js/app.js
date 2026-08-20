/* | KB @CerberRus00 - Nexus Invest Team */
import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('scanPoll', (id) => ({
        init() {
            const tick = () => {
                window.axios.get(`/checks/${id}/status`).then((response) => {
                    if (response.data.status !== 'pending') {
                        window.location.reload();
                    }
                });
            };

            this.timer = window.setInterval(tick, 4000);
        },
        destroy() {
            if (this.timer) {
                window.clearInterval(this.timer);
            }
        },
    }));
});

Alpine.start();
