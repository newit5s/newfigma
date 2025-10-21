(function ($) {
  'use strict';

  const toNumber = (value, fallback = 0) => {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
  };

  const formatTrend = (value) => {
    const number = toNumber(value, 0);
    if (number === 0) {
      return '0%';
    }
    const sign = number > 0 ? '+' : '';
    return `${sign}${number}%`;
  };

  const formatNumber = (value) => {
    return new Intl.NumberFormat().format(toNumber(value, 0));
  };

  const formatPercent = (value, fractionDigits = 0) => {
    const number = toNumber(value, 0);
    return `${number.toFixed(fractionDigits)}%`;
  };

  const formatDateTime = (date, time, options = {}) => {
    if (!date) {
      return '';
    }
    const safeTime = time || '00:00:00';
    const isoString = `${date}T${safeTime}`;
    const parsed = new Date(isoString);
    if (Number.isNaN(parsed.getTime())) {
      return date;
    }
    const formatter = new Intl.DateTimeFormat(undefined, Object.assign({
      dateStyle: 'medium',
      timeStyle: 'short',
    }, options));
    return formatter.format(parsed);
  };

  const getToday = () => {
    const today = new Date();
    const month = `${today.getMonth() + 1}`.padStart(2, '0');
    const day = `${today.getDate()}`.padStart(2, '0');
    return `${today.getFullYear()}-${month}-${day}`;
  };

  class ModernAdmin {
    constructor() {
      const defaults = {
        strings: {},
        bookings: {},
        currency: {},
        badges: {},
      };

      const localized = window.rbAdmin ? Object.assign({}, defaults, window.rbAdmin) : defaults;

      this.ajaxUrl = localized.ajax_url || '';
      this.nonce = localized.nonce || '';
      this.strings = Object.assign({
        loading: 'Loading…',
        error: 'Error loading data',
        bookings: 'Bookings',
        revenue: 'Revenue',
        occupancy: 'Occupancy',
        tables: 'Tables',
        tablesHelp: 'Total tables in this location',
        pending: 'Pending',
        pendingHelp: 'Awaiting confirmation',
        emptyBookings: 'No bookings match the current filters.',
        locationsEmpty: 'No locations available yet.',
        settingsSaved: 'Settings saved successfully.',
        settingsReset: 'Settings restored to defaults.',
        locationSaved: 'Location details saved.',
        locationReset: 'Location form reset.',
        peakTime: 'Peak dining time',
        sentiment: 'Guest sentiment',
      }, localized.strings || {});

      this.bookingsConfig = Object.assign({
        perPage: 20,
        statusLabels: {
          pending: this.strings.pending,
          confirmed: 'Confirmed',
          completed: 'Completed',
          cancelled: 'Cancelled',
        },
      }, localized.bookings || {});

      this.currency = Object.assign({
        code: 'USD',
        symbol: '$',
      }, localized.currency || {});

      this.badges = Object.assign({}, localized.badges || {});

      this.dashboardData = null;
      this.locationsDirectory = null;
      this.bookingsState = null;
      this.reportState = {
        range: localized.reports && localized.reports.defaultRange ? Number(localized.reports.defaultRange) : 30,
        interval: 'month',
      };

      this.themeManager = window.rbThemeManager || null;

      this.init();
    }

    init() {
      this.bindThemeToggle();
      this.initDashboard();
      this.initBookings();
      this.initLocations();
      this.initSettings();
      this.initReports();
    }

    request(action, payload = {}) {
      if (!this.ajaxUrl) {
        return $.Deferred(function (deferred) {
          deferred.reject();
        }).promise();
      }

      const data = Object.assign({
        action,
        nonce: this.nonce,
      }, payload);

      return $.ajax({
        url: this.ajaxUrl,
        type: 'POST',
        dataType: 'json',
        data,
      });
    }

    toggleOverlay($overlay, state) {
      if (!$overlay || !$overlay.length) {
        return;
      }
      const visible = Boolean(state);
      $overlay.toggleClass('is-active', visible);
      $overlay.attr('aria-busy', visible ? 'true' : 'false');
    }

    escape(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    formatCurrency(value) {
      const number = toNumber(value, 0);
      try {
        if (this.currency && this.currency.code) {
          const formatter = new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: this.currency.code,
          });
          return formatter.format(number);
        }
      } catch (error) {
        // fall through
      }
      const symbol = this.currency && this.currency.symbol ? this.currency.symbol : '$';
      return `${symbol}${number.toFixed(2)}`;
    }

    updateMenuBadge(slug, count) {
      const normalized = Math.max(0, parseInt(count, 10) || 0);
      this.badges[slug] = normalized;
      const $link = $(`.rb-admin-menu-item[data-menu-item="${slug}"]`);
      if (!$link.length) {
        return;
      }
      let $badge = $link.find('.rb-admin-badge');
      if (normalized <= 0) {
        if ($badge.length) {
          $badge.remove();
        }
        return;
      }
      const label = `${normalized}`;
      if (!$badge.length) {
        $badge = $('<span>', {
          class: 'rb-admin-badge',
          text: label,
        });
        $link.append($badge);
      } else {
        $badge.text(label);
      }
      $badge.attr('aria-label', label);
    }

    /* --------------------------------------------------------------------- */
    /* Dashboard                                                             */
    /* --------------------------------------------------------------------- */

    bindThemeToggle() {
      const $toggle = $('#rb-admin-theme-toggle');
      if (!$toggle.length || !this.themeManager) {
        return;
      }
      $toggle.on('click', (event) => {
        event.preventDefault();
        this.themeManager.toggleTheme();
      });
    }

    initDashboard() {
      this.$dashboardRoot = $('#rb-admin-dashboard-root');
      if (!this.$dashboardRoot.length) {
        return;
      }
      this.$dashboardLoading = $('#rb-admin-loading');
      $('#rb-dashboard-refresh').on('click', (event) => {
        event.preventDefault();
        this.loadDashboard(true);
      });
      this.loadDashboard();
    }

    getDashboardData(force = false) {
      if (!force && this.dashboardData) {
        return $.Deferred().resolve(this.dashboardData).promise();
      }
      return this.request('rb_admin_get_dashboard').then((response) => {
        if (response && response.success && response.data) {
          this.dashboardData = response.data;
          return response.data;
        }
        throw new Error(response && response.data && response.data.message ? response.data.message : this.strings.error);
      });
    }

    loadDashboard(force = false) {
      this.toggleOverlay(this.$dashboardLoading, true);
      this.getDashboardData(force)
        .done((data) => {
          this.renderDashboard(data || {});
        })
        .fail(() => {
          this.renderDashboardError();
        })
        .always(() => {
          this.toggleOverlay(this.$dashboardLoading, false);
        });
    }

    renderDashboard(data) {
      const locations = Array.isArray(data.locations) ? data.locations : [];
      if (!locations.length) {
        this.$dashboardRoot.html(
          `<div class="rb-admin-empty-state">${this.escape(this.strings.locationsEmpty)}</div>`
        );
      } else {
        const cards = locations.map((location) => this.renderLocationCard(location));
        this.$dashboardRoot.html(cards.join(''));
      }

      const summary = data.summary || {};
      this.updateDashboardSummary(summary);
      this.updateDashboardInsights(summary);
    }

    renderDashboardError() {
      this.$dashboardRoot.html(
        `<div class="rb-admin-alert is-error">${this.escape(this.strings.error)}</div>`
      );
    }

    renderLocationCard(location) {
      const stats = location && location.stats ? location.stats : {};
      const bookings = toNumber(stats.bookings, 0);
      const bookingsTrend = toNumber(stats.bookings_trend, 0);
      const revenueValue = toNumber(stats.revenue_value || stats.revenue_raw, 0);
      const revenueTrend = toNumber(stats.revenue_trend, 0);
      const occupancy = toNumber(stats.occupancy, 0);
      const occupancyTrend = toNumber(stats.occupancy_trend, 0);
      const tables = toNumber(stats.tables, 0);
      const pending = toNumber(stats.pending, 0);

      const bookingsTrendClass = bookingsTrend >= 0 ? 'is-positive' : 'is-negative';
      const revenueTrendClass = revenueTrend >= 0 ? 'is-positive' : 'is-negative';
      const occupancyTrendClass = occupancyTrend >= 0 ? 'is-positive' : 'is-negative';

      const revenueLabel = stats.revenue ? stats.revenue : this.formatCurrency(revenueValue);

      return `
        <article class="rb-admin-panel rb-admin-location-card" data-location-id="${this.escape(location.id || '')}">
          <header class="rb-admin-panel-header">
            <div>
              <h3 class="rb-admin-title">${this.escape(location.name || 'Location')}</h3>
              <p class="rb-admin-panel-subtitle">${this.escape(location.address || '')}</p>
            </div>
            <div class="rb-admin-stat-change ${bookingsTrendClass}">${formatTrend(bookingsTrend)}</div>
          </header>
          <div class="rb-admin-grid cols-3">
            <div class="rb-admin-stat-card">
              <span class="rb-admin-stat-label">${this.escape(this.strings.bookings)}</span>
              <span class="rb-admin-stat-value">${formatNumber(bookings)}</span>
              <span class="rb-admin-stat-change ${bookingsTrendClass}">${formatTrend(bookingsTrend)}</span>
            </div>
            <div class="rb-admin-stat-card">
              <span class="rb-admin-stat-label">${this.escape(this.strings.revenue)}</span>
              <span class="rb-admin-stat-value">${this.escape(revenueLabel)}</span>
              <span class="rb-admin-stat-change ${revenueTrendClass}">${formatTrend(revenueTrend)}</span>
            </div>
            <div class="rb-admin-stat-card">
              <span class="rb-admin-stat-label">${this.escape(this.strings.occupancy)}</span>
              <span class="rb-admin-stat-value">${formatPercent(occupancy)}</span>
              <span class="rb-admin-stat-change ${occupancyTrendClass}">${formatTrend(occupancyTrend)}</span>
            </div>
          </div>
          <div class="rb-admin-card-list">
            <div class="rb-admin-activity-item">
              <div class="rb-admin-activity-icon" aria-hidden="true">🪑</div>
              <div>
                <div class="rb-admin-stat-label">${this.escape(this.strings.tables)}</div>
                <div class="rb-admin-stat-value">${formatNumber(tables)}</div>
                <div class="rb-admin-activity-meta">${this.escape(this.strings.tablesHelp)}</div>
              </div>
            </div>
            <div class="rb-admin-activity-item">
              <div class="rb-admin-activity-icon" aria-hidden="true">⏳</div>
              <div>
                <div class="rb-admin-stat-label">${this.escape(this.strings.pending)}</div>
                <div class="rb-admin-stat-value">${formatNumber(pending)}</div>
                <div class="rb-admin-activity-meta">${this.escape(this.strings.pendingHelp)}</div>
              </div>
            </div>
          </div>
          <div class="rb-admin-actions">
            <a class="rb-btn rb-btn-outline" href="${this.escape(this.buildAdminUrl('rb-bookings', { location: location.id }))}">
              ${this.escape(this.strings.bookings)}
            </a>
            <a class="rb-btn rb-btn-outline" href="${this.escape(this.buildAdminUrl('rb-locations', { focus: location.id }))}">
              ${this.escape(this.strings.tables)}
            </a>
          </div>
        </article>
      `;
    }

    buildAdminUrl(page, params = {}) {
      const url = new URL(window.location.href);
      url.searchParams.set('page', page);
      Object.keys(params).forEach((key) => {
        if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
          url.searchParams.set(key, params[key]);
        }
      });
      return url.toString();
    }

    updateDashboardSummary(summary) {
      const bookingsTotal = toNumber(summary.total_bookings, 0);
      const revenueTotal = toNumber(summary.total_revenue, 0);
      const occupancyAverage = toNumber(summary.average_occupancy, 0);
      const bookingsChange = toNumber(summary.bookings_change, 0);
      const revenueChange = toNumber(summary.revenue_change, 0);
      const pendingTotal = toNumber(summary.pending_total, 0);

      $('#rb-admin-summary-bookings').text(formatNumber(bookingsTotal));
      $('#rb-admin-summary-bookings-change')
        .text(formatTrend(bookingsChange))
        .toggleClass('is-positive', bookingsChange >= 0)
        .toggleClass('is-negative', bookingsChange < 0);

      $('#rb-admin-summary-revenue').text(this.formatCurrency(revenueTotal));
      $('#rb-admin-summary-revenue-change')
        .text(formatTrend(revenueChange))
        .toggleClass('is-positive', revenueChange >= 0)
        .toggleClass('is-negative', revenueChange < 0);

      $('#rb-admin-summary-occupancy').text(formatPercent(occupancyAverage));
      $('#rb-admin-summary-occupancy-change')
        .text(formatTrend(toNumber(summary.occupancy_change, 0)))
        .toggleClass('is-positive', toNumber(summary.occupancy_change, 0) >= 0)
        .toggleClass('is-negative', toNumber(summary.occupancy_change, 0) < 0);

      this.updateMenuBadge('rb-bookings', pendingTotal);
    }

    updateDashboardInsights(summary) {
      if (!summary) {
        return;
      }
      $('#rb-admin-top-location').text(summary.top_location ? summary.top_location : '—');
      if (summary.top_location_bookings !== undefined) {
        $('#rb-admin-top-location-meta').text(
          `${formatNumber(summary.top_location_bookings)} ${this.strings.bookings.toLowerCase()}`
        );
      }
      $('#rb-admin-peak-time').text(summary.peak_time ? summary.peak_time : '—');
      $('#rb-admin-peak-time-meta').text(summary.peak_time_label ? summary.peak_time_label : '');
      $('#rb-admin-sentiment').text(summary.sentiment_score ? summary.sentiment_score : '—');
      $('#rb-admin-sentiment-meta').text(summary.recommendation ? summary.recommendation : '');
    }

    /* --------------------------------------------------------------------- */
    /* Bookings                                                              */
    /* --------------------------------------------------------------------- */

    initBookings() {
      this.$bookingsTableBody = $('#rb-admin-bookings-body');
      if (!this.$bookingsTableBody.length) {
        return;
      }

      this.$bookingsEmpty = $('#rb-admin-bookings-empty');
      this.$bookingsLoading = $('#rb-admin-bookings-loading');
      this.$bookingsPagination = $('#rb-admin-bookings-pagination');
      this.$bookingsSummary = $('#rb-bookings-summary');

      this.bookingsState = {
        status: '',
        location: '',
        date_from: '',
        date_to: '',
        search: '',
        per_page: this.bookingsConfig.perPage,
        page: 1,
      };

      this.bindBookingFilters();
      this.loadLocationsDirectory().always(() => {
        this.populateLocationFilter();
        this.loadBookings();
      });
    }

    bindBookingFilters() {
      $('#rb-bookings-status-filter').on('change', (event) => {
        this.bookingsState.status = event.target.value;
        this.bookingsState.page = 1;
        this.loadBookings();
      });

      $('#rb-bookings-location-filter').on('change', (event) => {
        this.bookingsState.location = event.target.value;
        this.bookingsState.page = 1;
        this.loadBookings();
      });

      $('#rb-bookings-date-from').on('change', (event) => {
        this.bookingsState.date_from = event.target.value;
        this.bookingsState.page = 1;
        this.loadBookings();
      });

      $('#rb-bookings-date-to').on('change', (event) => {
        this.bookingsState.date_to = event.target.value;
        this.bookingsState.page = 1;
        this.loadBookings();
      });

      const searchInput = $('#rb-bookings-search-filter');
      let searchTimer = null;
      searchInput.on('input', (event) => {
        const value = event.target.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
          this.bookingsState.search = value;
          this.bookingsState.page = 1;
          this.loadBookings();
        }, 300);
      });

      $('#rb-bookings-per-page').on('change', (event) => {
        const perPage = Math.max(1, parseInt(event.target.value, 10) || this.bookingsConfig.perPage);
        this.bookingsState.per_page = perPage;
        this.bookingsState.page = 1;
        this.loadBookings();
      });

      $('#rb-bookings-clear').on('click', (event) => {
        event.preventDefault();
        this.resetBookingFilters();
        this.loadBookings();
      });

      $('#rb-bookings-refresh').on('click', (event) => {
        event.preventDefault();
        this.loadBookings(true);
      });

      $('.rb-admin-quick-filter').on('click', (event) => {
        event.preventDefault();
        const status = $(event.currentTarget).data('status') || '';
        this.bookingsState.status = status;
        const today = getToday();
        if (status === 'pending') {
          this.bookingsState.date_from = today;
          this.bookingsState.date_to = today;
          $('#rb-bookings-date-from').val(today);
          $('#rb-bookings-date-to').val(today);
        }
        $('#rb-bookings-status-filter').val(status);
        this.bookingsState.page = 1;
        this.loadBookings();
      });

      this.$bookingsPagination.on('click', '[data-page]', (event) => {
        event.preventDefault();
        const direction = $(event.currentTarget).data('page');
        if (direction === 'prev' && this.bookingsState.page > 1) {
          this.bookingsState.page -= 1;
          this.loadBookings();
        }
        if (direction === 'next') {
          this.bookingsState.page += 1;
          this.loadBookings();
        }
      });

      this.$bookingsTableBody.on('click', '.rb-admin-table-actions button', (event) => {
        event.preventDefault();
        const $button = $(event.currentTarget);
        const bookingId = $button.data('id');
        const action = $button.data('action');
        window.console && window.console.info && window.console.info(`Booking action: ${action}`, bookingId);
      });
    }

    resetBookingFilters() {
      this.bookingsState = Object.assign({}, this.bookingsState, {
        status: '',
        location: '',
        date_from: '',
        date_to: '',
        search: '',
        page: 1,
      });
      $('#rb-bookings-status-filter').val('');
      $('#rb-bookings-location-filter').val('');
      $('#rb-bookings-date-from').val('');
      $('#rb-bookings-date-to').val('');
      $('#rb-bookings-search-filter').val('');
    }

    loadBookings(force = false) {
      if (this.bookingsRequest && this.bookingsRequest.abort) {
        this.bookingsRequest.abort();
      }

      this.toggleOverlay(this.$bookingsLoading, true);
      const payload = Object.assign({}, this.bookingsState);
      payload.per_page = Math.max(1, Math.min(100, parseInt(payload.per_page, 10) || this.bookingsConfig.perPage));
      payload.page = Math.max(1, parseInt(payload.page, 10) || 1);

      this.bookingsRequest = this.request('rb_admin_get_bookings', payload);
      this.bookingsRequest
        .done((response) => {
          if (response && response.success && response.data) {
            this.renderBookings(response.data);
          } else {
            this.renderBookingsError();
          }
        })
        .fail(() => {
          if (!force) {
            this.renderBookingsError();
          }
        })
        .always(() => {
          this.toggleOverlay(this.$bookingsLoading, false);
        });
    }

    renderBookings(data) {
      const items = Array.isArray(data.items) ? data.items : [];
      const pagination = data.pagination || {};
      const summary = data.summary || {};

      this.$bookingsTableBody.empty();

      if (!items.length) {
        this.$bookingsEmpty.removeAttr('hidden');
      } else {
        this.$bookingsEmpty.attr('hidden', 'hidden');
        const rows = items.map((item) => this.renderBookingRow(item));
        this.$bookingsTableBody.html(rows.join(''));
      }

      this.updateBookingsPagination(pagination, items.length);
      this.updateStatusCards(summary.status_counts || {});

      if (summary.status_counts && typeof summary.status_counts.pending !== 'undefined') {
        this.updateMenuBadge('rb-bookings', summary.status_counts.pending);
      }
    }

    renderBookingsError() {
      this.$bookingsTableBody.html(
        `<tr><td colspan="7"><div class="rb-admin-alert is-error">${this.escape(this.strings.error)}</div></td></tr>`
      );
      this.$bookingsEmpty.attr('hidden', 'hidden');
    }

    renderBookingRow(item) {
      const status = item.status || 'pending';
      const statusLabel = this.bookingsConfig.statusLabels[status] || status;
      const statusClass = `is-${status.replace(/[^a-z0-9_-]/gi, '').toLowerCase()}`;
      const tableLabel = item.table_number || (item.table_id ? `#${item.table_id}` : '—');
      const dateLabel = formatDateTime(item.booking_date, item.booking_time);
      const actions = `
        <div class="rb-admin-table-actions">
          <button type="button" data-action="view" data-id="${this.escape(item.id)}">View</button>
          <button type="button" data-action="confirm" data-id="${this.escape(item.id)}">Confirm</button>
        </div>
      `;

      return `
        <tr>
          <td>
            <div>${this.escape(item.customer_name || '—')}</div>
            <small class="rb-text-muted">${this.escape(item.customer_phone || item.customer_email || '')}</small>
          </td>
          <td>${this.escape(dateLabel || '—')}</td>
          <td>${formatNumber(item.party_size || 0)}</td>
          <td>${this.escape(tableLabel)}</td>
          <td>${this.escape(item.location_name || '—')}</td>
          <td><span class="rb-admin-status-badge ${statusClass}">${this.escape(statusLabel)}</span></td>
          <td class="rb-admin-column-actions">${actions}</td>
        </tr>
      `;
    }

    updateBookingsPagination(pagination, pageItems) {
      const currentPage = Math.max(1, parseInt(pagination.current_page, 10) || this.bookingsState.page);
      const totalPages = Math.max(1, parseInt(pagination.total_pages, 10) || 1);
      const totalItems = Math.max(0, parseInt(pagination.total_items, 10) || 0);
      const perPage = Math.max(1, parseInt(this.bookingsState.per_page, 10) || this.bookingsConfig.perPage);

      this.bookingsState.page = Math.min(currentPage, totalPages);

      const start = totalItems === 0 ? 0 : (this.bookingsState.page - 1) * perPage + 1;
      const end = totalItems === 0 ? 0 : start + pageItems - 1;

      this.$bookingsSummary.text(
        totalItems === 0
          ? this.strings.emptyBookings
          : `${formatNumber(start)}–${formatNumber(end)} / ${formatNumber(totalItems)}`
      );

      const $buttons = this.$bookingsPagination.find('[data-page]');
      $buttons.filter('[data-page="prev"]').prop('disabled', this.bookingsState.page <= 1);
      $buttons.filter('[data-page="next"]').prop('disabled', this.bookingsState.page >= totalPages);
      $('#rb-admin-bookings-page-state').text(`${formatNumber(this.bookingsState.page)} / ${formatNumber(totalPages)}`);
    }

    updateStatusCards(counts) {
      $('#rb-admin-status-confirmed').text(formatNumber(counts.confirmed || 0));
      $('#rb-admin-status-pending').text(formatNumber(counts.pending || 0));
      $('#rb-admin-status-completed').text(formatNumber(counts.completed || 0));
      $('#rb-admin-status-cancelled').text(formatNumber(counts.cancelled || 0));
    }

    populateLocationFilter() {
      const directory = this.locationsDirectory;
      if (!directory || !Array.isArray(directory.locations)) {
        return;
      }
      const $select = $('#rb-bookings-location-filter');
      if (!$select.length) {
        return;
      }
      const current = this.bookingsState.location;
      const options = directory.locations
        .map((location) => `<option value="${this.escape(location.id)}">${this.escape(location.name)}</option>`)
        .join('');
      $select.find('option:not(:first-child)').remove();
      $select.append(options);
      if (current) {
        $select.val(current);
      }
    }

    loadLocationsDirectory(force = false) {
      if (!force && this.locationsDirectory) {
        return $.Deferred().resolve(this.locationsDirectory).promise();
      }
      return this.request('rb_admin_get_locations').then((response) => {
        if (response && response.success && response.data) {
          const locations = Array.isArray(response.data.locations) ? response.data.locations : [];
          const summary = response.data.summary || {};
          this.locationsDirectory = { locations, summary };
          return this.locationsDirectory;
        }
        throw new Error(response && response.data && response.data.message ? response.data.message : this.strings.error);
      });
    }

    /* --------------------------------------------------------------------- */
    /* Locations                                                             */
    /* --------------------------------------------------------------------- */

    initLocations() {
      this.$locationsRoot = $('#rb-admin-locations-root');
      if (!this.$locationsRoot.length) {
        return;
      }
      this.$locationsLoading = $('#rb-admin-locations-loading');
      this.$locationsSummary = $('#rb-admin-location-capacity');
      this.$locationForm = $('#rb-admin-location-form');
      this.$locationUnsaved = $('#rb-location-unsaved');
      this.$locationSave = $('#rb-location-save');
      this.$locationReset = $('#rb-location-reset');
      this.activeLocation = null;

      this.bindLocationForm();
      this.loadLocations();
    }

    loadLocations() {
      this.toggleOverlay(this.$locationsLoading, true);

      $.when(this.loadLocationsDirectory(true), this.getDashboardData(true))
        .done((directory, dashboard) => {
          const locations = directory.locations || [];
          const summary = directory.summary || {};
          this.renderLocations(locations, dashboard && dashboard.locations ? dashboard.locations : []);
          this.updateLocationSummary(summary);
        })
        .fail(() => {
          this.$locationsRoot.html(
            `<div class="rb-admin-alert is-error">${this.escape(this.strings.error)}</div>`
          );
        })
        .always(() => {
          this.toggleOverlay(this.$locationsLoading, false);
        });
    }

    renderLocations(locations, dashboardLocations) {
      const statsMap = {};
      if (Array.isArray(dashboardLocations)) {
        dashboardLocations.forEach((location) => {
          statsMap[location.id] = location.stats || {};
        });
      }

      if (!locations.length) {
        this.$locationsRoot.html(
          `<div class="rb-admin-empty-state">${this.escape(this.strings.locationsEmpty)}</div>`
        );
        return;
      }

      const cards = locations.map((location) => {
        const stats = statsMap[location.id] || {};
        const bookings = formatNumber(stats.bookings || 0);
        const occupancy = formatPercent(stats.occupancy || 0);
        const status = location.status ? location.status : 'active';
        return `
          <article class="rb-admin-panel rb-admin-location-card" data-location-id="${this.escape(location.id)}">
            <header class="rb-admin-panel-header">
              <div>
                <h3 class="rb-admin-title">${this.escape(location.name)}</h3>
                <p class="rb-admin-panel-subtitle">${this.escape(location.address || '')}</p>
              </div>
              <span class="rb-admin-stat-change">${this.escape(status)}</span>
            </header>
            <div class="rb-admin-card-list">
              <div class="rb-admin-activity-item">
                <div class="rb-admin-activity-icon" aria-hidden="true">📞</div>
                <div>
                  <div class="rb-admin-stat-label">Contact</div>
                  <div>${this.escape(location.phone || '—')}</div>
                  <div class="rb-admin-activity-meta">${this.escape(location.email || '')}</div>
                </div>
              </div>
              <div class="rb-admin-activity-item">
                <div class="rb-admin-activity-icon" aria-hidden="true">🪑</div>
                <div>
                  <div class="rb-admin-stat-label">Capacity</div>
                  <div>${formatNumber(location.capacity || 0)} seats</div>
                  <div class="rb-admin-activity-meta">${formatNumber(location.tables || stats.tables || 0)} tables</div>
                </div>
              </div>
              <div class="rb-admin-activity-item">
                <div class="rb-admin-activity-icon" aria-hidden="true">📊</div>
                <div>
                  <div class="rb-admin-stat-label">Today</div>
                  <div>${bookings} ${this.strings.bookings.toLowerCase()}</div>
                  <div class="rb-admin-activity-meta">Occupancy ${occupancy}</div>
                </div>
              </div>
            </div>
          </article>
        `;
      });

      this.$locationsRoot.html(cards.join(''));

      this.$locationsRoot.find('.rb-admin-location-card').on('click', (event) => {
        const id = $(event.currentTarget).data('location-id');
        const location = (this.locationsDirectory.locations || []).find((item) => String(item.id) === String(id));
        if (location) {
          this.populateLocationForm(location);
        }
      });
    }

    updateLocationSummary(summary) {
      $('#rb-admin-total-tables').text(formatNumber(summary.total_tables || 0));
      $('#rb-admin-total-seats').text(formatNumber(summary.total_seats || 0));
      $('#rb-admin-open-locations').text(formatNumber(summary.open_locations || 0));
    }

    bindLocationForm() {
      if (!this.$locationForm.length) {
        return;
      }

      this.$locationForm.on('input change', 'input, textarea, select', () => {
        if (!this.activeLocation) {
          return;
        }
        this.setLocationFormDirty(true);
      });

      this.$locationSave.on('click', (event) => {
        event.preventDefault();
        this.setLocationFormDirty(false);
        this.showLocationNotice(this.strings.locationSaved);
      });

      this.$locationReset.on('click', (event) => {
        event.preventDefault();
        if (this.activeLocation) {
          this.populateLocationForm(this.activeLocation, false);
        }
        this.showLocationNotice(this.strings.locationReset);
      });
    }

    populateLocationForm(location, markDirty = false) {
      this.activeLocation = Object.assign({}, location);
      this.$locationForm.find('#rb-location-name').val(location.name || '');
      this.$locationForm.find('#rb-location-email').val(location.email || '');
      this.$locationForm.find('#rb-location-phone').val(location.phone || '');
      this.$locationForm.find('#rb-location-address').val(location.address || '');
      this.$locationForm.find('#rb-location-hours-weekday').val(location.hours_weekday || '');
      this.$locationForm.find('#rb-location-hours-weekend').val(location.hours_weekend || '');
      this.$locationForm.find('#rb-location-waitlist').prop('checked', Boolean(location.waitlist_enabled));
      this.$locationForm.find('#rb-location-private').prop('checked', location.status === 'private');
      this.setLocationFormDirty(markDirty);
    }

    setLocationFormDirty(isDirty) {
      this.$locationSave.prop('disabled', !isDirty);
      this.$locationReset.prop('disabled', !isDirty);
      if (isDirty) {
        this.$locationUnsaved.removeAttr('hidden');
      } else {
        this.$locationUnsaved.attr('hidden', 'hidden');
      }
    }

    showLocationNotice(message) {
      if (!message) {
        return;
      }
      this.$locationUnsaved.removeClass('is-error');
      this.$locationUnsaved.text(message).removeAttr('hidden');
      setTimeout(() => {
        this.$locationUnsaved.attr('hidden', 'hidden');
      }, 2500);
    }

    /* --------------------------------------------------------------------- */
    /* Settings                                                              */
    /* --------------------------------------------------------------------- */

    initSettings() {
      this.$settingsForm = $('#rb-admin-settings-form');
      if (!this.$settingsForm.length) {
        return;
      }
      this.$settingsSave = $('#rb-settings-save');
      this.$settingsReset = $('#rb-settings-reset');
      this.$settingsNotice = $('#rb-settings-unsaved');

      this.$settingsForm.on('input change', 'input, textarea, select', () => {
        this.setSettingsDirty(true);
        this.updateSettingsSummary();
      });

      this.$settingsForm.on('submit', (event) => {
        event.preventDefault();
        this.setSettingsDirty(false);
        this.showSettingsNotice(this.strings.settingsSaved);
      });

      this.$settingsReset.on('click', (event) => {
        event.preventDefault();
        this.$settingsForm[0].reset();
        this.updateSettingsSummary();
        this.setSettingsDirty(false);
        this.showSettingsNotice(this.strings.settingsReset);
      });

      this.updateSettingsSummary();
    }

    setSettingsDirty(isDirty) {
      this.$settingsSave.prop('disabled', !isDirty);
      this.$settingsReset.prop('disabled', !isDirty);
      if (isDirty) {
        this.$settingsNotice.removeAttr('hidden');
      } else {
        this.$settingsNotice.attr('hidden', 'hidden');
      }
    }

    showSettingsNotice(message) {
      if (!message) {
        return;
      }
      this.$settingsNotice.removeClass('is-error');
      this.$settingsNotice.text(message).removeAttr('hidden');
      setTimeout(() => {
        this.$settingsNotice.attr('hidden', 'hidden');
      }, 2500);
    }

    updateSettingsSummary() {
      const leadTime = $('#rb-setting-buffer').val() || '0';
      const maxParty = $('#rb-setting-max-party').val() || '0';
      const reminders = $('#rb-setting-reminder-hours').val() || '0';
      $('#rb-policy-lead-time').text(`${leadTime} min buffer`);
      $('#rb-policy-party-size').text(`${maxParty} guests`);
      $('#rb-policy-reminders').text(`${reminders} hrs prior`);
    }

    /* --------------------------------------------------------------------- */
    /* Reports                                                               */
    /* --------------------------------------------------------------------- */

    initReports() {
      this.$reportsRoot = $('#rb-admin-reports-root');
      if (!this.$reportsRoot.length) {
        return;
      }
      this.$reportsLoading = $('#rb-admin-reports-loading');

      $('#rb-report-interval').on('change', (event) => {
        this.reportState.interval = event.target.value;
        this.loadReports();
      });

      $('#rb-report-range').on('change blur', (event) => {
        const match = String(event.target.value || '').match(/\d+/);
        this.reportState.range = match ? Math.max(1, parseInt(match[0], 10)) : this.reportState.range;
        this.loadReports();
      });

      $('#rb-reports-refresh').on('click', (event) => {
        event.preventDefault();
        this.loadReports(true);
      });

      $('.rb-admin-report-preset').on('click', (event) => {
        event.preventDefault();
        const range = $(event.currentTarget).data('range');
        if (range) {
          this.reportState.range = parseInt(range, 10) || this.reportState.range;
          $('#rb-report-range').val(`Last ${this.reportState.range} days`);
          this.loadReports();
        }
      });

      this.loadReports();
    }

    loadReports(force = false) {
      this.toggleOverlay(this.$reportsLoading, true);

      $.when(
        this.getDashboardData(force),
        this.request('rb_admin_get_bookings', {
          per_page: 1,
          page: 1,
          status: '',
        })
      )
        .done((dashboard, bookingsResponse) => {
          const bookingsData = bookingsResponse && bookingsResponse[0] ? bookingsResponse[0] : bookingsResponse;
          const summaryPayload = bookingsData && bookingsData.success ? bookingsData.data : bookingsData;
          this.renderReports(dashboard || {}, summaryPayload || {});
        })
        .fail(() => {
          this.renderReportsError();
        })
        .always(() => {
          this.toggleOverlay(this.$reportsLoading, false);
        });
    }

    renderReports(dashboard, bookingsData) {
      const summary = bookingsData.summary || {};
      const pagination = bookingsData.pagination || {};
      const locations = dashboard.locations || [];
      const totalBookings = toNumber(pagination.total_items, 0);
      const revenueTotal = toNumber(summary.total_revenue || summary.revenue_total, 0);
      const revenueChange = toNumber(summary.revenue_change, 0);
      const bookingsChange = toNumber(summary.bookings_change, 0);
      const averagePartySize = toNumber(summary.average_party_size, 0);
      const occupancyAvg = toNumber((dashboard.summary && dashboard.summary.average_occupancy) || 0, 0);

      $('#rb-report-revenue').text(this.formatCurrency(this.scaleByRange(revenueTotal)));
      $('#rb-report-revenue-change')
        .text(formatTrend(revenueChange))
        .toggleClass('is-positive', revenueChange >= 0)
        .toggleClass('is-negative', revenueChange < 0);

      $('#rb-report-bookings').text(formatNumber(this.scaleByRange(totalBookings)));
      $('#rb-report-bookings-change')
        .text(formatTrend(bookingsChange))
        .toggleClass('is-positive', bookingsChange >= 0)
        .toggleClass('is-negative', bookingsChange < 0);

      $('#rb-report-party-size').text(averagePartySize.toFixed(1));
      $('#rb-report-party-size-change').text(formatTrend(summary.party_size_change || 0));
      $('#rb-report-occupancy').text(formatPercent(occupancyAvg));
      $('#rb-report-occupancy-meta').text(`${this.reportState.range}-day average occupancy`);

      this.renderReportsChart(locations);
      this.renderTopLocationsTable(locations);
      $('#rb-report-top-summary').text(
        `${formatNumber(locations.length)} locations analysed`
      );
    }

    renderReportsError() {
      this.$reportsRoot.append(
        `<div class="rb-admin-alert is-error">${this.escape(this.strings.error)}</div>`
      );
    }

    renderReportsChart(locations) {
      const $chart = $('#rb-admin-reports-chart');
      if (!$chart.length) {
        return;
      }
      if (!locations.length) {
        $chart.html(`<div class="rb-chart-empty">${this.escape(this.strings.locationsEmpty)}</div>`);
        return;
      }
      const bars = locations
        .slice(0, 8)
        .map((location) => {
          const stats = location.stats || {};
          const value = toNumber(stats.bookings, 0);
          const height = Math.min(100, value ? value : 10);
          return `
            <div class="rb-admin-chart-bar" style="height: ${height + 40}px;">
              <div class="rb-admin-stat-value">${formatNumber(value)}</div>
              <span>${this.escape(location.name)}</span>
            </div>
          `;
        })
        .join('');
      $chart.html(`<div class="rb-admin-chart-bars">${bars}</div>`);
    }

    renderTopLocationsTable(locations) {
      const $body = $('#rb-admin-reports-top-body');
      const $empty = $('#rb-admin-reports-empty');
      if (!$body.length) {
        return;
      }
      if (!locations.length) {
        $body.empty();
        $empty.removeAttr('hidden');
        return;
      }
      $empty.attr('hidden', 'hidden');

      const sorted = locations.slice().sort((a, b) => {
        const aValue = toNumber(a.stats && a.stats.bookings, 0);
        const bValue = toNumber(b.stats && b.stats.bookings, 0);
        return bValue - aValue;
      });

      const rows = sorted.map((location) => {
        const stats = location.stats || {};
        const bookings = formatNumber(stats.bookings || 0);
        const revenue = this.formatCurrency(stats.revenue_value || stats.revenue_raw || 0);
        const occupancy = formatPercent(stats.occupancy || 0);
        const change = formatTrend(stats.revenue_trend || 0);
        return `
          <tr>
            <td>${this.escape(location.name)}</td>
            <td>${bookings}</td>
            <td>${this.escape(revenue)}</td>
            <td>${occupancy}</td>
            <td>${change}</td>
          </tr>
        `;
      });

      $body.html(rows.join(''));
    }

    scaleByRange(value) {
      const range = Math.max(1, this.reportState.range || 1);
      const base = 30;
      return (toNumber(value, 0) * range) / base;
    }
  }

  $(document).ready(() => {
    window.rbModernAdmin = new ModernAdmin();
  });
})(jQuery);
