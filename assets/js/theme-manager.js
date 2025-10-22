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
      DARK: 'dark',
      SYSTEM: 'system',
    };
    this.preference = this.THEMES.SYSTEM;
    this.activeTheme = this.THEMES.LIGHT;

    this.init();
  }

  init() {
    const savedPreference = this.getSavedTheme();
    const preferredTheme = savedPreference || this.THEMES.SYSTEM;

    this.applyPreference(preferredTheme);
    this.setupToggleListener();
    this.watchSystemPreference();
  }

  getSavedTheme() {
    if (typeof Storage !== 'undefined') {
      try {
        const stored = localStorage.getItem(this.STORAGE_KEY);
        if (!stored) {
          return null;
        }
        if (Object.values(this.THEMES).includes(stored)) {
          return stored;
        }
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
    this.applyPreference(theme);
  }

  applyPreference(preference) {
    let resolvedPreference = preference;
    if (!Object.values(this.THEMES).includes(resolvedPreference)) {
      resolvedPreference = this.THEMES.LIGHT;
    }

    const theme = this.resolveTheme(resolvedPreference);
    this.preference = resolvedPreference;
    this.activeTheme = theme;
    this.persistPreference(resolvedPreference);
    document.documentElement.setAttribute(this.THEME_ATTRIBUTE, theme);
    this.dispatchThemeChange(theme, resolvedPreference);
  }

  resolveTheme(preference) {
    if (preference === this.THEMES.DARK || preference === this.THEMES.LIGHT) {
      return preference;
    }
    return this.getSystemPreference();
  }

  persistPreference(preference) {
    if (typeof Storage === 'undefined') {
      return;
    }
    try {
      if (preference) {
        localStorage.setItem(this.STORAGE_KEY, preference);
      } else {
        localStorage.removeItem(this.STORAGE_KEY);
      }
    } catch (error) {
      console.warn('ThemeManager: Unable to persist theme preference.', error);
    }
  }

  dispatchThemeChange(theme, preference) {
    document.dispatchEvent(new CustomEvent('themechange', {
      detail: { theme, preference }
    }));
  }

  toggleTheme() {
    const current = this.getCurrentTheme();
    const next = current === this.THEMES.DARK ? this.THEMES.LIGHT : this.THEMES.DARK;
    this.setTheme(next);
  }

  getCurrentTheme() {
    return document.documentElement.getAttribute(this.THEME_ATTRIBUTE) || this.activeTheme || this.THEMES.LIGHT;
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
      if (this.preference === this.THEMES.SYSTEM) {
        const theme = event.matches ? this.THEMES.DARK : this.THEMES.LIGHT;
        this.activeTheme = theme;
        document.documentElement.setAttribute(this.THEME_ATTRIBUTE, theme);
        this.dispatchThemeChange(theme, this.THEMES.SYSTEM);
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
      if (!event || !event.detail) {
        callback();
        return;
      }
      callback(event.detail.theme, event.detail.preference);
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

