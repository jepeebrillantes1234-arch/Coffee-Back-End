import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';


if (window.Echo) {
    const reportConnection = connected => window.dispatchEvent(new CustomEvent('live-connection', {
        detail: { connected },
    }));

    const channel = window.Echo.private('staff.updates');
    channel.subscribed(() => reportConnection(true));
    channel.error(() => reportConnection(false));
    channel.listen('.live.updated', event => {
        window.dispatchEvent(new CustomEvent('live-update', { detail: event }));
    });

    // A socket can drop after the channel was authorized. Keep the UI honest
    // and let the orders page use its lightweight partial fallback meanwhile.
    window.Echo.connector.pusher.connection.bind('state_change', state => {
        if (state.current !== 'connected') reportConnection(false);
    });
}
