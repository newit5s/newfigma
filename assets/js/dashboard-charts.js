/*
 * Modern Restaurant Booking Manager - Dashboard Charts (Phase 4)
 * Handles Chart.js visualisations for the restaurant manager portal dashboard.
 */

(function () {
  'use strict';

  class DashboardCharts {
    constructor() {
      this.charts = {};
      this.currentPeriod = '7d';
      this.currentLocation = (typeof rbDashboard !== 'undefined' && rbDashboard.current_location) ? rbDashboard.current_location : '';
      this.ajaxUrl = (typeof rbDashboard !== 'undefined') ? rbDashboard.ajax_url : '';
      this.nonce = (typeof rbDashboard !== 'undefined') ? rbDashboard.nonce : '';

      this.init();
    }

    init() {
      if (typeof Chart === 'undefined') {
        console.warn('Chart.js is required for dashboard charts.');
        return;
      }

      this.initBookingTrendsChart();
      this.bindEvents();
      this.loadInitialData();
    }

    initBookingTrendsChart() {
      const canvas = document.getElementById('bookingTrendsChart');
      if (!canvas) {
        return;
      }

      const ctx = canvas.getContext('2d');
      if (!ctx) {
        return;
      }

      this.charts.bookingTrends = new Chart(ctx, {
        type: 'line',
        data: {
          labels: [],
          datasets: [
            {
              label: 'Total Bookings',
              data: [],
              borderColor: 'rgb(59, 130, 246)',
              backgroundColor: 'rgba(59, 130, 246, 0.15)',
              tension: 0.4,
              fill: true
            },
            {
              label: 'Confirmed',
              data: [],
              borderColor: 'rgb(16, 185, 129)',
              backgroundColor: 'rgba(16, 185, 129, 0.15)',
              tension: 0.4,
              fill: true
            },
            {
              label: 'Pending',
              data: [],
              borderColor: 'rgb(245, 158, 11)',
              backgroundColor: 'rgba(245, 158, 11, 0.15)',
              tension: 0.4,
              fill: true
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                color: 'rgba(148, 163, 184, 0.25)'
              },
              ticks: {
                precision: 0
              }
            },
            x: {
              grid: {
                display: false
              }
            }
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              mode: 'index',
              intersect: false,
              backgroundColor: 'rgba(15, 23, 42, 0.9)',
              borderColor: 'rgba(255, 255, 255, 0.1)',
              borderWidth: 1,
              padding: 12,
              titleColor: '#fff',
              bodyColor: '#fff'
            }
          },
          interaction: {
            mode: 'nearest',
            axis: 'x',
            intersect: false
          }
        }
      });
    }

    bindEvents() {
      document.querySelectorAll('.rb-chart-period [data-period]').forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          const { period } = event.currentTarget.dataset;
          if (!period || period === this.currentPeriod) {
            return;
          }
          this.changePeriod(period);
        });
      });

      const exportButton = document.getElementById('export-chart');
      if (exportButton) {
        exportButton.addEventListener('click', (event) => {
          event.preventDefault();
          this.exportChart();
        });
      }
    }

    async changePeriod(period) {
      this.currentPeriod = period;

      document.querySelectorAll('.rb-chart-period [data-period]').forEach((button) => {
        button.classList.toggle('rb-active', button.dataset.period === period);
      });

      await this.loadChartData();
    }

    async loadInitialData() {
      await this.loadChartData();
    }

    async loadChartData(locationId) {
      if (locationId) {
        this.currentLocation = locationId;
      }

      if (!this.ajaxUrl) {
        return;
      }

      this.showChartLoading(true);

      try {
        const response = await fetch(this.ajaxUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: new URLSearchParams({
            action: 'rb_get_dashboard_chart_data',
            nonce: this.nonce,
            period: this.currentPeriod,
            location_id: this.currentLocation
          })
        });

        const payload = await response.json();

        if (payload && payload.success && payload.data) {
          this.updateCharts(payload.data);
        } else {
          const message = payload && payload.data && payload.data.message
            ? payload.data.message
            : (payload && payload.message) || (rbDashboard && rbDashboard.strings ? rbDashboard.strings.error : 'Unable to load chart data');
          this.showChartError(message);
        }
      } catch (error) {
        console.error('Failed to load chart data:', error);
        this.showChartError('Failed to load chart data');
      } finally {
        this.showChartLoading(false);
      }
    }

    updateCharts(data) {
      if (!data) {
        return;
      }

      if (this.charts.bookingTrends && data.bookingTrends) {
        const chart = this.charts.bookingTrends;
        chart.data.labels = Array.isArray(data.bookingTrends.labels) ? data.bookingTrends.labels : [];
        chart.data.datasets[0].data = Array.isArray(data.bookingTrends.total) ? data.bookingTrends.total : [];
        chart.data.datasets[1].data = Array.isArray(data.bookingTrends.confirmed) ? data.bookingTrends.confirmed : [];
        chart.data.datasets[2].data = Array.isArray(data.bookingTrends.pending) ? data.bookingTrends.pending : [];
        chart.update('none');
      }
    }

    showChartLoading(show) {
      const loading = document.getElementById('chart-loading');
      if (!loading) {
        return;
      }

      loading.style.display = show ? 'flex' : 'none';
    }

    showChartError(message) {
      if (!message) {
        return;
      }
      console.error(message);
    }

    exportChart() {
      const chart = this.charts.bookingTrends;
      if (!chart) {
        return;
      }

      const link = document.createElement('a');
      link.download = `booking-trends-${this.currentPeriod}.png`;
      link.href = chart.toBase64Image();
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  }

  DashboardCharts.prototype.setLocation = function setLocation(locationId) {
    this.currentLocation = locationId;
  };

  window.DashboardCharts = DashboardCharts;
})();
