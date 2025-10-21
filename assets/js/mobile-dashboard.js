/*
 * Portal dashboard mobile enhancements (Phase 8)
 */

(function () {
  'use strict';

  const config = window.rbMobileConfig || {};

  class MobileDashboardEnhancements {
    constructor() {
      this.shell = document.querySelector('.rb-mobile-shell');
      if (!this.shell) {
        return;
      }

      this.nav = document.getElementById('mobileNav');
      this.overlay = document.getElementById('mobileOverlay');
      this.hamburger = document.getElementById('hamburgerBtn');
      this.closeButton = document.getElementById('closeMobileNav');
      this.bottomNav = document.querySelectorAll('.rb-bottom-nav-item');
      this.quickActions = document.querySelectorAll('.rb-mobile-action-btn');
      this.dateLabel = document.getElementById('mobileScheduleDate');
      this.pullContainer = this.shell.querySelector('.rb-mobile-content');
      this.installBanner = null;
      this.deferredPrompt = null;
      this.openCard = null;
      this.pullIndicator = null;
      this.isPulling = false;
      this.pullStartY = 0;
      this.pullDistance = 0;
      this.pullThreshold = 80;

      this.registerEventListeners();
      this.setupSwipeActions();
      this.setupQuickActions();
      this.setupBottomNavigation();
      this.setupPullToRefresh();
      this.registerServiceWorker();
      this.setupInstallPrompt();
      this.setupOfflineDetection();
      this.updateCurrentDate();
      this.enhanceFocusStates();
    }

    registerEventListeners() {
      if (this.hamburger && this.nav && this.overlay) {
        this.hamburger.addEventListener('click', () => this.openNavigation());
        this.overlay.addEventListener('click', () => this.closeNavigation());

        if (this.closeButton) {
          this.closeButton.addEventListener('click', () => this.closeNavigation());
        }

        this.nav.addEventListener('keydown', (event) => {
          if (event.key === 'Escape') {
            this.closeNavigation();
          }
        });
      }

      document.addEventListener('click', (event) => {
        if (this.openCard && !this.openCard.contains(event.target)) {
          this.resetCard(this.openCard);
        }
      });

      window.addEventListener('appinstalled', () => {
        this.dismissInstallBanner();
        this.deferredPrompt = null;
      });
    }

    openNavigation() {
      if (!this.nav || !this.overlay) {
        return;
      }

      this.nav.classList.add('open');
      this.overlay.classList.add('rb-active');
      document.body.style.overflow = 'hidden';
      this.hapticFeedback();

      const firstNavItem = this.nav.querySelector('.rb-mobile-nav-item');
      if (firstNavItem) {
        firstNavItem.focus();
      }
    }

    closeNavigation() {
      if (!this.nav || !this.overlay) {
        return;
      }

      this.nav.classList.remove('open');
      this.overlay.classList.remove('rb-active');
      document.body.style.overflow = '';

      if (this.hamburger) {
        this.hamburger.focus();
      }
    }

    setupSwipeActions() {
      const cards = document.querySelectorAll('.rb-booking-card-mobile');
      cards.forEach((card) => this.attachSwipeHandlers(card));
    }

    attachSwipeHandlers(card) {
      const content = card.querySelector('.rb-booking-card-content');
      const actions = card.querySelector('.rb-booking-card-swipe-actions');

      if (!content || !actions) {
        return;
      }

      let startX = 0;
      let currentX = 0;
      let swiping = false;

      card.addEventListener('touchstart', (event) => {
        if (!event.touches || event.touches.length === 0) {
          return;
        }

        startX = event.touches[0].clientX;
        currentX = startX;
        swiping = true;
        this.openCard = null;
      }, { passive: true });

      card.addEventListener('touchmove', (event) => {
        if (!swiping || !event.touches || event.touches.length === 0) {
          return;
        }

        currentX = event.touches[0].clientX;
        const diffX = startX - currentX;
        if (diffX <= 0) {
          return;
        }

        event.preventDefault();
        const translate = Math.min(diffX, 220);
        content.style.transform = `translateX(-${translate}px)`;
        actions.style.right = `-${220 - translate}px`;
      }, { passive: false });

      card.addEventListener('touchend', () => {
        if (!swiping) {
          return;
        }

        swiping = false;
        const diffX = startX - currentX;
        if (diffX > 90) {
          content.style.transform = 'translateX(-220px)';
          actions.style.right = '0';
          this.openCard = card;
          this.hapticFeedback('medium');
        } else {
          this.resetCard(card);
        }
      }, { passive: true });

      actions.querySelectorAll('.rb-swipe-action').forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          const action = button.dataset.action;
          this.handleSwipeAction(card, action);
        });
      });
    }

    resetCard(card) {
      const content = card.querySelector('.rb-booking-card-content');
      const actions = card.querySelector('.rb-booking-card-swipe-actions');
      if (content && actions) {
        content.style.transform = '';
        actions.style.right = '-240px';
      }
      if (this.openCard === card) {
        this.openCard = null;
      }
    }

    handleSwipeAction(card, action) {
      const bookingId = card.dataset.bookingId;
      if (!bookingId) {
        return;
      }

      switch (action) {
        case 'confirm':
          this.updateBookingStatus(card, bookingId, 'confirmed');
          break;
        case 'reschedule':
          window.location.href = `${config.homeUrl}?rb_portal=bookings&view=calendar&booking=${encodeURIComponent(bookingId)}`;
          this.resetCard(card);
          break;
        case 'cancel':
          this.updateBookingStatus(card, bookingId, 'cancelled', true);
          break;
        default:
          this.closeNavigation();
          break;
      }
    }

    updateBookingStatus(card, bookingId, status, removeCard = false) {
      if (!config.ajaxUrl || !config.nonce) {
        return;
      }

      const params = new URLSearchParams({
        action: 'rb_update_booking_status',
        booking_id: bookingId,
        status,
        nonce: config.nonce,
      });

      fetch(config.ajaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: params.toString(),
      })
        .then((response) => response.json())
        .then((payload) => {
          if (payload && payload.success) {
            this.applyStatusUpdate(card, status, removeCard);
            const key = status === 'confirmed' ? 'confirmSuccess' : 'cancelSuccess';
            this.showToast(config.strings && config.strings[key] ? config.strings[key] : 'Success', 'success');
            this.hapticFeedback('success');
          } else {
            this.showToast(this.getErrorMessage(payload), 'error');
            this.hapticFeedback('error');
            this.resetCard(card);
          }
        })
        .catch(() => {
          this.showToast(config.strings && config.strings.actionError ? config.strings.actionError : 'Error', 'error');
          this.hapticFeedback('error');
          this.resetCard(card);
        });
    }

    applyStatusUpdate(card, status, removeCard) {
      if (removeCard) {
        card.classList.add('slide-out');
        window.setTimeout(() => card.remove(), 280);
        return;
      }

      const statusBadge = card.querySelector('.rb-booking-status');
      if (statusBadge) {
        statusBadge.className = `rb-booking-status rb-status-${status}`;
        statusBadge.textContent = status.replace(/^(.)/, (match) => match.toUpperCase());
      }

      this.resetCard(card);
    }

    getErrorMessage(payload) {
      if (payload && payload.data && payload.data.message) {
        return payload.data.message;
      }

      return config.strings && config.strings.actionError ? config.strings.actionError : 'Error';
    }

    setupQuickActions() {
      if (!this.quickActions || this.quickActions.length === 0) {
        return;
      }

      this.quickActions.forEach((button) => {
        button.addEventListener('click', () => {
          const action = button.dataset.action;
          this.handleQuickAction(action);
        });
      });
    }

    handleQuickAction(action) {
      const dashboard = window.rbDashboard || {};

      switch (action) {
        case 'new-booking':
          if (dashboard.calendar_url) {
            window.location.href = dashboard.calendar_url;
          }
          break;
        case 'walk-in':
          if (dashboard.tables_url) {
            window.location.href = dashboard.tables_url;
          }
          break;
        case 'pending':
          window.location.href = `${config.homeUrl}?rb_portal=bookings&status=pending`;
          break;
        case 'tables':
          if (dashboard.tables_url) {
            window.location.href = dashboard.tables_url;
          }
          break;
        case 'reports':
          if (dashboard.reports_url) {
            window.location.href = dashboard.reports_url;
          }
          break;
        default:
          break;
      }
    }

    setupBottomNavigation() {
      if (!this.bottomNav) {
        return;
      }

      const current = new URL(window.location.href);
      const view = current.searchParams.get('rb_portal') || 'dashboard';

      this.bottomNav.forEach((link) => {
        const target = link.dataset.nav;
        if (target === view) {
          link.classList.add('active');
        }
        link.addEventListener('click', () => {
          this.bottomNav.forEach((item) => item.classList.remove('active'));
          link.classList.add('active');
        });
      });
    }

    setupPullToRefresh() {
      if (!this.pullContainer) {
        return;
      }

      const indicator = document.createElement('div');
      indicator.className = 'rb-pull-indicator';
      indicator.innerHTML = '<svg class="rb-pull-arrow" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M7 14l5-5 5 5z"/></svg>';
      this.pullContainer.prepend(indicator);
      this.pullIndicator = indicator;

      this.pullContainer.addEventListener('touchstart', (event) => {
        if (window.scrollY > 0) {
          return;
        }

        this.isPulling = true;
        this.pullStartY = event.touches ? event.touches[0].clientY : 0;
        this.pullDistance = 0;
      }, { passive: true });

      this.pullContainer.addEventListener('touchmove', (event) => {
        if (!this.isPulling || window.scrollY > 0) {
          return;
        }

        const currentY = event.touches ? event.touches[0].clientY : 0;
        this.pullDistance = currentY - this.pullStartY;

        if (this.pullDistance <= 0) {
          return;
        }

        event.preventDefault();
        const clamped = Math.min(this.pullDistance, this.pullThreshold * 2);
        indicator.style.top = `${clamped - 60}px`;
        indicator.style.opacity = Math.min(clamped / this.pullThreshold, 1).toString();

        if (this.pullDistance >= this.pullThreshold) {
          indicator.classList.add('ready');
        } else {
          indicator.classList.remove('ready');
        }
      }, { passive: false });

      this.pullContainer.addEventListener('touchend', () => {
        if (!this.isPulling) {
          return;
        }

        this.isPulling = false;
        if (this.pullDistance >= this.pullThreshold) {
          this.triggerRefresh();
        } else {
          this.resetPullIndicator();
        }
      }, { passive: true });
    }

    triggerRefresh() {
      if (!this.pullIndicator || this.refreshing) {
        return;
      }

      this.refreshing = true;
      this.pullIndicator.classList.add('loading');
      this.pullIndicator.innerHTML = '<div class="rb-mobile-loading-spinner" aria-hidden="true"></div>';
      this.pullIndicator.style.top = '12px';

      const refreshAction = window.rbPortalDashboard && typeof window.rbPortalDashboard.refreshData === 'function'
        ? window.rbPortalDashboard.refreshData()
        : Promise.resolve();

      Promise.resolve(refreshAction)
        .then(() => {
          this.showToast(config.strings && config.strings.refreshed ? config.strings.refreshed : 'Updated', 'success');
        })
        .finally(() => {
          this.refreshing = false;
          this.resetPullIndicator();
        });
    }

    resetPullIndicator() {
      if (!this.pullIndicator) {
        return;
      }

      this.pullIndicator.classList.remove('ready', 'loading');
      this.pullIndicator.style.top = '-60px';
      this.pullIndicator.style.opacity = '0';
      this.pullIndicator.innerHTML = '<svg class="rb-pull-arrow" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M7 14l5-5 5 5z"/></svg>';
      this.pullDistance = 0;
    }

    registerServiceWorker() {
      if (!('serviceWorker' in navigator) || !config.serviceWorker) {
        return;
      }

      navigator.serviceWorker.register(config.serviceWorker)
        .catch(() => {
          // Fail silently – service worker support is optional.
        });
    }

    setupInstallPrompt() {
      if (!window.matchMedia('(display-mode: standalone)').matches) {
        window.addEventListener('beforeinstallprompt', (event) => {
          event.preventDefault();
          this.deferredPrompt = event;
          this.renderInstallBanner();
        });
      }
    }

    renderInstallBanner() {
      if (this.installBanner || !config.manifestUrl) {
        return;
      }

      const banner = document.createElement('div');
      banner.className = 'rb-install-banner';
      banner.innerHTML = `
        <div class="rb-install-content">
          <div class="rb-install-icon">📱</div>
          <div class="rb-install-text">
            <div class="rb-install-title">${(config.strings && config.strings.installTitle) || 'Install App'}</div>
            <div class="rb-install-description">${(config.strings && config.strings.installMessage) || 'Add to your home screen for quick access.'}</div>
          </div>
          <div class="rb-install-actions">
            <button class="rb-btn rb-btn-sm rb-btn-primary" type="button" data-install="confirm">${(config.strings && config.strings.installAction) || 'Install'}</button>
            <button class="rb-btn rb-btn-sm rb-btn-outline" type="button" data-install="dismiss">${(config.strings && config.strings.dismissAction) || 'Dismiss'}</button>
          </div>
        </div>
      `;

      document.body.appendChild(banner);
      window.setTimeout(() => banner.classList.add('show'), 100);

      banner.querySelector('[data-install="confirm"]').addEventListener('click', () => this.promptInstall());
      banner.querySelector('[data-install="dismiss"]').addEventListener('click', () => this.dismissInstallBanner());

      this.installBanner = banner;
    }

    async promptInstall() {
      if (!this.deferredPrompt) {
        return;
      }

      this.deferredPrompt.prompt();
      await this.deferredPrompt.userChoice;
      this.deferredPrompt = null;
      this.dismissInstallBanner();
    }

    dismissInstallBanner() {
      if (!this.installBanner) {
        return;
      }

      this.installBanner.classList.remove('show');
      window.setTimeout(() => {
        if (this.installBanner) {
          this.installBanner.remove();
          this.installBanner = null;
        }
      }, 240);
    }

    setupOfflineDetection() {
      const show = () => {
        const banner = document.createElement('div');
        banner.className = 'rb-offline-banner';
        banner.innerHTML = `<span aria-hidden="true">⚠️</span><span>${(config.strings && config.strings.offline) || 'You are offline'}</span>`;
        document.body.appendChild(banner);
        window.setTimeout(() => banner.classList.add('show'), 50);
        this.offlineBanner = banner;
      };

      const hide = () => {
        if (this.offlineBanner) {
          this.offlineBanner.classList.remove('show');
          window.setTimeout(() => {
            if (this.offlineBanner) {
              this.offlineBanner.remove();
              this.offlineBanner = null;
            }
          }, 220);
        }
      };

      window.addEventListener('offline', show);
      window.addEventListener('online', () => {
        hide();
        this.showToast((config.strings && config.strings.online) || 'Online', 'success');
      });

      if (!navigator.onLine) {
        show();
      }
    }

    showToast(message, type = 'info') {
      const toast = document.createElement('div');
      toast.className = `rb-toast rb-toast-${type}`;
      toast.innerHTML = `
        <div class="rb-toast-content">
          <div class="rb-toast-message">${message}</div>
          <button class="rb-toast-close" type="button" aria-label="Close">×</button>
        </div>
      `;

      document.body.appendChild(toast);
      window.setTimeout(() => toast.classList.add('show'), 20);

      const close = () => {
        toast.classList.remove('show');
        window.setTimeout(() => toast.remove(), 200);
      };

      toast.querySelector('.rb-toast-close').addEventListener('click', close);
      window.setTimeout(close, 3200);
    }

    setupOfflineBanner() {
      // Deprecated method placeholder for backward compatibility.
    }

    updateCurrentDate() {
      const label = document.getElementById('mobileCurrentDate');
      const now = new Date();
      const formatted = now.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });

      if (label) {
        label.textContent = formatted;
      }

      if (this.dateLabel) {
        this.dateLabel.textContent = formatted;
      }
    }

    enhanceFocusStates() {
      document.addEventListener('touchstart', () => document.body.classList.add('using-touch'), { passive: true });
      document.addEventListener('keydown', (event) => {
        if (event.key === 'Tab') {
          document.body.classList.remove('using-touch');
        }
      });
    }

    hapticFeedback(level = 'light') {
      if (!navigator.vibrate) {
        return;
      }

      const patterns = {
        light: [10],
        medium: [20],
        success: [15, 40, 15],
        error: [30, 60, 30],
      };

      const pattern = patterns[level] || patterns.light;
      navigator.vibrate(pattern);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.rb-mobile-shell')) {
      window.rbMobileDashboard = new MobileDashboardEnhancements();
    }
  });
})();
