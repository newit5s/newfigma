(function ($) {
  'use strict';

  const safeNumber = (value, precision = 0) => {
    const number = Number(value);
    if (Number.isNaN(number)) {
      return (0).toFixed(precision);
    }
    return number.toFixed(precision);
  };

  const formatTrend = (value) => {
    const number = Number(value);
    if (Number.isNaN(number) || number === 0) {
      return '0%';
    }
    const sign = number > 0 ? '+' : '';
    return `${sign}${number}%`;
  };

  class ModernAdmin {
    constructor() {
      this.$root = $('#rb-admin-dashboard-root');
      this.$loading = $('#rb-admin-loading');
      this.ajaxUrl = window.rbAdmin ? window.rbAdmin.ajax_url : '';
      this.nonce = window.rbAdmin ? window.rbAdmin.nonce : '';
      this.strings = window.rbAdmin && window.rbAdmin.strings ? window.rbAdmin.strings : {};

      if (!this.$root.length) {
        return;
      }

      this.bindThemeToggle();
      this.loadDashboard();
    }

    bindThemeToggle() {
      const $toggle = $('#rb-admin-theme-toggle');
      if (!$toggle.length || !window.rbThemeManager) {
        return;
      }

      $toggle.on('click', (event) => {
        event.preventDefault();
        window.rbThemeManager.toggleTheme();
      });
    }

    showLoading(state) {
      if (!this.$loading.length) {
        return;
      }
      this.$loading.toggle(Boolean(state));
    }

    loadDashboard() {
      if (!this.ajaxUrl) {
        return;
      }

      this.showLoading(true);

      $.ajax({
        url: this.ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data: {
          action: 'rb_admin_get_dashboard',
          nonce: this.nonce,
        },
      })
        .done((response) => {
          if (response && response.success && response.data) {
            this.renderDashboard(response.data);
          } else {
            const message = response && response.data && response.data.message
              ? response.data.message
              : this.strings.error || 'Unable to load dashboard data.';
            this.renderError(message);
          }
        })
        .fail(() => {
          this.renderError(this.strings.error || 'Unable to load dashboard data.');
        })
        .always(() => {
          this.showLoading(false);
        });
    }

    renderDashboard(data) {
      const locations = Array.isArray(data.locations) ? data.locations : [];

      if (!locations.length) {
        this.$root.html(
          '<div class="rb-admin-empty-state">' +
            '<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 2a5 5 0 110 10 5 5 0 010-10zm0 12c5.33 0 8 2.67 8 8H4c0-5.33 2.67-8 8-8z"/></svg>' +
            `<p>${this.strings.loading || 'No dashboard data available yet.'}</p>` +
          '</div>'
        );
        return;
      }

      const cards = locations.map((location) => this.renderLocationCard(location));

      this.$root.html(
        '<div class="rb-admin-grid cols-2" id="rb-admin-dashboard-cards">' +
          cards.join('') +
        '</div>'
      );
    }

    renderLocationCard(location) {
      const stats = location && location.stats ? location.stats : {};
      const bookingsTrendClass = (stats.bookings_trend || 0) >= 0 ? 'is-positive' : 'is-negative';
      const revenueTrendClass = (stats.revenue_trend || 0) >= 0 ? 'is-positive' : 'is-negative';
      const occupancyTrendClass = (stats.occupancy_trend || 0) >= 0 ? 'is-positive' : 'is-negative';

      return `
        <section class="rb-admin-panel" aria-labelledby="rb-location-${location.id}-title">
          <header class="rb-admin-panel-header">
            <h2 class="rb-admin-title" id="rb-location-${location.id}-title">${this.escape(location.name || 'Location')}</h2>
            <div class="rb-admin-stat-change ${bookingsTrendClass}">${formatTrend(stats.bookings_trend || 0)}</div>
          </header>
          <div class="rb-admin-grid cols-3 rb-admin-stats">
            <div class="rb-admin-stat-card">
              <span class="rb-admin-stat-label">${this.escape(this.strings.bookings || 'Bookings')}</span>
              <span class="rb-admin-stat-value">${safeNumber(stats.bookings || 0)}</span>
              <span class="rb-admin-stat-change ${bookingsTrendClass}">${formatTrend(stats.bookings_trend || 0)}</span>
            </div>
            <div class="rb-admin-stat-card">
              <span class="rb-admin-stat-label">${this.escape(this.strings.revenue || 'Revenue')}</span>
              <span class="rb-admin-stat-value">${this.escape(stats.revenue || '$0')}</span>
              <span class="rb-admin-stat-change ${revenueTrendClass}">${formatTrend(stats.revenue_trend || 0)}</span>
            </div>
            <div class="rb-admin-stat-card">
              <span class="rb-admin-stat-label">${this.escape(this.strings.occupancy || 'Occupancy')}</span>
              <span class="rb-admin-stat-value">${safeNumber(stats.occupancy || 0)}%</span>
              <span class="rb-admin-stat-change ${occupancyTrendClass}">${formatTrend(stats.occupancy_trend || 0)}</span>
            </div>
          </div>
          <div class="rb-admin-card-list">
            <div class="rb-admin-activity-item">
              <div class="rb-admin-activity-icon">🪑</div>
              <div>
                <div class="rb-admin-stat-label">${this.escape(this.strings.tables || 'Tables')}</div>
                <div class="rb-admin-stat-value">${safeNumber(stats.tables || 0)}</div>
                <div class="rb-admin-activity-meta">${this.escape(this.strings.tablesHelp || 'Total tables in this location')}</div>
              </div>
            </div>
            <div class="rb-admin-activity-item">
              <div class="rb-admin-activity-icon">📅</div>
              <div>
                <div class="rb-admin-stat-label">${this.escape(this.strings.pending || 'Pending')}</div>
                <div class="rb-admin-stat-value">${safeNumber(stats.pending || 0)}</div>
                <div class="rb-admin-activity-meta">${this.escape(this.strings.pendingHelp || 'Awaiting confirmation')}</div>
              </div>
            </div>
          </div>
        </section>
      `;
    }

    renderError(message) {
      this.$root.html(
        `<div class="rb-admin-alert is-error" role="alert">${this.escape(message)}</div>`
      );
    }

    escape(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }
  }

  $(document).ready(() => {
    if ($('#rb-admin-dashboard-root').length) {
      window.rbModernAdmin = new ModernAdmin();
    }
  });
})(jQuery);
