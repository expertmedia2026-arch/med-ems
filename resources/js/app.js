

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('notificationBell', () => ({
    notificationsOpen: false,
    unreadCount: 0,
    notifications: [],
    latestUrl: null,
    refreshing: false,
    timer: null,
    visibilityListener: null,

    init() {
        this.unreadCount = Number(this.$el.dataset.unreadCount || 0);
        this.notifications = JSON.parse(this.$el.dataset.notifications || '[]');
        this.latestUrl = this.$el.dataset.latestUrl;

        this.timer = window.setInterval(() => {
            if (!document.hidden) this.refresh();
        }, 5000);

        this.visibilityListener = () => {
            if (!document.hidden) this.refresh();
        };
        document.addEventListener('visibilitychange', this.visibilityListener);
        this.refresh();
    },

    destroy() {
        window.clearInterval(this.timer);
        document.removeEventListener('visibilitychange', this.visibilityListener);
    },

    async refresh() {
        if (this.refreshing || !this.latestUrl) return;

        this.refreshing = true;

        try {
            const response = await fetch(this.latestUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) return;

            const data = await response.json();
            this.unreadCount = Number(data.unread_count || 0);
            this.notifications = data.notifications || [];
        } catch (error) {
            // Keep showing the last known notifications while the network is unavailable.
        } finally {
            this.refreshing = false;
        }
    },

    async markAllRead(event) {
        const form = event.currentTarget;
        const token = new FormData(form).get('_token');

        try {
            const response = await fetch(form.action, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                credentials: 'same-origin',
            });

            if (!response.ok) return;

            this.unreadCount = 0;
            this.notifications = this.notifications.map((notification) => ({ ...notification, unread: false }));
        } catch (error) {
            // Leave the form's state alone if the request could not reach the server.
        }
    },
}));

Alpine.start();
