import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('importProgress', ({ endpoint, imports, wsChannel = 'ivr.imports', wsEvent = '.ivr.import.updated' }) => ({
    endpoint,
    imports,
    wsChannel,
    wsEvent,
    timer: null,
    inFlight: false,
    channel: null,

    init() {
        this.connectWebsocket();
        this.start();

        window.addEventListener('pageshow', () => this.start());
        document.addEventListener('visibilitychange', () => {
            if (! document.hidden) {
                this.start();
            }
        });
    },

    start() {
        if (! this.hasActiveImports()) {
            return;
        }

        void this.refresh();

        if (! this.timer) {
            this.timer = setInterval(() => this.refresh(), 3000);
        }
    },

    stop() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    },

    connectWebsocket() {
        if (! window.Echo || this.channel || ! this.wsChannel) {
            return;
        }

        this.channel = window.Echo.private(this.wsChannel)
            .listen(this.wsEvent, (event) => {
                if (! event.import) {
                    return;
                }

                this.mergeImport(event.import);
            });
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
            delete_progress: {},
            progress_label: '0 / -',
            detail_label: '0 imported - 0 failed - 0 duplicates',
            is_active: false,
        };
    },

    mergeImport(updated) {
        const index = this.imports.findIndex((item) => item.id === updated.id);

        if (index === -1) {
            return;
        }

        Object.assign(this.imports[index], updated);

        if (! this.hasActiveImports()) {
            this.stop();
        }
    },

    hasActiveImports() {
        return this.imports.some((item) => item.is_active);
    },

    async refresh() {
        if (this.inFlight) {
            return;
        }

        const ids = this.imports.filter((item) => item.is_active).map((item) => item.id);

        if (ids.length === 0) {
            this.stop();
            return;
        }

        this.inFlight = true;

        try {
            const separator = this.endpoint.includes('?') ? '&' : '?';
            const response = await fetch(`${this.endpoint}${separator}ids=${ids.join(',')}&_=${Date.now()}`, {
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });

            if (! response.ok) {
                return;
            }

            const payload = await response.json();

            payload.imports.forEach((updated) => this.mergeImport(updated));
        } finally {
            this.inFlight = false;
        }

        if (! this.hasActiveImports()) {
            this.stop();
        }
    },
}));

Alpine.start();
