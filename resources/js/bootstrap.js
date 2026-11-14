import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';


const dispatchConnectionState = connected => {
    window.staffLiveConnected = connected;
    window.dispatchEvent(new CustomEvent('live-connection', {
        detail: { connected },
    }));
};

if (!window.Echo) {
    dispatchConnectionState(false);
} else {
    const connection = window.Echo.connector.pusher.connection;
    let socketConnected = connection.state === 'connected';
    let channelSubscribed = false;
    let lastReportedState = null;

    const reportConnection = () => {
        const connected = socketConnected && channelSubscribed;
        if (connected === lastReportedState) return;
        lastReportedState = connected;
        dispatchConnectionState(connected);
    };

    // This bootstrap owns the only staff.updates subscription. Report online
    // only when both the socket and private channel are ready.
    connection.bind('state_change', state => {
        socketConnected = state.current === 'connected';
        if (!socketConnected) channelSubscribed = false;
        reportConnection();
    });

    const channel = window.Echo.private('staff.updates');
    channel.subscribed(() => {
        channelSubscribed = true;
        reportConnection();
    });
    channel.error(() => {
        channelSubscribed = false;
        reportConnection();
    });
    channel.listen('.live.updated', event => {
        window.dispatchEvent(new CustomEvent('live-update', { detail: event }));
    });

    reportConnection();
}
