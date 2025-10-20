/*
 * Booking Management Interface Logic (Phase 5)
 * ------------------------------------------------------------
 * Coordinates the interactive behaviour for the staff booking
 * management UI: table rendering, calendar view, filters,
 * pagination, bulk actions, and AJAX communication with the
 * WordPress back end.
 */

(function () {
  'use strict';

  const htmlEscapeMap = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };

  function escapeHtml(value) {
    if (value === null || value === undefined) {
      return '';
    }
    return String(value).replace(/[&<>"']/g, (character) => htmlEscapeMap[character] || character);
  }

  function formatDateISO(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return '';
    }
    return date.toISOString().split('T')[0];
  }

  class BookingManagement {
    constructor() {
      if (typeof rbBookingManagement === 'undefined') {
        return;
      }

      this.ajaxUrl = rbBookingManagement.ajax_url;
      this.nonce = rbBookingManagement.nonce;
      this.currentLocation = rbBookingManagement.current_location || '';
      this.strings = rbBookingManagement.strings || {};

      this.currentView = 'table';
      this.currentPage = 1;
      this.pageSize = 25;
      this.sortBy = 'booking_datetime';
      this.sortOrder = 'desc';
      this.selectedBookings = new Set();
      this.refreshTimer = null;
      this.refreshInterval = 30000;

      const today = new Date();
      const nextWeek = new Date(today.getTime() + (7 * 24 * 60 * 60 * 1000));

      this.filters = {
        dateFrom: rbBookingManagement.defaults?.date_from || formatDateISO(today),
        dateTo: rbBookingManagement.defaults?.date_to || formatDateISO(nextWeek),
        status: '',
        location: rbBookingManagement.defaults?.location || this.currentLocation || '',
        search: ''
      };

      this.tableManager = new BookingTableManager(this);
      this.filterManager = new BookingFilterManager(this);
      this.calendarManager = new BookingCalendarManager(this);
      this.bulkActions = new BulkActionsManager(this);

      this.bindCoreEvents();
      this.initializeFilterInputs();
      this.loadBookings();
      this.startAutoRefresh();
    }

    bindCoreEvents() {
      const viewButtons = document.querySelectorAll('.rb-view-btn');
      viewButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          const view = button.getAttribute('data-view');
          if (view && view !== this.currentView) {
            this.switchView(view);
          }
        });
      });

      const addButton = document.getElementById('add-booking-btn');
      if (addButton) {
        addButton.addEventListener('click', (event) => {
          event.preventDefault();
          this.openAddBookingModal();
        });
      }

      const refreshButton = document.getElementById('refresh-bookings');
      if (refreshButton) {
        refreshButton.addEventListener('click', (event) => {
          event.preventDefault();
          this.loadBookings();
        });
      }

      const exportButton = document.getElementById('export-bookings');
      if (exportButton) {
        exportButton.addEventListener('click', (event) => {
          event.preventDefault();
          this.exportBookings();
        });
      }

      const selectAll = document.getElementById('select-all-bookings');
      if (selectAll) {
        selectAll.addEventListener('change', (event) => {
          this.toggleSelectAll(event.target.checked);
        });
      }

      const paginationPrev = document.getElementById('pagination-prev');
      if (paginationPrev) {
        paginationPrev.addEventListener('click', (event) => {
          event.preventDefault();
          if (this.currentPage > 1) {
            this.currentPage -= 1;
            this.loadBookings();
          }
        });
      }

      const paginationNext = document.getElementById('pagination-next');
      if (paginationNext) {
        paginationNext.addEventListener('click', (event) => {
          event.preventDefault();
          this.currentPage += 1;
          this.loadBookings();
        });
      }

      const paginationSize = document.getElementById('pagination-size');
      if (paginationSize) {
        paginationSize.addEventListener('change', (event) => {
          const newSize = parseInt(event.target.value, 10);
          if (!Number.isNaN(newSize) && newSize > 0) {
            this.pageSize = newSize;
            this.currentPage = 1;
            this.loadBookings();
          }
        });
      }
    }

    initializeFilterInputs() {
      const dateFromInput = document.getElementById('date-from');
      if (dateFromInput && this.filters.dateFrom) {
        dateFromInput.value = this.filters.dateFrom;
      }

      const dateToInput = document.getElementById('date-to');
      if (dateToInput && this.filters.dateTo) {
        dateToInput.value = this.filters.dateTo;
      }

      const statusSelect = document.getElementById('status-filter');
      if (statusSelect && this.filters.status) {
        statusSelect.value = this.filters.status;
      }

      const locationSelect = document.getElementById('location-filter');
      if (locationSelect && this.filters.location) {
        locationSelect.value = this.filters.location;
      }
    }

    switchView(view) {
      this.currentView = view;
      document.querySelectorAll('.rb-view-btn').forEach((button) => {
        const buttonView = button.getAttribute('data-view');
        button.classList.toggle('rb-active', buttonView === view);
      });

      const tableView = document.getElementById('table-view');
      const calendarView = document.getElementById('calendar-view');
      if (tableView) {
        tableView.style.display = view === 'table' ? 'block' : 'none';
      }
      if (calendarView) {
        calendarView.style.display = view === 'calendar' ? 'block' : 'none';
      }

      if (view === 'calendar') {
        this.calendarManager.loadCalendarData();
      } else {
        this.loadBookings();
      }
    }

    async loadBookings(showLoader = true) {
      if (!this.ajaxUrl) {
        return;
      }

      if (showLoader) {
        this.showLoadingState(true);
      }

      const payload = new URLSearchParams({
        action: 'rb_get_bookings_list',
        nonce: this.nonce,
        page: String(this.currentPage),
        page_size: String(this.pageSize),
        sort_by: this.sortBy,
        sort_order: this.sortOrder,
        dateFrom: this.filters.dateFrom || '',
        dateTo: this.filters.dateTo || '',
        status: this.filters.status || '',
        location: this.filters.location || '',
        search: this.filters.search || ''
      });

      try {
        const response = await fetch(this.ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload
        });

        const result = await response.json();
        if (!result || typeof result.success === 'undefined') {
          throw new Error('invalid_response');
        }

        if (result.success && result.data) {
          const bookings = Array.isArray(result.data.bookings) ? result.data.bookings : [];
          this.tableManager.renderBookings(bookings);
          this.updatePagination(result.data.pagination || {});
          this.clearSelection();
        } else {
          const message = result.data?.message || result.message || this.strings.error || 'Failed to load bookings';
          this.showNotice('error', message);
          this.tableManager.renderBookings([]);
          this.updatePagination({ current_page: 1, total_pages: 1, start: 0, end: 0, total: 0 });
        }
      } catch (error) {
        console.error('BookingManagement.loadBookings', error);
        this.showNotice('error', this.strings.error || 'Unable to load bookings');
      } finally {
        if (showLoader) {
          this.showLoadingState(false);
        }
      }
    }

    async updateBookingStatus(bookingId, status) {
      if (!bookingId) {
        return;
      }

      const payload = new URLSearchParams({
        action: 'rb_update_booking_status',
        nonce: this.nonce,
        booking_id: String(bookingId),
        status
      });

      try {
        const response = await fetch(this.ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload
        });

        const result = await response.json();
        if (result.success) {
          this.showNotice('success', this.strings.success_updated || 'Booking updated successfully');
          this.loadBookings(false);
        } else {
          const message = result.data?.message || result.message || this.strings.error || 'Unable to update booking';
          this.showNotice('error', message);
        }
      } catch (error) {
        console.error('BookingManagement.updateBookingStatus', error);
        this.showNotice('error', this.strings.error || 'Unable to update booking');
      }
    }

    async deleteBooking(bookingId) {
      if (!bookingId) {
        return;
      }

      const confirmMessage = this.strings.confirm_delete || 'Delete this booking?';
      if (!window.confirm(confirmMessage)) {
        return;
      }

      const payload = new URLSearchParams({
        action: 'rb_delete_booking',
        nonce: this.nonce,
        booking_id: String(bookingId)
      });

      try {
        const response = await fetch(this.ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload
        });
        const result = await response.json();
        if (result.success) {
          this.showNotice('success', this.strings.success_deleted || 'Booking removed');
          this.loadBookings();
        } else {
          const message = result.data?.message || result.message || this.strings.error || 'Unable to delete booking';
          this.showNotice('error', message);
        }
      } catch (error) {
        console.error('BookingManagement.deleteBooking', error);
        this.showNotice('error', this.strings.error || 'Unable to delete booking');
      }
    }

    toggleSelectAll(checked) {
      const checkboxes = document.querySelectorAll('.rb-booking-checkbox');
      this.selectedBookings.clear();
      checkboxes.forEach((checkbox) => {
        checkbox.checked = checked;
        const id = checkbox.getAttribute('data-booking-id');
        if (checked && id) {
          this.selectedBookings.add(id);
        }
        const row = checkbox.closest('tr');
        if (row) {
          row.classList.toggle('rb-selected', checkbox.checked);
        }
      });
      this.updateBulkActions();
    }

    toggleBookingSelection(bookingId, checked, rowElement) {
      if (!bookingId) {
        return;
      }
      if (checked) {
        this.selectedBookings.add(bookingId);
      } else {
        this.selectedBookings.delete(bookingId);
      }
      if (rowElement) {
        rowElement.classList.toggle('rb-selected', checked);
      }
      this.updateBulkActions();
      this.updateSelectAllState();
    }

    updateBulkActions() {
      const bulkBar = document.getElementById('bulk-actions-bar');
      const selectedCountElement = document.querySelector('.rb-selected-count');
      const count = this.selectedBookings.size;
      if (!bulkBar || !selectedCountElement) {
        return;
      }
      if (count > 0) {
        bulkBar.style.display = 'flex';
        selectedCountElement.textContent = `${count} booking${count === 1 ? '' : 's'} selected`;
      } else {
        bulkBar.style.display = 'none';
      }
    }

    clearSelection() {
      this.selectedBookings.clear();
      document.querySelectorAll('.rb-booking-checkbox').forEach((checkbox) => {
        checkbox.checked = false;
        const row = checkbox.closest('tr');
        if (row) {
          row.classList.remove('rb-selected');
        }
      });
      this.updateBulkActions();
      this.updateSelectAllState();
    }

    updateSelectAllState() {
      const selectAll = document.getElementById('select-all-bookings');
      if (!selectAll) {
        return;
      }
      const total = document.querySelectorAll('.rb-booking-checkbox').length;
      const count = this.selectedBookings.size;
      if (count === 0) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
      } else if (count === total) {
        selectAll.checked = true;
        selectAll.indeterminate = false;
      } else {
        selectAll.checked = false;
        selectAll.indeterminate = true;
      }
    }

    showLoadingState(show) {
      const loading = document.getElementById('table-loading');
      const empty = document.getElementById('table-empty');
      const table = document.getElementById('bookings-table');
      if (loading) {
        loading.style.display = show ? 'flex' : 'none';
      }
      if (table) {
        table.style.visibility = show ? 'hidden' : 'visible';
      }
      if (empty && !show) {
        empty.style.display = empty.dataset.visible === 'true' ? 'flex' : 'none';
      }
    }

    showNotice(type, message) {
      const toast = document.getElementById('booking-management-toast');
      if (!toast) {
        if (type === 'error') {
          window.alert(message);
        } else {
          console.log(message);
        }
        return;
      }

      toast.textContent = message;
      toast.classList.remove('rb-toast-success', 'rb-toast-error');
      toast.classList.add(type === 'success' ? 'rb-toast-success' : 'rb-toast-error');
      toast.style.display = 'block';

      window.clearTimeout(this.toastTimer);
      this.toastTimer = window.setTimeout(() => {
        toast.style.display = 'none';
      }, 4000);
    }

    startAutoRefresh() {
      if (this.refreshTimer) {
        window.clearInterval(this.refreshTimer);
      }
      this.refreshTimer = window.setInterval(() => {
        if (this.currentView === 'table') {
          this.loadBookings(false);
        } else {
          this.calendarManager.loadCalendarData();
        }
      }, this.refreshInterval);
    }

    updatePagination(pagination) {
      const startEl = document.getElementById('pagination-start');
      const endEl = document.getElementById('pagination-end');
      const totalEl = document.getElementById('pagination-total');

      const currentPage = Number(pagination.current_page) || 1;
      const totalPages = Math.max(Number(pagination.total_pages) || 1, 1);
      const start = Number(pagination.start) || 0;
      const end = Number(pagination.end) || 0;
      const total = Number(pagination.total) || Number(pagination.total_items) || 0;

      this.currentPage = Math.min(Math.max(currentPage, 1), totalPages);

      if (startEl) {
        startEl.textContent = start ? start : '0';
      }
      if (endEl) {
        endEl.textContent = end ? end : '0';
      }
      if (totalEl) {
        totalEl.textContent = total ? total : '0';
      }

      const prev = document.getElementById('pagination-prev');
      if (prev) {
        prev.disabled = this.currentPage <= 1;
      }
      const next = document.getElementById('pagination-next');
      if (next) {
        next.disabled = this.currentPage >= totalPages;
      }

      this.renderPaginationPages(this.currentPage, totalPages);
    }

    renderPaginationPages(current, total) {
      const container = document.getElementById('pagination-pages');
      if (!container) {
        return;
      }
      let html = '';
      const maxPagesToShow = 5;
      const createButton = (page, active = false) => `\n        <button class="rb-pagination-page${active ? ' rb-active' : ''}" data-page="${page}" type="button">${page}</button>\n      `;

      const renderRange = (start, end) => {
        for (let i = start; i <= end; i += 1) {
          html += createButton(i, i === current);
        }
      };

      if (total <= maxPagesToShow) {
        renderRange(1, total);
      } else {
        html += createButton(1, current === 1);
        if (current > 3) {
          html += '<span class="rb-pagination-ellipsis">…</span>';
        }
        const rangeStart = Math.max(2, current - 1);
        const rangeEnd = Math.min(total - 1, current + 1);
        renderRange(rangeStart, rangeEnd);
        if (current < total - 2) {
          html += '<span class="rb-pagination-ellipsis">…</span>';
        }
        html += createButton(total, current === total);
      }

      container.innerHTML = html.trim();
      container.querySelectorAll('.rb-pagination-page').forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          const page = parseInt(button.getAttribute('data-page'), 10);
          if (!Number.isNaN(page) && page !== this.currentPage) {
            this.currentPage = page;
            this.loadBookings();
          }
        });
      });
    }

    async exportBookings() {
      const payload = new URLSearchParams({
        action: 'rb_export_bookings',
        nonce: this.nonce,
        dateFrom: this.filters.dateFrom || '',
        dateTo: this.filters.dateTo || '',
        status: this.filters.status || '',
        location: this.filters.location || '',
        search: this.filters.search || ''
      });

      try {
        const response = await fetch(this.ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload
        });

        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
          const result = await response.json();
          const message = result.data?.message || result.message || this.strings.error || 'Unable to export bookings';
          this.showNotice('error', message);
          return;
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `bookings-export-${formatDateISO(new Date()) || 'export'}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
      } catch (error) {
        console.error('BookingManagement.exportBookings', error);
        this.showNotice('error', this.strings.error || 'Unable to export bookings');
      }
    }

    openAddBookingModal() {
      // Placeholder hook for integration with modal implementation.
      document.dispatchEvent(new CustomEvent('rb:booking-add')); // eslint-disable-line no-undef
    }
  }

  class BookingTableManager {
    constructor(manager) {
      this.manager = manager;
    }

    renderBookings(bookings) {
      const tbody = document.getElementById('bookings-table-body');
      const empty = document.getElementById('table-empty');
      if (!tbody) {
        return;
      }

      if (!Array.isArray(bookings) || bookings.length === 0) {
        tbody.innerHTML = '';
        if (empty) {
          empty.dataset.visible = 'true';
          empty.style.display = 'flex';
        }
        return;
      }

      if (empty) {
        empty.dataset.visible = 'false';
        empty.style.display = 'none';
      }

      const rows = bookings.map((booking) => this.renderRow(booking)).join('');
      tbody.innerHTML = rows;
      this.bindRowEvents();
      this.updateSortIndicators();
    }

    renderRow(booking) {
      const id = escapeHtml(booking.id);
      const name = escapeHtml(booking.customer_name || 'Guest');
      const email = escapeHtml(booking.email || '');
      const phone = escapeHtml(booking.phone || '');
      const partySize = Number(booking.party_size) || 0;
      const partyText = partySize ? `${partySize} ${partySize === 1 ? 'person' : 'people'}` : '—';
      const tableNumber = booking.table_number ? escapeHtml(booking.table_number) : 'TBD';
      const status = (booking.status || '').toLowerCase().replace(/[^a-z0-9]+/g, '-');
      const statusLabel = escapeHtml(booking.status || 'pending');
      const date = escapeHtml(booking.booking_date || '');
      const time = escapeHtml(booking.booking_time || '');
      const location = escapeHtml(booking.location_name || '');

      const avatar = this.generateAvatar(name);

      return `
        <tr class="rb-booking-row" data-booking-id="${id}">
          <td class="rb-checkbox-col" data-label="Select">
            <label class="rb-checkbox-label">
              <input type="checkbox" class="rb-checkbox rb-booking-checkbox" data-booking-id="${id}">
              <span class="rb-checkbox-custom"></span>
            </label>
          </td>
          <td class="rb-customer-col" data-label="Customer">
            <div class="rb-customer-cell">
              <div class="rb-customer-avatar">${avatar}</div>
              <div class="rb-customer-info">
                <div class="rb-customer-name">${name}</div>
                <div class="rb-customer-contact">${email}${email && phone ? ' • ' : ''}${phone}</div>
              </div>
            </div>
          </td>
          <td class="rb-datetime-col" data-label="Date &amp; time">
            <div class="rb-datetime-info">
              <div class="rb-booking-date">${this.formatDate(date)}</div>
              <div class="rb-booking-time">${this.formatTime(time)}</div>
            </div>
          </td>
          <td class="rb-party-col" data-label="Party">
            <span class="rb-party-size">${partyText}</span>
          </td>
          <td class="rb-table-col" data-label="Table">
            <span class="rb-table-number">${tableNumber}</span>
          </td>
          <td class="rb-status-col" data-label="Status">
            <span class="rb-status-badge rb-status-${status}">
              <span class="rb-status-indicator"></span>
              ${statusLabel}
            </span>
          </td>
          <td class="rb-actions-col" data-label="Actions">
            <div class="rb-action-buttons">
              <button class="rb-action-btn rb-action-view" type="button" data-action="view" data-booking-id="${id}" title="View details">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zm0 10.5c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"></path>
                </svg>
              </button>
              <button class="rb-action-btn rb-action-edit" type="button" data-action="edit" data-booking-id="${id}" title="Edit booking">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a.996.996 0 0 0 0-1.41L18.34 3.25a.996.996 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.86-1.79z"></path>
                </svg>
              </button>
              ${statusLabel.toLowerCase() === 'pending' ? `
                <button class="rb-action-btn rb-action-confirm" type="button" data-action="confirm" data-booking-id="${id}" title="Confirm booking">
                  <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M9 16.17 4.83 12 3.41 13.41 9 19l12-12-1.41-1.41z"></path>
                  </svg>
                </button>
              ` : ''}
              <button class="rb-action-btn rb-action-delete" type="button" data-action="delete" data-booking-id="${id}" title="Cancel or delete">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"></path>
                </svg>
              </button>
            </div>
          </td>
        </tr>
      `;
    }

    bindRowEvents() {
      document.querySelectorAll('.rb-booking-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('change', (event) => {
          const target = event.currentTarget;
          const bookingId = target.getAttribute('data-booking-id');
          const row = target.closest('tr');
          this.manager.toggleBookingSelection(bookingId, target.checked, row);
        });
      });

      document.querySelectorAll('.rb-action-btn').forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          const target = event.currentTarget;
          const bookingId = target.getAttribute('data-booking-id');
          const action = target.getAttribute('data-action');
          this.handleAction(action, bookingId);
        });
      });

      document.querySelectorAll('.rb-booking-row').forEach((row) => {
        row.addEventListener('click', (event) => {
          const checkbox = row.querySelector('.rb-booking-checkbox');
          if (!checkbox) {
            return;
          }
          const target = event.target;
          if (target instanceof HTMLElement && (target.closest('.rb-action-buttons') || target.matches('input,button,label,svg,path'))) {
            return;
          }
          checkbox.checked = !checkbox.checked;
          const bookingId = checkbox.getAttribute('data-booking-id');
          this.manager.toggleBookingSelection(bookingId, checkbox.checked, row);
        });
      });

      document.querySelectorAll('.rb-sortable').forEach((header) => {
        header.addEventListener('click', (event) => {
          event.preventDefault();
          const column = header.getAttribute('data-sort');
          if (!column) {
            return;
          }
          if (this.manager.sortBy === column) {
            this.manager.sortOrder = this.manager.sortOrder === 'asc' ? 'desc' : 'asc';
          } else {
            this.manager.sortBy = column;
            this.manager.sortOrder = 'asc';
          }
          this.updateSortIndicators();
          this.manager.loadBookings();
        });
      });
    }

    updateSortIndicators() {
      document.querySelectorAll('.rb-sortable').forEach((header) => {
        const column = header.getAttribute('data-sort');
        header.classList.remove('rb-sorted', 'rb-sort-asc', 'rb-sort-desc');
        if (column === this.manager.sortBy) {
          header.classList.add('rb-sorted', `rb-sort-${this.manager.sortOrder}`);
        }
      });
    }

    handleAction(action, bookingId) {
      if (!action || !bookingId) {
        return;
      }
      switch (action) {
        case 'view':
          document.dispatchEvent(new CustomEvent('rb:booking-view', { detail: { bookingId } }));
          break;
        case 'edit':
          document.dispatchEvent(new CustomEvent('rb:booking-edit', { detail: { bookingId } }));
          break;
        case 'confirm':
          this.manager.updateBookingStatus(bookingId, 'confirmed');
          break;
        case 'delete':
          this.manager.deleteBooking(bookingId);
          break;
        default:
          break;
      }
    }

    generateAvatar(name) {
      const parts = name.trim().split(/\s+/).filter(Boolean);
      if (parts.length === 0) {
        return 'G';
      }
      const initials = parts.slice(0, 2).map((part) => part[0]).join('');
      return escapeHtml(initials.toUpperCase());
    }

    formatDate(value) {
      if (!value) {
        return '—';
      }
      const parsed = new Date(value);
      if (Number.isNaN(parsed.getTime())) {
        return escapeHtml(value);
      }
      return parsed.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    formatTime(value) {
      if (!value) {
        return '—';
      }
      const parsed = new Date(`2000-01-01T${value}`);
      if (Number.isNaN(parsed.getTime())) {
        return escapeHtml(value);
      }
      return parsed.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    }
  }

  class BookingFilterManager {
    constructor(manager) {
      this.manager = manager;
      this.searchTimer = null;
      this.bind();
    }

    bind() {
      const dateFromInput = document.getElementById('date-from');
      if (dateFromInput) {
        dateFromInput.addEventListener('change', (event) => {
          this.manager.filters.dateFrom = event.target.value;
          this.apply();
        });
      }

      const dateToInput = document.getElementById('date-to');
      if (dateToInput) {
        dateToInput.addEventListener('change', (event) => {
          this.manager.filters.dateTo = event.target.value;
          this.apply();
        });
      }

      const statusSelect = document.getElementById('status-filter');
      if (statusSelect) {
        statusSelect.addEventListener('change', (event) => {
          this.manager.filters.status = event.target.value;
          this.apply();
        });
      }

      const locationSelect = document.getElementById('location-filter');
      if (locationSelect) {
        locationSelect.addEventListener('change', (event) => {
          this.manager.filters.location = event.target.value;
          this.apply();
        });
      }

      const searchInput = document.getElementById('search-bookings');
      const clearSearchButton = document.getElementById('clear-search');
      if (searchInput) {
        searchInput.addEventListener('input', (event) => {
          window.clearTimeout(this.searchTimer);
          const value = event.target.value;
          this.searchTimer = window.setTimeout(() => {
            this.manager.filters.search = value;
            this.toggleClearButton(value);
            this.apply();
          }, 350);
        });
      }
      if (clearSearchButton) {
        clearSearchButton.addEventListener('click', (event) => {
          event.preventDefault();
          if (searchInput) {
            searchInput.value = '';
          }
          this.manager.filters.search = '';
          this.toggleClearButton('');
          this.apply();
        });
      }

      const resetButton = document.getElementById('reset-filters');
      if (resetButton) {
        resetButton.addEventListener('click', (event) => {
          event.preventDefault();
          this.reset();
        });
      }
    }

    apply() {
      this.manager.currentPage = 1;
      this.manager.loadBookings();
      if (this.manager.currentView === 'calendar') {
        this.manager.calendarManager.loadCalendarData();
      }
    }

    reset() {
      this.manager.filters = {
        dateFrom: '',
        dateTo: '',
        status: '',
        location: this.manager.currentLocation || '',
        search: ''
      };
      this.manager.initializeFilterInputs();
      const searchInput = document.getElementById('search-bookings');
      if (searchInput) {
        searchInput.value = '';
      }
      this.toggleClearButton('');
      this.apply();
    }

    toggleClearButton(value) {
      const clearButton = document.getElementById('clear-search');
      if (clearButton) {
        clearButton.style.display = value ? 'block' : 'none';
      }
    }
  }

  class BulkActionsManager {
    constructor(manager) {
      this.manager = manager;
      this.bind();
    }

    bind() {
      const clearButton = document.getElementById('clear-selection');
      if (clearButton) {
        clearButton.addEventListener('click', (event) => {
          event.preventDefault();
          this.manager.clearSelection();
        });
      }

      const confirmButton = document.getElementById('bulk-confirm');
      if (confirmButton) {
        confirmButton.addEventListener('click', (event) => {
          event.preventDefault();
          this.bulkUpdate('confirmed');
        });
      }

      const pendingButton = document.getElementById('bulk-pending');
      if (pendingButton) {
        pendingButton.addEventListener('click', (event) => {
          event.preventDefault();
          this.bulkUpdate('pending');
        });
      }

      const cancelButton = document.getElementById('bulk-cancel');
      if (cancelButton) {
        cancelButton.addEventListener('click', (event) => {
          event.preventDefault();
          this.bulkUpdate('cancelled');
        });
      }

      const reminderButton = document.getElementById('bulk-email');
      if (reminderButton) {
        reminderButton.addEventListener('click', (event) => {
          event.preventDefault();
          this.sendReminders();
        });
      }
    }

    async bulkUpdate(status) {
      const ids = Array.from(this.manager.selectedBookings);
      if (!ids.length) {
        this.manager.showNotice('error', this.manager.strings.no_bookings_selected || 'No bookings selected');
        return;
      }

      const message = status === 'cancelled'
        ? (this.manager.strings.confirm_bulk_cancel || 'Cancel selected bookings?')
        : `Update ${ids.length} booking${ids.length === 1 ? '' : 's'} to ${status}?`;

      if (!window.confirm(message)) {
        return;
      }

      const payload = new URLSearchParams({
        action: 'rb_bulk_update_bookings',
        nonce: this.manager.nonce,
        booking_ids: ids.join(','),
        status
      });

      try {
        const response = await fetch(this.manager.ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload
        });
        const result = await response.json();
        if (result.success) {
          const messageSuccess = result.data?.message || this.manager.strings.success_updated || 'Bookings updated';
          this.manager.showNotice('success', messageSuccess);
          this.manager.loadBookings();
        } else {
          const messageError = result.data?.message || result.message || this.manager.strings.error || 'Unable to update bookings';
          this.manager.showNotice('error', messageError);
        }
      } catch (error) {
        console.error('BulkActionsManager.bulkUpdate', error);
        this.manager.showNotice('error', this.manager.strings.error || 'Unable to update bookings');
      }
    }

    async sendReminders() {
      const ids = Array.from(this.manager.selectedBookings);
      if (!ids.length) {
        this.manager.showNotice('error', this.manager.strings.no_bookings_selected || 'No bookings selected');
        return;
      }

      const confirmation = window.confirm(`Send reminder emails to ${ids.length} booking${ids.length === 1 ? '' : 's'}?`);
      if (!confirmation) {
        return;
      }

      const payload = new URLSearchParams({
        action: 'rb_send_bulk_reminders',
        nonce: this.manager.nonce,
        booking_ids: ids.join(',')
      });

      try {
        const response = await fetch(this.manager.ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload
        });
        const result = await response.json();
        if (result.success) {
          const message = result.data?.message || this.manager.strings.reminders_sent || 'Reminders sent';
          this.manager.showNotice('success', message);
          this.manager.clearSelection();
        } else {
          const message = result.data?.message || result.message || this.manager.strings.error || 'Unable to send reminders';
          this.manager.showNotice('error', message);
        }
      } catch (error) {
        console.error('BulkActionsManager.sendReminders', error);
        this.manager.showNotice('error', this.manager.strings.error || 'Unable to send reminders');
      }
    }
  }

  class BookingCalendarManager {
    constructor(manager) {
      this.manager = manager;
      this.currentDate = new Date();
      this.currentView = 'month';
      this.calendarData = {};
      this.bind();
    }

    bind() {
      const prev = document.getElementById('calendar-prev');
      if (prev) {
        prev.addEventListener('click', (event) => {
          event.preventDefault();
          this.navigate(-1);
        });
      }

      const next = document.getElementById('calendar-next');
      if (next) {
        next.addEventListener('click', (event) => {
          event.preventDefault();
          this.navigate(1);
        });
      }

      const today = document.getElementById('calendar-today');
      if (today) {
        today.addEventListener('click', (event) => {
          event.preventDefault();
          this.currentDate = new Date();
          this.loadCalendarData();
        });
      }

      document.querySelectorAll('.rb-calendar-view-btn').forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          const view = button.getAttribute('data-view');
          if (view && view !== this.currentView) {
            this.currentView = view;
            document.querySelectorAll('.rb-calendar-view-btn').forEach((btn) => {
              btn.classList.toggle('rb-active', btn.getAttribute('data-view') === view);
            });
            this.loadCalendarData();
          }
        });
      });
    }

    navigate(direction) {
      const newDate = new Date(this.currentDate);
      newDate.setMonth(newDate.getMonth() + direction);
      this.currentDate = newDate;
      this.loadCalendarData();
    }

    async loadCalendarData() {
      if (!this.manager.ajaxUrl) {
        return;
      }
      const payload = new URLSearchParams({
        action: 'rb_get_calendar_data',
        nonce: this.manager.nonce,
        month: String(this.currentDate.getMonth() + 1),
        year: String(this.currentDate.getFullYear()),
        view: this.currentView,
        dateFrom: this.manager.filters.dateFrom || '',
        dateTo: this.manager.filters.dateTo || '',
        status: this.manager.filters.status || '',
        location: this.manager.filters.location || '',
        search: this.manager.filters.search || ''
      });

      try {
        const response = await fetch(this.manager.ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: payload
        });
        const result = await response.json();
        if (result.success) {
          this.calendarData = result.data || {};
          this.render();
        } else {
          const message = result.data?.message || result.message || this.manager.strings.error || 'Unable to load calendar';
          this.manager.showNotice('error', message);
        }
      } catch (error) {
        console.error('BookingCalendarManager.loadCalendarData', error);
        this.manager.showNotice('error', this.manager.strings.error || 'Unable to load calendar');
      }
    }

    render() {
      const title = document.getElementById('calendar-month-year');
      if (title) {
        title.textContent = this.currentDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
      }
      this.renderDays();
    }

    renderDays() {
      const container = document.getElementById('calendar-days');
      if (!container) {
        return;
      }

      const year = this.currentDate.getFullYear();
      const month = this.currentDate.getMonth();
      const firstDay = new Date(year, month, 1);
      const startDay = (firstDay.getDay() + 6) % 7; // Convert Sunday=0 to Monday=0
      const daysInMonth = new Date(year, month + 1, 0).getDate();

      const prevMonthDays = [];
      for (let i = startDay; i > 0; i -= 1) {
        const date = new Date(year, month, 1 - i);
        prevMonthDays.push(this.renderDayCell(date, true));
      }

      const currentMonthDays = [];
      for (let day = 1; day <= daysInMonth; day += 1) {
        const date = new Date(year, month, day);
        currentMonthDays.push(this.renderDayCell(date, false));
      }

      const totalCells = prevMonthDays.length + currentMonthDays.length;
      const nextMonthDays = [];
      const remainder = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
      for (let i = 1; i <= remainder; i += 1) {
        const date = new Date(year, month + 1, i);
        nextMonthDays.push(this.renderDayCell(date, true));
      }

      container.innerHTML = [...prevMonthDays, ...currentMonthDays, ...nextMonthDays].join('');
      this.bindDayEvents();
    }

    renderDayCell(date, otherMonth) {
      const key = formatDateISO(date);
      const data = this.calendarData[key] || { bookings: [], count: 0 };
      const isToday = formatDateISO(new Date()) === key;
      const classes = ['rb-calendar-day'];
      if (otherMonth) {
        classes.push('rb-other-month');
      }
      if (isToday) {
        classes.push('rb-today');
      }

      const bookings = Array.isArray(data.bookings) ? data.bookings.slice(0, 3) : [];
      const bookingsHtml = bookings.map((booking) => {
        const status = (booking.status || '').toLowerCase().replace(/[^a-z0-9]+/g, '-');
        return `
          <div class="rb-calendar-booking rb-${escapeHtml(status)}" data-booking-id="${escapeHtml(booking.id)}">
            ${escapeHtml(booking.time)} – ${escapeHtml(booking.customer_name)}
          </div>
        `;
      }).join('');

      const countBadge = data.count > bookings.length
        ? `<div class="rb-calendar-booking-count">+${escapeHtml(data.count - bookings.length)}</div>`
        : '';

      return `
        <div class="${classes.join(' ')}" data-date="${key}">
          <div class="rb-calendar-day-number">${date.getDate()}</div>
          <div class="rb-calendar-bookings">${bookingsHtml}</div>
          ${countBadge}
        </div>
      `;
    }

    bindDayEvents() {
      document.querySelectorAll('.rb-calendar-day').forEach((cell) => {
        cell.addEventListener('click', () => {
          const date = cell.getAttribute('data-date');
          document.dispatchEvent(new CustomEvent('rb:calendar-day', { detail: { date } }));
        });
      });

      document.querySelectorAll('.rb-calendar-booking').forEach((item) => {
        item.addEventListener('click', (event) => {
          event.stopPropagation();
          const bookingId = item.getAttribute('data-booking-id');
          document.dispatchEvent(new CustomEvent('rb:booking-view', { detail: { bookingId } }));
        });
      });
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.rb-booking-management')) {
      // eslint-disable-next-line no-new
      new BookingManagement();
    }
  });
})();
