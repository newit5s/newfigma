/**
 * Modern Restaurant Booking Manager - Portal Authentication
 * Handles login form interactions, accessibility and AJAX authentication flow.
 */
(function () {
  'use strict';

  const settings = window.rbPortalAuth || {};

  const DEFAULT_STRINGS = {
    requiredUsername: 'Enter your username.',
    requiredPassword: 'Enter your password.',
    invalidCredentials: 'The username or password is incorrect.',
    genericError: 'Unable to sign you in right now. Please try again.',
    networkError: 'Network error. Check your connection and retry.',
    success: 'Signed in successfully. Redirecting…',
    checkingSession: 'Checking your session…'
  };

  class PortalAuth {
    constructor(config) {
      this.config = config || {};
      this.strings = { ...DEFAULT_STRINGS, ...(this.config.strings || {}) };

      document.addEventListener('DOMContentLoaded', () => {
        this.cacheDom();
        if (!this.form) {
          return;
        }

        this.bindEvents();
        this.checkExistingSession();
      });
    }

    cacheDom() {
      this.form = document.getElementById('rb-portal-login');
      if (!this.form) {
        return;
      }

      this.submitBtn = this.form.querySelector('#login-submit');
      this.btnText = this.submitBtn ? this.submitBtn.querySelector('.rb-btn-text') : null;
      this.loadingSpinner = this.submitBtn ? this.submitBtn.querySelector('.rb-btn-loading') : null;
      this.usernameField = this.form.querySelector('#username');
      this.passwordField = this.form.querySelector('#password');
      this.rememberField = this.form.querySelector('#remember-me');
      this.usernameError = this.form.querySelector('#username-error');
      this.passwordError = this.form.querySelector('#password-error');
      this.errorAlert = document.getElementById('login-error');
      this.errorMessage = this.errorAlert ? this.errorAlert.querySelector('.rb-alert-message') : null;
      this.forgotPasswordLink = document.getElementById('forgot-password');
      this.passwordToggle = this.form.querySelector('.rb-password-toggle');
      this.redirectUrl = this.form.dataset.redirect || this.config.redirectUrl || '';
      this.redirecting = false;
      this.actions = {
        login: (this.config.actions && this.config.actions.login) || 'rb_portal_login',
        checkSession:
          (this.config.actions && this.config.actions.checkSession) || 'rb_portal_check_session'
      };
    }

    bindEvents() {
      this.form.addEventListener('submit', (event) => {
        event.preventDefault();
        this.handleSubmit();
      });

      if (this.usernameField) {
        this.usernameField.addEventListener('input', () => {
          this.clearFieldError(this.usernameError);
        });
      }

      if (this.passwordField) {
        this.passwordField.addEventListener('input', () => {
          this.clearFieldError(this.passwordError);
        });
      }

      if (this.passwordToggle && this.passwordField) {
        this.passwordToggle.addEventListener('click', () => {
          this.togglePasswordVisibility();
        });
      }

      if (this.forgotPasswordLink) {
        this.forgotPasswordLink.addEventListener('click', (event) => {
          const customEvent = new CustomEvent('rb:portal:forgot-password', {
            detail: {
              trigger: this.forgotPasswordLink,
              username: this.usernameField ? this.usernameField.value.trim() : ''
            },
            bubbles: true,
            cancelable: true
          });

          const prevented = !document.dispatchEvent(customEvent);
          if (prevented) {
            event.preventDefault();
          }
        });
      }
    }

    handleSubmit() {
      this.clearErrors();
      this.redirecting = false;

      const username = this.usernameField ? this.usernameField.value.trim() : '';
      const password = this.passwordField ? this.passwordField.value : '';

      let hasError = false;

      if (!username) {
        this.showFieldError(this.usernameError, this.strings.requiredUsername);
        if (this.usernameField) {
          this.usernameField.setAttribute('aria-invalid', 'true');
          this.usernameField.focus();
        }
        hasError = true;
      }

      if (!password) {
        this.showFieldError(this.passwordError, this.strings.requiredPassword);
        if (!hasError && this.passwordField) {
          this.passwordField.setAttribute('aria-invalid', 'true');
          this.passwordField.focus();
        }
        hasError = true;
      }

      if (hasError || !this.submitBtn) {
        this.triggerFormErrorState();
        return;
      }

      if (!this.config.ajax_url || !this.config.nonce) {
        this.showError(this.strings.genericError);
        return;
      }

      const formData = new FormData();
      formData.append('action', this.actions.login);
      formData.append('nonce', this.config.nonce);
      formData.append('username', username);
      formData.append('password', password);
      formData.append('remember', this.rememberField && this.rememberField.checked ? '1' : '0');
      formData.append('redirect', this.redirectUrl);

      this.setLoading(true);

      fetch(this.config.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      })
        .then((response) => response.json())
        .then((payload) => {
          if (payload && payload.success) {
            this.handleSuccess(payload.data);
            return;
          }

          const message = payload && payload.data && payload.data.message;
          this.showError(message || this.strings.invalidCredentials);
          this.triggerFormErrorState();
        })
        .catch(() => {
          this.showError(this.strings.networkError);
          this.triggerFormErrorState();
        })
        .finally(() => {
          if (!this.redirecting) {
            this.setLoading(false);
          }
        });
    }

    handleSuccess(data) {
      this.redirecting = true;

      if (!this.submitBtn || !this.btnText) {
        this.redirect(data);
        return;
      }

      this.submitBtn.disabled = true;
      this.submitBtn.setAttribute('aria-busy', 'true');
      this.submitBtn.classList.remove('rb-btn-error');
      this.btnText.textContent = this.strings.success;

      if (this.loadingSpinner) {
        this.loadingSpinner.style.display = 'none';
      }

      setTimeout(() => {
        this.redirect(data);
      }, 350);
    }

    redirect(data) {
      const fallback = this.config.redirectUrl || window.location.href;
      const dataRedirect = data && data.redirect_url;
      const redirectUrl = dataRedirect || this.redirectUrl || fallback;

      if (redirectUrl) {
        window.location.assign(redirectUrl);
      }
    }

    showFieldError(target, message) {
      if (!target) {
        return;
      }
      target.textContent = message;
      target.setAttribute('aria-hidden', 'false');
    }

    clearFieldError(target) {
      if (!target) {
        return;
      }
      target.textContent = '';
      target.setAttribute('aria-hidden', 'true');
    }

    clearErrors() {
      this.clearFieldError(this.usernameError);
      this.clearFieldError(this.passwordError);

      if (this.usernameField) {
        this.usernameField.removeAttribute('aria-invalid');
      }
      if (this.passwordField) {
        this.passwordField.removeAttribute('aria-invalid');
      }
      this.hideErrorAlert();
    }

    showError(message) {
      if (!this.errorAlert || !this.errorMessage) {
        return;
      }

      this.errorMessage.textContent = message;
      this.errorAlert.style.display = 'block';
      this.errorAlert.setAttribute('aria-hidden', 'false');
      this.errorAlert.classList.add('rb-alert-visible');
    }

    hideErrorAlert() {
      if (!this.errorAlert || !this.errorMessage) {
        return;
      }

      this.errorMessage.textContent = '';
      this.errorAlert.style.display = 'none';
      this.errorAlert.setAttribute('aria-hidden', 'true');
      this.errorAlert.classList.remove('rb-alert-visible');
    }

    triggerFormErrorState() {
      if (!this.form) {
        return;
      }

      this.form.classList.remove('rb-login-shake');
      void this.form.offsetWidth;
      this.form.classList.add('rb-login-shake');
    }

    setLoading(isLoading) {
      if (!this.submitBtn) {
        return;
      }

      this.submitBtn.disabled = isLoading;
      this.submitBtn.setAttribute('aria-busy', isLoading ? 'true' : 'false');

      if (this.loadingSpinner) {
        this.loadingSpinner.style.display = isLoading ? 'inline-flex' : 'none';
      }

      if (this.btnText && !isLoading) {
        this.btnText.textContent = this.config.strings && this.config.strings.submitLabel
          ? this.config.strings.submitLabel
          : 'Sign In';
      }
    }

    togglePasswordVisibility() {
      if (!this.passwordField) {
        return;
      }

      const type = this.passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
      this.passwordField.setAttribute('type', type);
      this.passwordToggle.setAttribute('aria-pressed', type === 'text');
    }

    checkExistingSession() {
      if (!this.config.checkSession || !this.config.ajax_url || !this.config.nonce) {
        return;
      }

      const formData = new FormData();
      formData.append('action', this.actions.checkSession);
      formData.append('nonce', this.config.nonce);

      fetch(this.config.ajax_url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      })
        .then((response) => response.json())
        .then((payload) => {
          if (!payload || !payload.success || !payload.data) {
            return;
          }

          if (payload.data.active) {
            this.handleSuccess(payload.data);
          }
        })
        .catch(() => {
          /* Silently ignore session check errors */
        });
    }
  }

  new PortalAuth(settings);
})();
