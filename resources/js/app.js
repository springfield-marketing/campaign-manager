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
        return this.imports.find((item) => item.id === id) || {
            id,
            status: 'unknown',
            status_label: 'unknown',
            status_message: 'Status is not available yet.',
            total_rows: 0,
            processed_rows: 0,
            successful_rows: 0,
            failed_rows: 0,
            duplicate_rows: 0,
            progress: 0,
            is_active: false,
        };
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
