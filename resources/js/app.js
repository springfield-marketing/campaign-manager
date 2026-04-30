import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('importProgress', ({ endpoint, imports }) => ({
    endpoint,
    imports,
    timer: null,

    start() {
        if (this.hasActiveImports()) {
            this.timer = setInterval(() => this.refresh(), 3000);
        }
    },

    get(id) {
        return this.imports.find((item) => item.id === id);
    },

    hasActiveImports() {
        return this.imports.some((item) => item.is_active);
    },

    async refresh() {
        const ids = this.imports.filter((item) => item.is_active).map((item) => item.id);

        if (ids.length === 0) {
            clearInterval(this.timer);
            return;
        }

        const response = await fetch(`${this.endpoint}?ids=${ids.join(',')}`, {
            headers: { Accept: 'application/json' },
        });

        if (! response.ok) {
            return;
        }

        const payload = await response.json();

        payload.imports.forEach((updated) => {
            const index = this.imports.findIndex((item) => item.id === updated.id);

            if (index !== -1) {
                Object.assign(this.imports[index], updated);
            }
        });

        if (! this.hasActiveImports()) {
            clearInterval(this.timer);
        }
    },
}));

Alpine.start();
