(function () {
  'use strict';

  const config = window.rbCustomerManager || {};

  class CustomerManagement {
    constructor(root) {
      this.root = root;
      this.listRoot = root.querySelector('[data-customer-list]');
      this.detailRoot = root.querySelector('[data-customer-detail]');
      this.searchInput = root.querySelector('#customer-search');
      this.segmentButtons = Array.from(root.querySelectorAll('[data-segment]'));
      this.addButton = root.querySelector('#add-customer');
      this.exportButton = root.querySelector('#export-customers');
      this.importButton = root.querySelector('#import-customers');
      this.noticeRegion = root.querySelector('[data-customer-notice]');
      this.strings = config.strings || {};

      this.customers = (config.customers || []).map((item) => ({ ...item }));
      this.filteredCustomers = [...this.customers];
      this.activeSegment = 'all';
      this.selectedId = null;

      this.bindEvents();
      this.applyFilters();
    }

    t(key, fallback) {
      return Object.prototype.hasOwnProperty.call(this.strings, key) ? this.strings[key] : fallback;
    }

    bindEvents() {
      if (this.searchInput) {
        this.searchInput.addEventListener('input', () => this.applyFilters());
      }

      this.segmentButtons.forEach((button) => {
        button.addEventListener('click', () => {
          this.activeSegment = button.dataset.segment;
          this.segmentButtons.forEach((el) => el.classList.toggle('rb-active', el === button));
          this.segmentButtons.forEach((el) => {
            el.setAttribute('aria-selected', el === button ? 'true' : 'false');
          });
          this.applyFilters();
        });
      });

      if (this.addButton) {
        this.addButton.addEventListener('click', () => this.notice(this.t('add_customer', 'Launching add customer flow…')));
      }
      if (this.exportButton) {
        this.exportButton.addEventListener('click', () => this.notice(this.t('export_started', 'Preparing export file…')));
      }
      if (this.importButton) {
        this.importButton.addEventListener('click', () => this.notice(this.t('import_started', 'Upload CSV with customer records.')));
      }
    }

    applyFilters() {
      const term = (this.searchInput ? this.searchInput.value : '').trim().toLowerCase();

      this.filteredCustomers = this.customers.filter((customer) => {
        const matchesSegment =
          this.activeSegment === 'all' ||
          (this.activeSegment === 'vip' && customer.status === 'vip') ||
          (this.activeSegment === 'regular' && customer.status === 'regular') ||
          (this.activeSegment === 'blacklist' && customer.status === 'blacklist');

        if (!matchesSegment) {
          return false;
        }

        if (!term) {
          return true;
        }

        const haystack = [customer.name, customer.email, customer.phone, customer.notes].join(' ').toLowerCase();
        return haystack.includes(term);
      });

      this.renderList();
      if (!this.selectedId && this.filteredCustomers.length) {
        this.selectCustomer(this.filteredCustomers[0].id);
      } else if (this.selectedId && !this.filteredCustomers.some((c) => String(c.id) === String(this.selectedId))) {
        const fallback = this.filteredCustomers[0] ? this.filteredCustomers[0].id : null;
        this.selectCustomer(fallback);
      }

      if (!this.filteredCustomers.length) {
        this.renderDetail(null);
      }
    }

    renderList() {
      if (!this.listRoot) {
        return;
      }

      if (!this.filteredCustomers.length) {
        this.listRoot.innerHTML = `<div class="rb-empty-state">${this.t('no_customers', 'No customers match the filters.')}</div>`;
        return;
      }

      this.listRoot.innerHTML = this.filteredCustomers
        .map((customer) => {
          const initials = customer.initials || this.getInitials(customer.name);
          const badges = this.buildBadges(customer);
          return `
            <article class="rb-customer-item ${this.selectedId === customer.id ? 'rb-active' : ''}" data-customer="${customer.id}">
              <div class="rb-customer-avatar">${initials}</div>
              <div class="rb-customer-meta">
                <span class="rb-customer-name">${customer.name}</span>
                <span class="rb-text-muted">${customer.email}</span>
                <div class="rb-customer-tags">${badges}</div>
              </div>
            </article>
          `;
        })
        .join('');

      const entries = this.listRoot.querySelectorAll('[data-customer]');
      entries.forEach((item) => {
        item.addEventListener('click', () => {
          const id = item.dataset.customer;
          this.selectCustomer(id);
        });
      });
    }

    selectCustomer(id) {
      this.selectedId = id ? String(id) : null;

      if (this.listRoot) {
        const entries = this.listRoot.querySelectorAll('[data-customer]');
        entries.forEach((item) => {
          item.classList.toggle('rb-active', item.dataset.customer === this.selectedId);
        });
      }

      const customer = this.customers.find((entry) => String(entry.id) === String(id)) || null;
      this.renderDetail(customer);
    }

    renderDetail(customer) {
      if (!this.detailRoot) {
        return;
      }

      if (!customer) {
        this.detailRoot.innerHTML = `
          <div class="rb-empty-state">
            <h2>${this.t('select_customer', 'Select a customer')}</h2>
            <p>${this.t('select_customer_hint', 'Choose a customer from the list to view their visit history and notes.')}</p>
          </div>
        `;
        return;
      }

      const summary = this.renderSummary(customer);
      const history = this.renderHistory(customer);
      const notes = this.renderNotes(customer);

      this.detailRoot.innerHTML = `
        <header class="rb-customer-detail-header">
          <div class="rb-customer-detail-info">
            <div class="rb-customer-avatar rb-avatar-lg">${customer.initials || this.getInitials(customer.name)}</div>
            <div>
              <h2 class="rb-page-title">${customer.name}</h2>
              <p class="rb-page-subtitle">${customer.email} • ${customer.phone}</p>
            </div>
          </div>
          <div class="rb-table-actions">
            <button type="button" class="rb-btn rb-btn-sm rb-btn-outline" data-action="vip">${this.t('mark_vip', 'Mark VIP')}</button>
            <button type="button" class="rb-btn rb-btn-sm rb-btn-outline" data-action="regular">${this.t('mark_regular', 'Mark Regular')}</button>
            <button type="button" class="rb-btn rb-btn-sm rb-btn-warning" data-action="blacklist">${this.t('mark_blacklist', 'Add to Blacklist')}</button>
            <button type="button" class="rb-btn rb-btn-sm rb-btn-primary" data-action="note">${this.t('add_note', 'Add Note')}</button>
          </div>
        </header>
        <section class="rb-customer-summary">${summary}</section>
        <section>
          <h3 class="rb-section-title">${this.t('recent_bookings', 'Recent bookings')}</h3>
          <div class="rb-history-list">${history}</div>
        </section>
        <section>
          <h3 class="rb-section-title">${this.t('notes_preferences', 'Notes & preferences')}</h3>
          <div class="rb-history-list">${notes}</div>
        </section>
      `;

      const actionButtons = this.detailRoot.querySelectorAll('[data-action]');
      actionButtons.forEach((button) => {
        button.addEventListener('click', () => this.handleAction(button.dataset.action, customer));
      });
    }

    renderSummary(customer) {
      const items = [
        { label: this.t('total_visits', 'Total visits'), value: customer.total_visits || 0 },
        { label: this.t('total_spent', 'Total spent'), value: customer.total_spent || 0 },
        { label: this.t('avg_party_size', 'Avg party size'), value: customer.avg_party_size || 0 },
        { label: this.t('last_visit', 'Last visit'), value: customer.last_visit || '—' },
      ];

      return items
        .map(
          (item) => `
            <article class="rb-summary-item">
              <span class="rb-summary-label">${item.label}</span>
              <span class="rb-summary-value">${item.value}</span>
            </article>
          `
        )
        .join('');
    }

    renderHistory(customer) {
      if (!customer.history || !customer.history.length) {
        return `<div class="rb-empty-state">${this.t('no_history', 'No bookings recorded yet.')}</div>`;
      }

      return customer.history
        .map(
          (entry) => `
            <article class="rb-history-item">
              <strong>${entry.date}</strong>
              <div>${entry.location || ''} • ${this.t('table', 'Table')} ${entry.table || ''}</div>
              <div>${this.t('party', 'Party')}: ${entry.party_size || 0} • ${this.t('status', 'Status')}: ${entry.status || ''}</div>
            </article>
          `
        )
        .join('');
    }

    renderNotes(customer) {
      const blocks = [];
      if (customer.notes) {
        blocks.push(`<article class="rb-history-item"><strong>${this.t('staff_notes', 'Staff notes')}</strong><p>${customer.notes}</p></article>`);
      }
      if (customer.preferences && customer.preferences.length) {
        blocks.push(
          `<article class="rb-history-item"><strong>${this.t('preferences', 'Preferences')}</strong><p>${customer.preferences.join(', ')}</p></article>`
        );
      }
      if (!blocks.length) {
        blocks.push(`<div class="rb-empty-state">${this.t('no_notes', 'No notes added yet.')}</div>`);
      }
      return blocks.join('');
    }

    handleAction(action, customer) {
      if (action === 'note') {
        this.notice(this.t('note_prompt', 'Open note editor modal.'));
        return;
      }

      const statusMap = {
        vip: 'vip',
        regular: 'regular',
        blacklist: 'blacklist',
      };

      if (!statusMap[action]) {
        return;
      }

      this.updateCustomerStatus(customer.id, statusMap[action]);
    }

    updateCustomerStatus(id, status) {
      const customer = this.customers.find((entry) => String(entry.id) === String(id));
      if (!customer) {
        return;
      }

      if (!config.ajax_url) {
        customer.status = status;
        this.notice(this.t('status_updated', 'Customer status updated'));
        this.applyFilters();
        return;
      }

      fetch(config.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: new URLSearchParams({
          action: 'rb_update_customer_status',
          nonce: config.nonce,
          customer_id: id,
          status,
        }),
      })
        .then((response) => response.json())
        .then((response) => {
          if (response.success) {
            customer.status = status;
            this.notice(this.t('status_updated', 'Customer status updated'));
            this.applyFilters();
          } else {
            const message = response && response.data && response.data.message ? response.data.message : this.t('update_failed', 'Unable to update status.');
            this.notice(message, true);
          }
        })
        .catch(() => this.notice(this.t('update_failed', 'Unable to update status.'), true));
    }

    buildBadges(customer) {
      const badges = [];
      const statusBadge = `<span class="rb-badge ${this.statusClass(customer.status)}">${this.formatStatus(customer.status)}</span>`;
      badges.push(statusBadge);

      (customer.tags || []).forEach((tag) => {
        badges.push(`<span class="rb-badge">${tag}</span>`);
      });

      return badges.join('');
    }

    statusClass(status) {
      switch (status) {
        case 'vip':
          return 'rb-badge-success';
        case 'blacklist':
          return 'rb-badge-error';
        default:
          return 'rb-badge-muted';
      }
    }

    formatStatus(status) {
      if (this.strings.statuses && this.strings.statuses[status]) {
        return this.strings.statuses[status];
      }
      return status || 'regular';
    }

    getInitials(name) {
      if (!name) {
        return '?';
      }
      const parts = name.trim().split(/\s+/).slice(0, 2);
      return parts.map((part) => part.charAt(0).toUpperCase()).join('');
    }

    notice(message, isError) {
      if (!this.noticeRegion) {
        return;
      }
      const toast = document.createElement('div');
      toast.className = `rb-toast ${isError ? 'rb-toast-error' : 'rb-toast-info'}`;
      toast.textContent = message;
      this.noticeRegion.appendChild(toast);
      setTimeout(() => toast.classList.add('rb-toast-visible'), 16);
      setTimeout(() => {
        toast.classList.remove('rb-toast-visible');
        setTimeout(() => toast.remove(), 320);
      }, 3400);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.rb-customer-management').forEach((root) => {
      if (!root.dataset.initialized) {
        root.dataset.initialized = 'true';
        new CustomerManagement(root);
      }
    });
  });
})();
