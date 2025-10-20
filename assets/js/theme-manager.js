/**
 * Global Theme Manager
 * Manages light/dark mode across entire application.
 */
class ThemeManager {
  constructor() {
    this.STORAGE_KEY = 'rb_theme_preference';
    this.THEME_ATTRIBUTE = 'data-theme';
    this.THEMES = {
      LIGHT: 'light',
      DARK: 'dark'
    };

    this.init();
  }

  init() {
    const savedTheme = this.getSavedTheme();
    const preferredTheme = savedTheme || this.getSystemPreference();

    this.setTheme(preferredTheme);
    this.setupToggleListener();
    this.watchSystemPreference();
  }

  getSavedTheme() {
    if (typeof Storage !== 'undefined') {
      try {
        return localStorage.getItem(this.STORAGE_KEY);
      } catch (error) {
        console.warn('ThemeManager: Unable to access localStorage.', error);
      }
    }
    return null;
  }

  getSystemPreference() {
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return this.THEMES.DARK;
    }
    return this.THEMES.LIGHT;
  }

  setTheme(theme) {
    if (!Object.values(this.THEMES).includes(theme)) {
      theme = this.THEMES.LIGHT;
    }

    document.documentElement.setAttribute(this.THEME_ATTRIBUTE, theme);

    if (typeof Storage !== 'undefined') {
      try {
        localStorage.setItem(this.STORAGE_KEY, theme);
      } catch (error) {
        console.warn('ThemeManager: Unable to persist theme preference.', error);
      }
    }

    document.dispatchEvent(new CustomEvent('themechange', {
      detail: { theme }
    }));
  }

  toggleTheme() {
    const current = this.getCurrentTheme();
    const next = current === this.THEMES.DARK ? this.THEMES.LIGHT : this.THEMES.DARK;
    this.setTheme(next);
  }

  getCurrentTheme() {
    return document.documentElement.getAttribute(this.THEME_ATTRIBUTE) || this.THEMES.LIGHT;
  }

  setupToggleListener() {
    const toggles = document.querySelectorAll('[id*="theme-toggle"], .rb-theme-toggle');

    toggles.forEach((btn) => {
      btn.addEventListener('click', (event) => {
        event.preventDefault();
        this.toggleTheme();
      });
    });
  }

  watchSystemPreference() {
    if (!window.matchMedia) return;

    const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');

    const listener = (event) => {
      if (!this.getSavedTheme()) {
        this.setTheme(event.matches ? this.THEMES.DARK : this.THEMES.LIGHT);
      }
    };

    if (typeof darkModeQuery.addEventListener === 'function') {
      darkModeQuery.addEventListener('change', listener);
    } else if (typeof darkModeQuery.addListener === 'function') {
      darkModeQuery.addListener(listener);
    }
  }

  onThemeChange(callback) {
    document.addEventListener('themechange', (event) => {
      callback(event.detail.theme);
    });
  }
}

if (typeof window !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      window.rbThemeManager = new ThemeManager();
    });
  } else {
    window.rbThemeManager = new ThemeManager();
  }
}

