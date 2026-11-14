(function (window) {
  'use strict';

  // ///////////////////////////////////////////////////////////////////////////
  // Ticketio Toast Notifications
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Displays Bootstrap 5.3 toast notifications using Ticketio's
   * shared light and dark theme.
   */
  class TicketioToast {

    // /////////////////////////////////////////////////////////////////////////
    // Configuration
    // /////////////////////////////////////////////////////////////////////////

    static maxVisible = 4;
    static delay = 6000;

    // /////////////////////////////////////////////////////////////////////////
    // Display
    // /////////////////////////////////////////////////////////////////////////

    /**
     * Display a notification.
     *
     * @param {Object} options
     */
    static show(options = {}) {
      const container = document.getElementById('ticketioToastContainer');

      if (!container || !window.bootstrap?.Toast) {
        return;
      }

      const settings = {
        title: 'Ticketio',
        message: '',
        source: 'Ticketio',
        level: 'info',
        delay: this.delay,
        ...options
      };

      // --- Normalise Notification Type ---
      const allowed = ['success', 'info', 'warning', 'danger'];

      let level = String(settings.level).toLowerCase();

      if (level === 'error') {
        level = 'danger';
      }

      if (!allowed.includes(level)) {
        level = 'info';
      }

      // --- Limit Visible Notifications ---
      const existing = container.querySelectorAll('.toast');

      if (existing.length >= this.maxVisible) {
        const oldest = existing[existing.length - 1];

        window.bootstrap.Toast.getInstance(oldest)?.dispose();
        oldest.remove();
      }

      // --- Create Toast ---
      const toast = document.createElement('div');

      toast.className = `toast ticketio-toast-${level}`;

      toast.setAttribute('role', level === 'danger' ? 'alert' : 'status');
      toast.setAttribute(
        'aria-live',
        level === 'danger' ? 'assertive' : 'polite'
      );
      toast.setAttribute('aria-atomic', 'true');

      // --- Header ---
      const header = document.createElement('div');
      header.className = 'toast-header';

      // --- Notification Indicator ---
      const indicator = document.createElement('span');
      indicator.className = 'ticketio-toast-indicator';
      indicator.setAttribute('aria-hidden', 'true');

      // --- Title ---
      const title = document.createElement('strong');
      title.className = 'me-auto';
      title.textContent = String(settings.title ?? 'Ticketio');

      // --- Source ---
      const source = document.createElement('small');
      source.textContent = String(settings.source ?? '');

      // --- Close Button ---
      const close = document.createElement('button');

      close.type = 'button';
      close.className = 'btn-close';
      close.setAttribute('data-bs-dismiss', 'toast');
      close.setAttribute('aria-label', 'Dismiss notification');

      // --- Body ---
      const body = document.createElement('div');
      body.className = 'toast-body';
      body.textContent = String(settings.message ?? '');

      // --- Assemble ---
      header.append(
        indicator,
        title,
        source,
        close
      );

      toast.append(header, body);

      container.prepend(toast);

      // --- Initialise Bootstrap ---
      const delay = Number(settings.delay);

      const instance = window.bootstrap.Toast.getOrCreateInstance(
        toast,
        {
          animation: true,
          autohide: true,
          delay: Number.isFinite(delay) && delay >= 0
            ? delay
            : this.delay
        }
      );

      // --- Cleanup ---
      toast.addEventListener('hidden.bs.toast', () => {
        instance.dispose();
        toast.remove();
      }, { once: true });

      // --- Display ---
      instance.show();

      return toast;
    }

    // /////////////////////////////////////////////////////////////////////////
    // Notification Shortcuts
    // /////////////////////////////////////////////////////////////////////////

    /**
     * Display a success notification.
     */
    static success(title, message, source = 'Ticketio') {
      return this.show({
        title,
        message,
        source,
        level: 'success'
      });
    }

    /**
     * Display an informational notification.
     */
    static info(title, message, source = 'Ticketio') {
      return this.show({
        title,
        message,
        source,
        level: 'info'
      });
    }

    /**
     * Display a warning notification.
     */
    static warning(title, message, source = 'Ticketio') {
      return this.show({
        title,
        message,
        source,
        level: 'warning'
      });
    }

    /**
     * Display an error notification.
     */
    static error(title, message, source = 'Ticketio') {
      return this.show({
        title,
        message,
        source,
        level: 'danger'
      });
    }

    // /////////////////////////////////////////////////////////////////////////
    // Dismiss Notifications
    // /////////////////////////////////////////////////////////////////////////

    /**
     * Dismiss all visible notifications.
     */
    static clear() {
      const container = document.getElementById('ticketioToastContainer');

      if (!container) {
        return;
      }

      container.querySelectorAll('.toast').forEach(toast => {
        const instance = window.bootstrap.Toast.getInstance(toast);

        if (instance) {
          instance.hide();
        } else {
          toast.remove();
        }
      });
    }

  }

  // ///////////////////////////////////////////////////////////////////////////
  // Global Service
  // ///////////////////////////////////////////////////////////////////////////

  window.TicketioToast = TicketioToast;

})(window);
