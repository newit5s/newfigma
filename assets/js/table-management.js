(function () {
  'use strict';

  const config = window.rbTableManager || {};

  class TableManagement {
    constructor(root) {
      this.root = root;
      this.canvas = root.querySelector('[data-floor-canvas]');
      this.propertiesPanel = root.querySelector('[data-table-properties]');
      this.tableListBody = root.querySelector('[data-table-list]');
      this.analyticsRoot = root.querySelector('[data-table-analytics]');
      this.saveButton = root.querySelector('#save-layout');
      this.resetButton = root.querySelector('#reset-layout');
      this.addButton = root.querySelector('#add-table');
      this.tabButtons = Array.from(root.querySelectorAll('.rb-tab-btn'));
      this.panels = Array.from(root.querySelectorAll('.rb-tab-panel'));
      this.noticeRegion = root.querySelector('[data-table-notice]');
      this.strings = config.strings || {};

      this.tables = (config.tables || []).map((table) => ({ ...table }));
      this.originalTables = JSON.parse(JSON.stringify(this.tables));
      this.selectedTableId = null;
      this.dragState = null;

      this.bindEvents();
      this.renderAll();
    }

    t(key, fallback) {
      return Object.prototype.hasOwnProperty.call(this.strings, key) ? this.strings[key] : fallback;
    }

    bindEvents() {
      if (this.saveButton) {
        this.saveButton.addEventListener('click', () => this.saveLayout());
      }

      if (this.resetButton) {
        this.resetButton.addEventListener('click', () => this.resetLayout());
      }

      if (this.addButton) {
        this.addButton.addEventListener('click', () => this.addTable());
      }

      this.tabButtons.forEach((btn) => {
        btn.addEventListener('click', () => this.activateTab(btn.dataset.tab));
      });
    }

    activateTab(tab) {
      this.tabButtons.forEach((btn) => {
        btn.classList.toggle('rb-active', btn.dataset.tab === tab);
      });

      this.panels.forEach((panel) => {
        panel.classList.toggle('rb-active', panel.dataset.tab === tab);
        if (panel.dataset.tab === tab) {
          panel.removeAttribute('aria-hidden');
        } else {
          panel.setAttribute('aria-hidden', 'true');
        }
      });
    }

    renderAll() {
      this.renderFloorPlan();
      this.renderTableList();
      this.renderAnalytics();
      if (this.tables.length) {
        this.selectTable(this.tables[0].id);
      } else {
        this.renderProperties(null);
      }
    }

    renderFloorPlan() {
      if (!this.canvas) {
        return;
      }

      this.canvas.innerHTML = '<div class="rb-floor-plan-grid" aria-hidden="true"></div>';
      this.tables.forEach((table) => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'rb-floor-table';
        el.dataset.tableId = table.id;
        el.dataset.status = table.status || 'available';
        el.dataset.shape = table.shape || 'rectangle';
        el.style.left = `${table.position_x}px`;
        el.style.top = `${table.position_y}px`;
        el.style.width = table.width ? `${table.width}px` : '';
        el.style.height = table.height ? `${table.height}px` : '';
        const seatsLabel = this.t('seats', 'seats');
        el.setAttribute('aria-label', `${table.label || table.name} – ${table.capacity} ${seatsLabel}`);
        el.innerHTML = `
          <span class="rb-table-label">${table.label || table.name}</span>
          <span class="rb-table-capacity">${table.capacity || 0} ${seatsLabel}</span>
        `;

        el.addEventListener('click', () => this.selectTable(table.id));
        el.addEventListener('pointerdown', (event) => this.startDrag(event, table.id));

        this.canvas.appendChild(el);
      });
    }

    renderTableList() {
      if (!this.tableListBody) {
        return;
      }

      if (!this.tables.length) {
        this.tableListBody.innerHTML = `<tr><td colspan="6">${this.t('no_tables', 'No tables defined yet.')}</td></tr>`;
        return;
      }

      this.tableListBody.innerHTML = this.tables
        .map((table) => {
          return `
            <tr data-table-row="${table.id}">
              <td>${table.label || table.name}</td>
              <td>${table.capacity}</td>
              <td>${this.formatStatusBadge(table.status)}</td>
              <td>${table.position_x}, ${table.position_y}</td>
              <td>${table.shape || 'rectangle'}</td>
              <td>
                <button type="button" class="rb-btn rb-btn-xs rb-btn-outline" data-action="focus" data-table="${table.id}">
                  ${this.t('focus', 'Focus')}
                </button>
              </td>
            </tr>
          `;
        })
        .join('');

      const focusButtons = this.tableListBody.querySelectorAll('[data-action="focus"]');
      focusButtons.forEach((button) => {
        button.addEventListener('click', () => {
          const tableId = button.dataset.table;
          this.selectTable(tableId);
          this.activateTab('floor-plan');
          this.scrollTableIntoView(tableId);
        });
      });
    }

    renderAnalytics() {
      if (!this.analyticsRoot) {
        return;
      }

      const totals = this.tables.reduce(
        (acc, table) => {
          acc.capacity += Number(table.capacity || 0);
          acc.count += 1;
          const status = table.status || 'available';
          acc[status] = (acc[status] || 0) + 1;
          return acc;
        },
        { count: 0, capacity: 0 }
      );

      const occupancyRate = totals.count ? Math.round(((totals.occupied || 0) / totals.count) * 100) : 0;

      this.analyticsRoot.innerHTML = `
        <div class="rb-analytics-grid">
          <div class="rb-analytics-card">
            <div class="rb-summary-label">${this.t('total_tables', 'Total tables')}</div>
            <div class="rb-analytics-value">${totals.count}</div>
          </div>
          <div class="rb-analytics-card">
            <div class="rb-summary-label">${this.t('total_capacity', 'Total seats')}</div>
            <div class="rb-analytics-value">${totals.capacity}</div>
          </div>
          <div class="rb-analytics-card">
            <div class="rb-summary-label">${this.t('occupancy', 'Occupied')}</div>
            <div class="rb-analytics-value">${totals.occupied || 0}</div>
          </div>
          <div class="rb-analytics-card">
            <div class="rb-summary-label">${this.t('occupancy_rate', 'Occupancy rate')}</div>
            <div class="rb-analytics-value">${occupancyRate}%</div>
          </div>
        </div>
      `;
    }

    selectTable(tableId) {
      this.selectedTableId = tableId;
      const selected = this.tables.find((table) => String(table.id) === String(tableId));
      this.renderProperties(selected || null);

      const tableButtons = this.root.querySelectorAll('.rb-floor-table');
      tableButtons.forEach((el) => {
        el.classList.toggle('rb-selected', el.dataset.tableId === String(tableId));
      });

      if (this.tableListBody) {
        const rows = this.tableListBody.querySelectorAll('tr');
        rows.forEach((row) => {
          row.classList.toggle('rb-active', row.dataset.tableRow === String(tableId));
        });
      }
    }

    renderProperties(table) {
      if (!this.propertiesPanel) {
        return;
      }

      if (!table) {
        this.propertiesPanel.innerHTML = `
          <header>
            <div>
              <h2 class="rb-page-title">${this.t('no_table_selected', 'No table selected')}</h2>
              <p class="rb-page-subtitle">${this.t('select_table_hint', 'Choose a table from the floor plan to edit its details.')}</p>
            </div>
          </header>
        `;
        return;
      }

      this.propertiesPanel.innerHTML = `
        <header>
          <div>
            <h2 class="rb-page-title">${table.label || table.name}</h2>
            <p class="rb-page-subtitle">${this.t('table_properties', 'Update table attributes and seating details.')}</p>
          </div>
          <span class="rb-table-status-badge ${this.statusClass(table.status)}">${this.formatStatus(table.status)}</span>
        </header>
        <div class="rb-property-grid">
          <div class="rb-property-item">
            <span class="rb-property-label">${this.t('capacity', 'Capacity')}</span>
            <span class="rb-property-value">${table.capacity}</span>
          </div>
          <div class="rb-property-item">
            <span class="rb-property-label">${this.t('status', 'Status')}</span>
            <span class="rb-property-value">${this.formatStatus(table.status)}</span>
          </div>
          <div class="rb-property-item">
            <span class="rb-property-label">${this.t('position', 'Position')}</span>
            <span class="rb-property-value">${table.position_x}, ${table.position_y}</span>
          </div>
          <div class="rb-property-item">
            <span class="rb-property-label">${this.t('shape', 'Shape')}</span>
            <span class="rb-property-value">${table.shape || 'Rectangle'}</span>
          </div>
        </div>
        <div class="rb-table-actions">
          <button type="button" class="rb-btn rb-btn-sm rb-btn-outline" data-action="duplicate">${this.t('duplicate', 'Duplicate')}</button>
          <button type="button" class="rb-btn rb-btn-sm rb-btn-outline" data-action="rotate">${this.t('rotate', 'Rotate')}</button>
          <button type="button" class="rb-btn rb-btn-sm rb-btn-outline" data-action="status">${this.t('toggle_status', 'Toggle status')}</button>
          <button type="button" class="rb-btn rb-btn-sm rb-btn-error" data-action="delete">${this.t('delete', 'Delete')}</button>
        </div>
      `;

      const actionButtons = this.propertiesPanel.querySelectorAll('[data-action]');
      actionButtons.forEach((button) => {
        button.addEventListener('click', () => this.handleAction(button.dataset.action));
      });
    }

    handleAction(action) {
      const table = this.tables.find((item) => String(item.id) === String(this.selectedTableId));
      if (!table) {
        return;
      }

      switch (action) {
        case 'duplicate':
          this.duplicateTable(table);
          break;
        case 'rotate':
          table.rotation = (table.rotation || 0) + 45;
          this.notice(this.t('table_rotated', 'Table rotated.'));
          break;
        case 'status':
          table.status = this.nextStatus(table.status);
          this.notice(this.t('status_updated', 'Table status updated.'));
          break;
        case 'delete':
          this.deleteTable(table.id);
          break;
        default:
          break;
      }
      this.renderAll();
    }

    duplicateTable(table) {
      const newId = `new-${Date.now()}`;
      const clone = {
        ...table,
        id: newId,
        label: `${table.label || table.name}*`,
        position_x: Number(table.position_x) + 24,
        position_y: Number(table.position_y) + 24,
        status: 'available',
      };
      this.tables.push(clone);
      this.selectTable(newId);
      this.notice(this.t('table_duplicated', 'Table duplicated.'));
    }

    deleteTable(tableId) {
      this.tables = this.tables.filter((item) => String(item.id) !== String(tableId));
      if (String(this.selectedTableId) === String(tableId)) {
        this.selectedTableId = null;
      }
      this.notice(this.t('table_deleted', 'Table removed from layout.'));
    }

    nextStatus(current) {
      const sequence = ['available', 'reserved', 'occupied', 'cleaning'];
      const index = sequence.indexOf(current);
      const nextIndex = index >= 0 ? (index + 1) % sequence.length : 0;
      return sequence[nextIndex];
    }

    startDrag(event, tableId) {
      event.preventDefault();
      const table = this.tables.find((item) => String(item.id) === String(tableId));
      if (!table || !this.canvas) {
        return;
      }

      this.selectTable(tableId);
      const rect = this.canvas.getBoundingClientRect();
      this.dragState = {
        table,
        offsetX: event.clientX - rect.left - table.position_x,
        offsetY: event.clientY - rect.top - table.position_y,
      };

      document.addEventListener('pointermove', this.handleDrag);
      document.addEventListener('pointerup', this.stopDrag, { once: true });
    }

    handleDrag = (event) => {
      if (!this.dragState || !this.canvas) {
        return;
      }

      const rect = this.canvas.getBoundingClientRect();
      const x = Math.max(0, Math.min(rect.width, event.clientX - rect.left - this.dragState.offsetX));
      const y = Math.max(0, Math.min(rect.height, event.clientY - rect.top - this.dragState.offsetY));

      this.dragState.table.position_x = Math.round(x);
      this.dragState.table.position_y = Math.round(y);

      const element = this.canvas.querySelector(`[data-table-id="${this.dragState.table.id}"]`);
      if (element) {
        element.style.left = `${this.dragState.table.position_x}px`;
        element.style.top = `${this.dragState.table.position_y}px`;
      }

      this.renderProperties(this.dragState.table);
      this.renderTableList();
    };

    stopDrag = () => {
      this.dragState = null;
      document.removeEventListener('pointermove', this.handleDrag);
    };

    addTable() {
      const newId = `new-${Date.now()}`;
      const label = `${this.t('table', 'Table')} ${this.tables.length + 1}`;
      this.tables.push({
        id: newId,
        label,
        name: label,
        capacity: 4,
        status: 'available',
        position_x: 120,
        position_y: 120,
        shape: 'rectangle',
      });
      this.renderAll();
      this.selectTable(newId);
      this.notice(this.t('table_added', 'Table added to layout.'));
    }

    saveLayout() {
      if (!config.ajax_url) {
        this.notice(this.t('missing_ajax', 'AJAX endpoint unavailable.'), true);
        return;
      }

      const payload = this.tables.map((table) => ({
        id: table.id,
        position_x: table.position_x,
        position_y: table.position_y,
        status: table.status,
        capacity: table.capacity,
        shape: table.shape,
        rotation: table.rotation || 0,
        label: table.label || table.name,
      }));

      this.toggleLoading(true);

      fetch(config.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: new URLSearchParams({
          action: 'rb_save_table_layout',
          nonce: config.nonce,
          location: config.current_location || '',
          tables: JSON.stringify(payload),
        }),
      })
        .then((response) => response.json())
        .then((response) => {
          if (response.success) {
            this.originalTables = JSON.parse(JSON.stringify(this.tables));
            this.notice(this.t('layout_saved', 'Layout saved successfully.'));
          } else {
            const message = response && response.data && response.data.message ? response.data.message : this.t('save_failed', 'Unable to save layout.');
            this.notice(message, true);
          }
        })
        .catch(() => this.notice(this.t('save_failed', 'Unable to save layout.'), true))
        .finally(() => this.toggleLoading(false));
    }

    resetLayout() {
      this.tables = JSON.parse(JSON.stringify(this.originalTables));
      this.renderAll();
      this.notice(this.t('layout_reset', 'Layout reset to last saved version.'));
    }

    scrollTableIntoView(tableId) {
      if (!this.canvas) {
        return;
      }
      const element = this.canvas.querySelector(`[data-table-id="${tableId}"]`);
      if (!element) {
        return;
      }
      element.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
      element.classList.add('rb-selected');
      setTimeout(() => element.classList.remove('rb-selected'), 800);
    }

    statusClass(status) {
      switch (status) {
        case 'occupied':
          return 'rb-status-occupied';
        case 'reserved':
          return 'rb-status-reserved';
        case 'cleaning':
          return 'rb-status-cleaning';
        default:
          return 'rb-status-available';
      }
    }

    formatStatus(status) {
      if (this.strings.statuses && this.strings.statuses[status]) {
        return this.strings.statuses[status];
      }
      return status || 'available';
    }

    formatStatusBadge(status) {
      const label = this.formatStatus(status);
      return `<span class="rb-table-status-badge ${this.statusClass(status)}">${label}</span>`;
    }

    notice(message, isError) {
      if (!this.noticeRegion) {
        return;
      }
      const toast = document.createElement('div');
      toast.className = `rb-toast ${isError ? 'rb-toast-error' : 'rb-toast-success'}`;
      toast.textContent = message;
      this.noticeRegion.appendChild(toast);
      setTimeout(() => {
        toast.classList.add('rb-toast-visible');
      }, 16);
      setTimeout(() => {
        toast.classList.remove('rb-toast-visible');
        setTimeout(() => toast.remove(), 320);
      }, 3600);
    }

    toggleLoading(isLoading) {
      if (this.saveButton) {
        this.saveButton.disabled = isLoading;
      }
      if (this.resetButton) {
        this.resetButton.disabled = isLoading;
      }
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.rb-table-management').forEach((root) => {
      if (!root.dataset.initialized) {
        root.dataset.initialized = 'true';
        new TableManagement(root);
      }
    });
  });
})();
