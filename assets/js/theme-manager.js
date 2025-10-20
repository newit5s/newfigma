/**
 * Theme Manager
 * Handles light/dark theme toggling and persistence across sessions.
 */
class ThemeManager {
  constructor({ toggleSelector = '#themeToggle', storageKey = 'rb-theme' } = {}) {
    this.toggleSelector = toggleSelector;
    this.storageKey = storageKey;
    this.toggleButton = null;

    this.handleToggleClick = this.handleToggleClick.bind(this);
    this.handleSystemChange = this.handleSystemChange.bind(this);

    document.addEventListener('DOMContentLoaded', () => this.init());
  }

  init() {
    this.toggleButton = document.querySelector(this.toggleSelector);
    this.applySavedTheme();
    this.setupToggle();
    this.observeSystemPreference();
  }

  applySavedTheme() {
    const savedTheme = this.getStoredTheme();

    if (savedTheme) {
      this.setTheme(savedTheme);
    } else {
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      this.setTheme(prefersDark ? 'dark' : 'light');
    }
  }

  setupToggle() {
    if (!this.toggleButton) return;

    this.updateToggleState();
    this.toggleButton.addEventListener('click', this.handleToggleClick);
    this.toggleButton.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        this.handleToggleClick();
      }
    });
  }

  handleToggleClick() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const nextTheme = currentTheme === 'light' ? 'dark' : 'light';
    this.setTheme(nextTheme);
    this.storeTheme(nextTheme);
    this.updateToggleState();
  }

  observeSystemPreference() {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    mediaQuery.addEventListener('change', this.handleSystemChange);
  }

  handleSystemChange(event) {
    const savedTheme = this.getStoredTheme();
    if (savedTheme) return; // respect explicit user choice

    const nextTheme = event.matches ? 'dark' : 'light';
    this.setTheme(nextTheme);
    this.updateToggleState();
  }

  updateToggleState() {
    if (!this.toggleButton) return;

    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const isDark = currentTheme === 'dark';
    this.toggleButton.setAttribute('aria-pressed', String(isDark));

    const sunIcon = this.toggleButton.querySelector('.rb-sun-icon');
    const moonIcon = this.toggleButton.querySelector('.rb-moon-icon');

    if (sunIcon) sunIcon.style.opacity = isDark ? '0.4' : '1';
    if (moonIcon) moonIcon.style.opacity = isDark ? '1' : '0.4';

    const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
    this.toggleButton.setAttribute('aria-label', label);
  }

  setTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
  }

  storeTheme(theme) {
    try {
      localStorage.setItem(this.storageKey, theme);
    } catch (error) {
      console.warn('ThemeManager: Unable to persist theme preference.', error);
    }
  }

  getStoredTheme() {
    try {
      return localStorage.getItem(this.storageKey);
    } catch (error) {
      console.warn('ThemeManager: Unable to read stored theme.', error);
      return null;
    }
  }
}

// Initialize with default options
new ThemeManager();
