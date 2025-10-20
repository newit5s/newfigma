/*
 * Modern Restaurant Booking Manager - Portal Dashboard Interactions (Phase 4)
 * Coordinates real-time stats, charts, quick actions, and today's schedule.
 */

(function () {
  'use strict';

  class PortalDashboard {
    constructor() {
      this.currentLocation = (typeof rbDashboard !== 'undefined' && rbDashboard.current_location) ? rbDashboard.current_location : '';
      this.refreshInterval = 30000;
      this.refreshTimer = null;

      this.charts = (typeof DashboardCharts === 'function') ? new DashboardCharts() : null;
      this.stats = new DashboardStats();
      this.quickActions = new QuickActions();
      this.schedule = new TodaysSchedule();

      this.init();
    }

    init() {
      this.bindEvents();
      this.loadInitialData();
      this.startAutoRefresh();
      this.updateDateTime();
    }

    bindEvents() {
      const locationSelector = document.getElementById('location-selector');
      if (locationSelector) {
        locationSelector.addEventListener('change', (event) => {
          const newLocation = event.target.value;
          this.changeLocation(newLocation);
        });
      }

      const themeToggle = document.getElementById('theme-toggle');
      if (themeToggle) {
        themeToggle.addEventListener('click', (event) => {
          event.preventDefault();
          this.toggleTheme();
        });
      }

      const notificationsToggle = document.getElementById('notifications-toggle');
      if (notificationsToggle) {
        notificationsToggle.addEventListener('click', (event) => {
          event.preventDefault();
          this.toggleNotifications();
        });
      }

      const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
      if (autoRefreshToggle) {
        autoRefreshToggle.addEventListener('change', (event) => {
          if (event.target.checked) {
            this.startAutoRefresh();
          } else {
            this.stopAutoRefresh();
          }
        });
      }
    }

    async changeLocation(locationId) {
      this.currentLocation = locationId;
      if (typeof rbDashboard !== 'undefined') {
        rbDashboard.current_location = locationId;
      }

      this.saveLocationPreference(locationId);
      this.showGlobalLoading(true);

      if (this.charts && typeof this.charts.setLocation === 'function') {
        this.charts.setLocation(locationId);
      }

      this.stats.setLocation(locationId);
      this.schedule.setLocation(locationId);

      try {
        await Promise.all([
          this.stats.refreshMetrics(),
          this.schedule.loadSchedule(locationId),
          this.charts ? this.charts.loadChartData(locationId) : Promise.resolve()
        ]);
      } catch (error) {
        console.error('Failed to change location:', error);
        this.showError(this.getString('error') || 'Failed to load location data');
      } finally {
        this.showGlobalLoading(false);
      }
    }

    async loadInitialData() {
      this.stats.setLocation(this.currentLocation);
      this.schedule.setLocation(this.currentLocation);

      try {
        this.showGlobalLoading(true);
        await Promise.all([
          this.stats.refreshMetrics(),
          this.schedule.loadSchedule(this.currentLocation),
          this.charts ? this.charts.loadChartData(this.currentLocation) : Promise.resolve()
        ]);
      } catch (error) {
        console.error('Failed to load dashboard data:', error);
        this.showError(this.getString('error') || 'Unable to load dashboard data');
      } finally {
        this.showGlobalLoading(false);
      }
    }

    startAutoRefresh() {
      this.stopAutoRefresh();
      this.refreshTimer = setInterval(() => {
        this.refreshData();
      }, this.refreshInterval);
    }

    stopAutoRefresh() {
      if (this.refreshTimer) {
        clearInterval(this.refreshTimer);
        this.refreshTimer = null;
      }
    }

    async refreshData() {
      try {
        await Promise.all([
          this.stats.refreshMetrics(false),
          this.schedule.loadSchedule(this.currentLocation)
        ]);
        this.showRefreshIndicator();
      } catch (error) {
        console.error('Auto refresh error:', error);
      }
    }

    showGlobalLoading(show) {
      const loader = document.getElementById('dashboard-loader');
      if (!loader) {
        return;
      }
      loader.style.display = show ? 'flex' : 'none';
    }

    showRefreshIndicator() {
      const indicator = document.getElementById('refresh-indicator');
      if (!indicator) {
        return;
      }

      indicator.style.display = 'block';
      setTimeout(() => {
        indicator.style.display = 'none';
      }, 2000);
    }

    showError(message) {
      console.error('Dashboard error:', message);
    }

    toggleTheme() {
      const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
      const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', nextTheme);
      try {
        window.localStorage.setItem('rb_theme', nextTheme);
      } catch (error) {
        console.warn('Unable to persist theme preference:', error);
      }
    }

    toggleNotifications() {
      const panel = document.getElementById('notifications-panel');
      if (panel) {
        panel.classList.toggle('rb-active');
      }
    }

    updateDateTime() {
      const dateElement = document.getElementById('current-date');
      if (dateElement) {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        dateElement.textContent = `Today: ${now.toLocaleDateString(undefined, options)}`;
      }

      setTimeout(() => this.updateDateTime(), 60000);
    }

    saveLocationPreference(locationId) {
      try {
        window.localStorage.setItem('rb_preferred_location', String(locationId));
      } catch (error) {
        console.warn('Unable to save location preference:', error);
      }
    }

    getString(key) {
      if (typeof rbDashboard === 'undefined' || !rbDashboard.strings) {
        return '';
      }
      return rbDashboard.strings[key] || '';
    }
  }

  class DashboardStats {
    constructor() {
      this.ajaxUrl = (typeof rbDashboard !== 'undefined') ? rbDashboard.ajax_url : '';
      this.nonce = (typeof rbDashboard !== 'undefined') ? rbDashboard.nonce : '';
      this.currentLocation = (typeof rbDashboard !== 'undefined' && rbDashboard.current_location) ? rbDashboard.current_location : '';
      this.metricPeriods = this.loadStoredPeriods();

      this.bindEvents();
    }

    bindEvents() {
      document.querySelectorAll('.rb-stat-period-select').forEach((select) => {
        const metric = select.dataset.metric;
        if (!metric) {
          return;
        }

        if (this.metricPeriods[metric]) {
          select.value = this.metricPeriods[metric];
        }

        select.addEventListener('change', (event) => {
          const period = event.target.value;
          this.metricPeriods[metric] = period;
          this.persistPeriods();
          this.updateMetric(metric, period, true);
        });
      });
    }

    setLocation(locationId) {
      this.currentLocation = locationId;
    }

    async refreshMetrics(showLoading = true) {
      const metrics = Object.keys(this.metricPeriods);
      const promises = metrics.map((metric) => this.updateMetric(metric, this.metricPeriods[metric], showLoading));
      await Promise.all(promises);
    }

    async updateMetric(metric, period, showLoading) {
      const card = document.querySelector(`.rb-stat-card[data-metric="${metric}"]`);
      if (!card) {
        return;
      }

      if (!this.ajaxUrl) {
        return;
      }

      if (showLoading) {
        this.toggleCardLoading(card, true);
      }

      try {
        const response = await fetch(this.ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: new URLSearchParams({
            action: 'rb_update_stat_period',
            nonce: this.nonce,
            metric,
            period,
            location_id: this.currentLocation
          })
        });

        const payload = await response.json();
        if (payload && payload.success && payload.data) {
          this.renderMetric(card, payload.data);
        } else {
          console.error('Failed to update metric', metric, payload);
        }
      } catch (error) {
        console.error('Failed to fetch metric data:', error);
      } finally {
        if (showLoading) {
          this.toggleCardLoading(card, false);
        }
      }
    }

    renderMetric(card, data) {
      if (!data) {
        return;
      }

      const numberEl = card.querySelector('.rb-stat-number');
      if (numberEl) {
        this.animateNumber(numberEl, Number(data.value), data.prefix || '', data.suffix || '');
      }

      const labelEl = card.querySelector('.rb-stat-label');
      if (labelEl && data.label) {
        labelEl.textContent = data.label;
      }

      const changeEl = card.querySelector('.rb-stat-change');
      if (changeEl && data.change) {
        this.updateChangeIndicator(changeEl, data.change);
      }

      const badgeEl = card.querySelector('.rb-action-badge, .rb-stat-badge');
      if (badgeEl && data.badge) {
        badgeEl.textContent = data.badge;
        badgeEl.style.display = 'inline-flex';
      }

      if (badgeEl && !data.badge) {
        badgeEl.style.display = 'none';
      }
    }

    toggleCardLoading(card, show) {
      const overlay = card.querySelector('.rb-stat-loading');
      if (overlay) {
        overlay.style.display = show ? 'flex' : 'none';
      }
    }

    animateNumber(element, targetValue, prefix = '', suffix = '') {
      const startValue = this.parseNumber(element.textContent);
      const duration = 900;
      const startTime = performance.now();

      const animate = (currentTime) => {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOutQuart = 1 - Math.pow(1 - progress, 4);
        const currentValue = startValue + (targetValue - startValue) * easeOutQuart;
        const decimals = suffix === '%' ? 0 : 0;
        element.textContent = `${prefix}${this.formatNumber(currentValue, decimals)}${suffix}`;
        if (progress < 1) {
          requestAnimationFrame(animate);
        }
      };

      requestAnimationFrame(animate);
    }

    updateChangeIndicator(element, change) {
      const percentage = Number(change.percentage) || 0;
      let className = 'rb-stat-change ';
      if (percentage > 0) {
        className += 'rb-positive';
      } else if (percentage < 0) {
        className += 'rb-negative';
      } else {
        className += 'rb-neutral';
      }
      element.className = className;

      const textNode = element.querySelector('span');
      if (textNode) {
        const sign = percentage > 0 ? '+' : '';
        textNode.textContent = `${sign}${percentage}% ${change.period || ''}`.trim();
      }
    }

    parseNumber(value) {
      if (!value) {
        return 0;
      }
      const numeric = String(value).replace(/[^0-9.-]/g, '');
      return Number(numeric) || 0;
    }

    formatNumber(value, decimals = 0) {
      const formatter = new Intl.NumberFormat(undefined, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
      });
      return formatter.format(decimals === 0 ? Math.round(value) : value);
    }

    loadStoredPeriods() {
      try {
        const stored = window.localStorage.getItem('rb_dashboard_periods');
        if (stored) {
          return Object.assign({ bookings: 'today', revenue: 'today', occupancy: 'today', pending: 'today' }, JSON.parse(stored));
        }
      } catch (error) {
        console.warn('Unable to read stored dashboard periods:', error);
      }
      return {
        bookings: 'today',
        revenue: 'today',
        occupancy: 'today',
        pending: 'today'
      };
    }

    persistPeriods() {
      try {
        window.localStorage.setItem('rb_dashboard_periods', JSON.stringify(this.metricPeriods));
      } catch (error) {
        console.warn('Unable to persist dashboard periods:', error);
      }
    }
  }

  class QuickActions {
    constructor() {
      this.bindEvents();
    }

    bindEvents() {
      document.querySelectorAll('.rb-action-item').forEach((item) => {
        item.addEventListener('click', (event) => {
          const action = event.currentTarget.dataset.action;
          this.handleAction(action);
        });
      });
    }

    handleAction(action) {
      switch (action) {
        case 'confirm-booking':
          this.openConfirmBookingModal();
          break;
        case 'add-walkin':
          this.openAddWalkInModal();
          break;
        case 'view-calendar':
          this.navigate(rbDashboard && rbDashboard.calendar_url);
          break;
        case 'manage-tables':
          this.navigate(rbDashboard && rbDashboard.tables_url);
          break;
        case 'view-reports':
          this.navigate(rbDashboard && rbDashboard.reports_url);
          break;
        case 'settings':
          this.navigate(rbDashboard && rbDashboard.settings_url);
          break;
        default:
          break;
      }
    }

    navigate(url) {
      if (url) {
        window.location.href = url;
      }
    }

    openConfirmBookingModal() {
      console.log('Open confirm booking modal (Phase 5 placeholder)');
    }

    openAddWalkInModal() {
      console.log('Open add walk-in modal (Phase 5 placeholder)');
    }
  }

  class TodaysSchedule {
    constructor() {
      this.currentLocation = (typeof rbDashboard !== 'undefined' && rbDashboard.current_location) ? rbDashboard.current_location : '';
      this.ajaxUrl = (typeof rbDashboard !== 'undefined') ? rbDashboard.ajax_url : '';
      this.nonce = (typeof rbDashboard !== 'undefined') ? rbDashboard.nonce : '';
      this.bindEvents();
    }

    bindEvents() {
      document.querySelectorAll('.rb-booking-item').forEach((item) => {
        item.addEventListener('click', (event) => {
          const bookingId = event.currentTarget.dataset.bookingId;
          this.openBookingDetails(bookingId);
        });
      });
    }

    setLocation(locationId) {
      this.currentLocation = locationId;
    }

    async loadSchedule(locationId) {
      if (locationId) {
        this.currentLocation = locationId;
      }

      if (!this.ajaxUrl) {
        return;
      }

      try {
        const response = await fetch(this.ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: new URLSearchParams({
            action: 'rb_get_todays_schedule',
            nonce: this.nonce,
            location_id: this.currentLocation
          })
        });

        const payload = await response.json();
        if (payload && payload.success && payload.data) {
          this.updateScheduleDisplay(payload.data);
        }
      } catch (error) {
        console.error('Failed to load schedule:', error);
      }
    }

    updateScheduleDisplay(data) {
      if (!data) {
        return;
      }

      const timeline = document.getElementById('schedule-timeline');
      if (!timeline) {
        return;
      }

      timeline.innerHTML = '';

      (data.timeSlots || []).forEach((slot) => {
        const element = this.createTimeSlotElement(slot);
        timeline.appendChild(element);
      });

      if (data.summary) {
        this.updateScheduleSummary(data.summary);
      }

      if (data.dateLabel) {
        const dateLabel = document.getElementById('schedule-date');
        if (dateLabel) {
          dateLabel.textContent = data.dateLabel;
        }
      }

      this.bindEvents();
    }

    createTimeSlotElement(slot) {
      const wrapper = document.createElement('div');
      wrapper.className = `rb-schedule-time-slot${!slot.bookings || slot.bookings.length === 0 ? ' rb-no-bookings' : ''}`;
      wrapper.dataset.time = slot.time || '';

      const bookingsHtml = (slot.bookings || []).length > 0
        ? slot.bookings.map((booking) => this.createBookingItemHTML(booking)).join('')
        : this.createEmptySlotHTML();

      wrapper.innerHTML = `
        <div class="rb-time-label">${slot.timeLabel || ''}</div>
        <div class="rb-bookings-list">
          ${bookingsHtml}
        </div>
      `;

      return wrapper;
    }

    createBookingItemHTML(booking) {
      const statusClass = booking.statusClass || `rb-status-${(booking.status || '').toLowerCase()}`;
      return `
        <div class="rb-booking-item" data-booking-id="${booking.id}">
          <div class="rb-booking-customer">${booking.customerName || ''}</div>
          <div class="rb-booking-details">${booking.details || ''}</div>
          <div class="rb-booking-status ${statusClass}">${booking.status || ''}</div>
        </div>
      `;
    }

    createEmptySlotHTML() {
      return `
        <div class="rb-empty-slot">
          <span class="rb-empty-text">${this.getString('no_data') || 'No bookings'}</span>
          <button class="rb-btn rb-btn-sm rb-btn-outline" type="button">${this.getString('add_booking') || 'Add Booking'}</button>
        </div>
      `;
    }

    updateScheduleSummary(summary) {
      const totalBookings = document.querySelector('[data-summary="total-bookings"]');
      const expectedRevenue = document.querySelector('[data-summary="expected-revenue"]');

      if (totalBookings) {
        totalBookings.textContent = summary.totalBookings || '0';
      }

      if (expectedRevenue) {
        expectedRevenue.textContent = summary.expectedRevenue || summary.expectedRevenueFormatted || '0';
      }
    }

    openBookingDetails(bookingId) {
      console.log('Open booking details modal (Phase 5 placeholder):', bookingId);
    }

    getString(key) {
      if (typeof rbDashboard === 'undefined' || !rbDashboard.strings) {
        return '';
      }
      return rbDashboard.strings[key] || '';
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.rb-portal-dashboard')) {
      window.rbPortalDashboard = new PortalDashboard();
    }
  });
})();
