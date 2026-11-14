(function (window, $) {
  'use strict';

  // ///////////////////////////////////////////////////////////////////////////
  // Ticketio WebSocket Client
  // ///////////////////////////////////////////////////////////////////////////

  class TicketioWebSocket {

    // /////////////////////////////////////////////////////////////////////////
    // Constructor
    // /////////////////////////////////////////////////////////////////////////

    constructor(options = {}) {
      this.settings = {
        endpoint: '/auth/websocket-ticket',
        csrf: '',
        ...options
      };

      this.socket = null;
      this.authenticated = false;
      this.connecting = false;
      this.stopped = false;
      this.attempts = 0;
      this.reconnectTimer = null;
      this.requestController = null;

      this.connect();
    }

    // /////////////////////////////////////////////////////////////////////////
    // Authentication
    // /////////////////////////////////////////////////////////////////////////

    /**
     * Request a single-use authentication ticket using the current
     * Ticketio PHP session.
     */
    async getTicket() {
      this.requestController = new AbortController();

      const response = await fetch(this.settings.endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        redirect: 'error',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': this.settings.csrf
        },
        signal: this.requestController.signal
      });

      // Authentication expired or CSRF validation failed.
      if (response.status === 401 || response.status === 403) {
        this.stop();

        $(document).trigger('ticketio:websocket:unauthorized');

        return null;
      }

      if (!response.ok) {
        throw new Error(
          `Ticket request failed: HTTP ${response.status}`
        );
      }

      const result = await response.json();

      if (
        result.success !== true ||
        typeof result.ticket !== 'string'
      ) {
        throw new Error('Invalid WebSocket ticket response.');
      }

      return result.ticket;
    }

    // /////////////////////////////////////////////////////////////////////////
    // Connection
    // /////////////////////////////////////////////////////////////////////////

    /**
     * Connect to Workerman and authenticate using a fresh ticket.
     */
    async connect() {
      if (this.stopped || this.connecting) {
        return;
      }

      if (
        this.socket &&
        this.socket.readyState !== WebSocket.CLOSED
      ) {
        return;
      }

      this.connecting = true;

      try {
        const ticket = await this.getTicket();

        if (!ticket || this.stopped) {
          return;
        }

        const protocol = window.location.protocol === 'https:'
          ? 'wss:'
          : 'ws:';

        const url = `${protocol}//${window.location.host}/ws`;

        const socket = new WebSocket(url);
        this.socket = socket;

        socket.addEventListener('open', () => {
          if (this.stopped) {
            socket.close();
            return;
          }

          socket.send(JSON.stringify({
            type: 'authenticate',
            ticket: ticket
          }));
        });

        socket.addEventListener('message', event => {
          this.receive(event);
        });

        socket.addEventListener('close', event => {
          if (this.socket !== socket) {
            return;
          }

          this.socket = null;
          this.authenticated = false;

          $(document).trigger(
            'ticketio:websocket:closed',
            [event]
          );

          if (!this.stopped) {
            this.scheduleReconnect();
          }
        });

        socket.addEventListener('error', () => {
          $(document).trigger('ticketio:websocket:error');
        });

      } catch (error) {
        if (!this.stopped) {
          console.error('Ticketio WebSocket:', error);
          this.scheduleReconnect();
        }
      } finally {
        this.connecting = false;
        this.requestController = null;
      }
    }

    // /////////////////////////////////////////////////////////////////////////
    // Events
    // /////////////////////////////////////////////////////////////////////////

    /**
     * Process events received from Workerman.
     */
    receive(event) {
      let payload;

      try {
        payload = JSON.parse(event.data);
      } catch (error) {
        console.warn('Invalid Ticketio event:', error);
        return;
      }

      if (
        !payload ||
        typeof payload !== 'object' ||
        typeof payload.type !== 'string'
      ) {
        return;
      }

      // Authentication acknowledgement.
      if (payload.type === 'connection.authenticated') {
        this.authenticated = true;
        this.attempts = 0;

        $(document).trigger(
          'ticketio:websocket:connected',
          [payload]
        );

        return;
      }

      if (!this.authenticated) {
        return;
      }

      // Global event hook.
      $(document).trigger(
        'ticketio:event',
        [payload]
      );

      // Event-specific hook.
      $(document).trigger(
        `ticketio:event:${payload.type}`,
        [payload]
      );

      // Bootstrap notification.
      if (
        payload.type === 'notification.created' &&
        payload.data
      ) {
        window.TicketioToast?.show(payload.data);

        $(document).trigger(
          'ticketio:notification',
          [payload]
        );
      }
    }

    // /////////////////////////////////////////////////////////////////////////
    // Reconnection
    // /////////////////////////////////////////////////////////////////////////

    /**
     * Reconnect with a new one-time ticket.
     */
    scheduleReconnect() {
      if (
        this.stopped ||
        this.reconnectTimer !== null
      ) {
        return;
      }

      this.attempts++;

      const delay = Math.min(
        30000,
        1000 * Math.pow(
          2,
          Math.min(this.attempts - 1, 5)
        )
      );

      const jitter = Math.floor(Math.random() * 500);

      this.reconnectTimer = window.setTimeout(() => {
        this.reconnectTimer = null;
        this.connect();
      }, delay + jitter);
    }

    // /////////////////////////////////////////////////////////////////////////
    // Disconnection
    // /////////////////////////////////////////////////////////////////////////

    /**
     * Stop all activity and close the WebSocket.
     */
    stop() {
      this.stopped = true;
      this.authenticated = false;

      if (this.reconnectTimer !== null) {
        window.clearTimeout(this.reconnectTimer);
        this.reconnectTimer = null;
      }

      if (this.requestController) {
        this.requestController.abort();
      }

      if (this.socket) {
        this.socket.close(1000, 'Ticketio disconnected');
        this.socket = null;
      }
    }

  }

  // ///////////////////////////////////////////////////////////////////////////
  // Initialise on Authenticated Pages
  // ///////////////////////////////////////////////////////////////////////////

  $(function () {
    const config = document.getElementById('ticketioRealtime');

    if (!config) {
      return;
    }

    if (!config.dataset.csrf) {
      console.warn('Ticketio WebSocket: CSRF token missing.');
      return;
    }

    window.ticketioWebSocket = new TicketioWebSocket({
      endpoint: config.dataset.ticketEndpoint,
      csrf: config.dataset.csrf
    });

    // Stop reconnecting when leaving the page.
    window.addEventListener('pagehide', () => {
      window.ticketioWebSocket?.stop();
    }, { once: true });

    // Stop immediately when the user submits the logout form.
    $('form[action="/logout"]').on('submit', function () {
      window.ticketioWebSocket?.stop();
    });
  });

  window.TicketioWebSocket = TicketioWebSocket;

})(window, jQuery);
