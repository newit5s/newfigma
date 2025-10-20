/**
 * Modern Restaurant Booking Manager - Booking Widget
 * Handles the 3-step booking flow, accessibility, and AJAX integrations.
 */
(function () {
  const ajaxSettings = window.rbBookingAjax || window.rb_booking_ajax || {};

  const DEFAULT_STRINGS = {
    loading: 'Loading...',
    error: 'Something went wrong. Please try again.',
    success: 'Booking confirmed! Check your email.',
    stepInvalid: 'Please complete the required fields before continuing.',
    selectTime: 'Select a time slot to continue.',
    submitLabel: 'Confirm Reservation',
    continueLabel: 'Continue',
    backLabel: 'Back',
    required: 'This field is required.',
    invalidEmail: 'Enter a valid email address.',
    invalidPhone: 'Enter a valid phone number.',
    noSlots: 'No available times for the selected date.',
    available: 'Available',
    unavailable: 'Fully Booked'
  };

  class ModernBookingWidget {
    constructor(options = {}) {
      const localizedStrings = ajaxSettings.strings || {};

      this.options = {
        triggerSelector: options.triggerSelector || '[data-rb-booking-trigger]',
        modalSelector: options.modalSelector || '#rb-booking-modal',
        overlayActiveClass: options.overlayActiveClass || 'rb-active',
        stepSelector: options.stepSelector || '.rb-booking-step',
        progressSelector: options.progressSelector || '.rb-progress-step',
        nextButtonSelector: options.nextButtonSelector || '#rb-next-step',
        prevButtonSelector: options.prevButtonSelector || '#rb-prev-step',
        closeSelector: options.closeSelector || '#rb-close-booking',
        formSelector: options.formSelector || '#rb-booking-form',
        timeSlotSelector: options.timeSlotSelector || '.rb-time-slot',
        alternativeSlotSelector: options.alternativeSlotSelector || '.rb-alt-slot'
      };

      this.ajaxUrl = options.ajaxUrl || ajaxSettings.ajax_url || '';
      this.nonce = options.nonce || ajaxSettings.nonce || '';
      this.strings = { ...DEFAULT_STRINGS, ...localizedStrings };

      this.currentStep = 1;
      this.bookingData = {
        location_id: '',
        location_label: '',
        party_size: '',
        date: '',
        time: '',
        time_label: ''
      };

      document.addEventListener('DOMContentLoaded', () => this.init());
    }

    init() {
      this.modalOverlay = document.querySelector(this.options.modalSelector);
      this.form = document.querySelector(this.options.formSelector);
      this.nextButton = document.querySelector(this.options.nextButtonSelector);
      this.prevButton = document.querySelector(this.options.prevButtonSelector);
      this.closeButton = document.querySelector(this.options.closeSelector);
      this.progressSteps = Array.from(document.querySelectorAll(this.options.progressSelector));
      this.stepPanels = Array.from(document.querySelectorAll(this.options.stepSelector));
      this.triggerButtons = Array.from(document.querySelectorAll(this.options.triggerSelector));
      this.timeSlotsContainer = document.querySelector('#time-slots');
      this.timeSlotError = document.querySelector('#rb-time-slot-error');
      this.alternativeTimes = document.querySelector('#alternative-times');
      this.altSlotsContainer = document.querySelector('#rb-alt-slots');
      this.selectedDateDisplay = document.querySelector('#selected-date-display');
      this.summaryElements = {
        location: document.querySelector('#summary-location'),
        date: document.querySelector('#summary-date'),
        time: document.querySelector('#summary-time'),
        party: document.querySelector('#summary-party')
      };
      this.statusElement = document.querySelector('#rb-footer-status');

      if (!this.modalOverlay || !this.form) {
        return;
      }

      this.bindEvents();
      this.goToStep(1);
      this.loadLocations();
    }

    bindEvents() {
      this.triggerButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          this.openModal(button);
        });
      });

      if (this.closeButton) {
        this.closeButton.addEventListener('click', (event) => {
          event.preventDefault();
          this.closeModal();
        });
      }

      this.modalOverlay.addEventListener('click', (event) => {
        if (event.target === this.modalOverlay) {
          this.closeModal();
        }
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && this.modalOverlay.classList.contains(this.options.overlayActiveClass)) {
          event.preventDefault();
          this.closeModal();
        }
      });

      if (this.nextButton) {
        this.nextButton.addEventListener('click', () => this.handleNextStep());
      }

      if (this.prevButton) {
        this.prevButton.addEventListener('click', () => this.handlePrevStep());
      }

      this.form.addEventListener('submit', (event) => {
        event.preventDefault();
        this.handleSubmit();
      });

      const locationSelect = document.querySelector('#location-select');
      const partySelect = document.querySelector('#party-size');
      const dateInput = document.querySelector('#booking-date');

      if (locationSelect) {
        locationSelect.addEventListener('change', () => {
          this.bookingData.location_id = locationSelect.value;
          this.bookingData.location_label = locationSelect.options[locationSelect.selectedIndex]?.text || '';
          this.updateSummary();
          this.maybeLoadTimeSlots();
        });
      }

      if (partySelect) {
        partySelect.addEventListener('change', () => {
          this.bookingData.party_size = partySelect.value;
          this.updateSummary();
          this.maybeLoadTimeSlots();
        });
      }

      if (dateInput) {
        dateInput.addEventListener('change', () => {
          this.bookingData.date = dateInput.value;
          this.updateSummary();
          this.maybeLoadTimeSlots();
        });
      }

      this.timeSlotsContainer?.addEventListener('click', (event) => {
        const slot = event.target.closest(this.options.timeSlotSelector);
        if (!slot || slot.classList.contains('rb-unavailable')) {
          return;
        }

        this.timeSlotsContainer.querySelectorAll(this.options.timeSlotSelector).forEach((item) => {
          item.classList.remove('rb-selected');
          item.setAttribute('aria-pressed', 'false');
        });

        slot.classList.add('rb-selected');
        slot.setAttribute('aria-pressed', 'true');
        this.bookingData.time = slot.dataset.time || '';
        this.bookingData.time_label = slot.querySelector('.rb-time')?.textContent || '';
        this.updateSummary();
        this.setTimeSlotError('');
      });

      this.altSlotsContainer?.addEventListener('click', (event) => {
        const altSlot = event.target.closest(this.options.alternativeSlotSelector);
        if (!altSlot) {
          return;
        }

        const time = altSlot.dataset.time;
        const label = altSlot.textContent?.trim() || '';
        if (time) {
          this.bookingData.time = time;
          this.bookingData.time_label = label;
          this.updateSummary();
          this.goToStep(3);
        }
      });

      this.form.querySelectorAll('.rb-input, .rb-select, .rb-textarea').forEach((field) => {
        field.addEventListener('blur', () => {
          this.validateField(field);
        });
      });
    }

    openModal(trigger) {
      this.previousFocus = document.activeElement;
      this.modalOverlay.classList.add(this.options.overlayActiveClass);
      this.modalOverlay.removeAttribute('aria-hidden');
      document.body.classList.add('rb-body-locked');

      const focusable = this.modalOverlay.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (focusable.length) {
        focusable[0].focus();
      }

      this.currentStep = 1;
      this.goToStep(1);
      this.triggerSource = trigger || null;
      this.clearStatus();
    }

    closeModal() {
      this.modalOverlay.classList.remove(this.options.overlayActiveClass);
      this.modalOverlay.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('rb-body-locked');
      this.clearStatus();
      if (this.previousFocus && typeof this.previousFocus.focus === 'function') {
        this.previousFocus.focus();
      }
    }

    handleNextStep() {
      if (!this.validateStep(this.currentStep)) {
        this.showStatus('error', this.strings.stepInvalid);
        return;
      }

      if (this.currentStep === this.stepPanels.length) {
        this.handleSubmit();
        return;
      }

      this.goToStep(this.currentStep + 1);
    }

    handlePrevStep() {
      if (this.currentStep === 1) {
        this.closeModal();
        return;
      }

      this.goToStep(this.currentStep - 1);
    }

    goToStep(step) {
      this.currentStep = Math.max(1, Math.min(step, this.stepPanels.length));

      this.stepPanels.forEach((panel, index) => {
        const panelStep = index + 1;
        if (panelStep === this.currentStep) {
          panel.classList.add('rb-active');
          panel.setAttribute('aria-hidden', 'false');
        } else {
          panel.classList.remove('rb-active');
          panel.setAttribute('aria-hidden', 'true');
        }
      });

      this.progressSteps.forEach((progressStep, index) => {
        const progressIndex = index + 1;
        progressStep.classList.toggle('rb-active', progressIndex === this.currentStep);
        progressStep.classList.toggle('rb-completed', progressIndex < this.currentStep);
        progressStep.setAttribute('aria-current', progressIndex === this.currentStep ? 'step' : 'false');
      });

      if (this.prevButton) {
        this.prevButton.style.display = this.currentStep > 1 ? '' : 'none';
        this.prevButton.textContent = this.strings.backLabel;
      }

      if (this.nextButton) {
        const isLastStep = this.currentStep === this.stepPanels.length;
        this.nextButton.textContent = isLastStep ? this.strings.submitLabel : this.strings.continueLabel;
        this.nextButton.setAttribute('type', isLastStep ? 'submit' : 'button');
      }

      this.clearStatus();
    }

    setTimeSlotError(message) {
      if (!this.timeSlotError) return;
      this.timeSlotError.textContent = message;
      this.timeSlotError.style.display = message ? 'block' : 'none';
    }

    maybeLoadTimeSlots() {
      if (!this.bookingData.location_id || !this.bookingData.date || !this.bookingData.party_size) {
        return;
      }
      this.bookingData.time = '';
      this.bookingData.time_label = '';
      this.updateSummary();
      this.loadTimeSlots(this.bookingData.location_id, this.bookingData.date, this.bookingData.party_size);
    }

    async loadLocations() {
      const select = document.querySelector('#location-select');
      if (!select) return;

      const fallbackLocations = this.getPresetLocations(select);

      if (!this.ajaxUrl) {
        if (fallbackLocations && fallbackLocations.length) {
          this.populateLocations(select, fallbackLocations);
          this.applyDefaultLocation(select);
        }
        this.updateSummary();
        return;
      }

      this.setSelectLoading(select, true);
      try {
        const response = await this.postAjax('rb_get_locations', {});
        const data = await response.json();
        const locations = Array.isArray(data?.locations) ? data.locations : fallbackLocations;

        if (locations && locations.length) {
          this.populateLocations(select, locations, data?.placeholder);
          if (data?.default_location) {
            select.value = data.default_location;
          }
          this.applyDefaultLocation(select);
        }
      } catch (error) {
        console.error('ModernBookingWidget: unable to load locations', error);
        if (fallbackLocations && fallbackLocations.length) {
          this.populateLocations(select, fallbackLocations);
          this.applyDefaultLocation(select);
        } else {
          this.showStatus('error', this.strings.error);
        }
      } finally {
        this.setSelectLoading(select, false);
        this.updateSummary();
      }
    }

    async loadTimeSlots(locationId, date, partySize) {
      if (!this.timeSlotsContainer) return;

      this.renderLoadingState();
      try {
        const response = await this.postAjax('rb_check_availability', {
          location_id: locationId,
          date,
          party_size: partySize
        });
        const data = await response.json();
        this.renderTimeSlots(Array.isArray(data?.available_slots) ? data.available_slots : []);
        this.renderAlternativeSlots(Array.isArray(data?.alternative_slots) ? data.alternative_slots : []);
      } catch (error) {
        console.error('ModernBookingWidget: unable to load time slots', error);
        this.showStatus('error', this.strings.error);
        this.renderTimeSlots([]);
      }
    }

    renderLoadingState() {
      if (!this.timeSlotsContainer) return;
      this.timeSlotsContainer.innerHTML = '';
      const wrapper = document.createElement('div');
      wrapper.className = 'rb-time-slots-loading';
      wrapper.innerHTML = '<div class="rb-loading-spinner" role="status" aria-live="polite"></div>';
      this.timeSlotsContainer.appendChild(wrapper);
    }

    populateLocations(select, locations, placeholder) {
      const defaultPlaceholder = placeholder || select.dataset.placeholder || 'Choose location';
      select.innerHTML = `<option value="">${defaultPlaceholder}</option>`;
      locations.forEach((location) => {
        const option = document.createElement('option');
        option.value = location.id || location.value || '';
        option.textContent = location.name || location.label || '';
        select.appendChild(option);
      });
    }

    applyDefaultLocation(select) {
      if (!select) return;
      if (select.value) {
        this.bookingData.location_id = select.value;
        this.bookingData.location_label = select.options[select.selectedIndex]?.text || '';
      } else if (select.dataset.preset) {
        select.value = select.dataset.preset;
        this.bookingData.location_id = select.dataset.preset;
        this.bookingData.location_label = select.options[select.selectedIndex]?.text || '';
      }
      this.maybeLoadTimeSlots();
    }

    getPresetLocations(select) {
      const datasetValue = select?.dataset.locations;
      if (!datasetValue) {
        return null;
      }
      try {
        const parsed = JSON.parse(datasetValue);
        return Array.isArray(parsed) ? parsed : null;
      } catch (error) {
        console.warn('ModernBookingWidget: unable to parse preset locations', error);
        return null;
      }
    }

    renderTimeSlots(slots) {
      if (!this.timeSlotsContainer) return;
      this.timeSlotsContainer.innerHTML = '';
      this.setTimeSlotError('');

      if (!slots.length) {
        const empty = document.createElement('p');
        empty.className = 'rb-inline-error';
        empty.textContent = this.strings.noSlots;
        this.timeSlotsContainer.appendChild(empty);
        return;
      }

      slots.forEach((slot) => {
        const isAvailable = slot.available !== undefined ? Boolean(slot.available) : !/(unavailable|full|booked)/i.test(String(slot.status || ''));
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `rb-time-slot${isAvailable ? '' : ' rb-unavailable'}`;
        button.dataset.time = slot.value || slot.time || '';
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-disabled', String(!isAvailable));
        button.disabled = !isAvailable;
        const timeLabel = slot.label || slot.time_label || slot.display || '';
        const availabilityLabel = slot.status || (isAvailable ? this.strings.available : this.strings.unavailable);
        button.innerHTML = `
          <span class="rb-time">${timeLabel}</span>
          <span class="rb-availability">${availabilityLabel}</span>
        `;
        this.timeSlotsContainer.appendChild(button);
      });
    }

    renderAlternativeSlots(slots) {
      if (!this.alternativeTimes || !this.altSlotsContainer) return;
      this.altSlotsContainer.innerHTML = '';

      if (!slots.length) {
        this.alternativeTimes.style.display = 'none';
        return;
      }

      this.alternativeTimes.style.display = 'block';
      slots.forEach((slot) => {
        const alt = document.createElement('button');
        alt.type = 'button';
        alt.className = 'rb-alt-slot';
        alt.dataset.time = slot.value || slot.time || '';
        alt.textContent = slot.label || slot.display || '';
        this.altSlotsContainer.appendChild(alt);
      });
    }

    validateStep(step) {
      switch (step) {
        case 1:
          return this.validateStepOne();
        case 2:
          return this.validateStepTwo();
        case 3:
          return this.validateStepThree();
        default:
          return true;
      }
    }

    validateStepOne() {
      const locationSelect = document.querySelector('#location-select');
      const partySelect = document.querySelector('#party-size');
      const dateInput = document.querySelector('#booking-date');

      const validLocation = this.validateField(locationSelect);
      const validParty = this.validateField(partySelect);
      const validDate = this.validateField(dateInput);

      return validLocation && validParty && validDate;
    }

    validateStepTwo() {
      if (!this.bookingData.time) {
        this.setTimeSlotError(this.strings.selectTime);
        return false;
      }
      return true;
    }

    validateStepThree() {
      const requiredFields = this.form.querySelectorAll('[data-rb-required]');
      let isValid = true;
      requiredFields.forEach((field) => {
        if (!this.validateField(field)) {
          isValid = false;
        }
      });
      return isValid;
    }

    validateField(field) {
      if (!field) return true;
      const value = field.value?.trim();
      let message = '';

      if (field.hasAttribute('data-rb-required') && !value) {
        message = this.strings.required;
      } else if (field.type === 'email' && value && !this.isValidEmail(value)) {
        message = this.strings.invalidEmail;
      } else if (field.type === 'tel' && value && !this.isValidPhone(value)) {
        message = this.strings.invalidPhone;
      }

      this.setFieldError(field, message);
      return message === '';
    }

    setFieldError(field, message) {
      const group = field.closest('.rb-form-group');
      const errorElement = group?.querySelector('.rb-error-message');
      if (errorElement) {
        errorElement.textContent = message;
      }
      field.setAttribute('aria-invalid', message ? 'true' : 'false');
    }

    isValidEmail(value) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    isValidPhone(value) {
      return /^[+\d\s().-]{6,}$/.test(value);
    }

    updateSummary() {
      if (this.summaryElements.location) {
        this.summaryElements.location.textContent = this.bookingData.location_label || '—';
      }
      if (this.summaryElements.date) {
        const formatted = this.formatDate(this.bookingData.date);
        this.summaryElements.date.textContent = formatted;
        if (this.selectedDateDisplay) {
          this.selectedDateDisplay.textContent = formatted;
        }
      }
      if (this.summaryElements.time) {
        this.summaryElements.time.textContent = this.bookingData.time_label || '—';
      }
      if (this.summaryElements.party) {
        this.summaryElements.party.textContent = this.bookingData.party_size || '—';
      }
    }

    formatDate(value) {
      if (!value) return '—';
      try {
        const date = new Date(value);
        return date.toLocaleDateString(undefined, {
          weekday: 'short',
          month: 'short',
          day: 'numeric',
          year: 'numeric'
        });
      } catch (error) {
        return value;
      }
    }

    showStatus(type, message) {
      if (!this.statusElement) return;
      this.statusElement.textContent = message;
      this.statusElement.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
      this.statusElement.classList.toggle('rb-success-message', type === 'success');
    }

    clearStatus() {
      if (!this.statusElement) return;
      this.statusElement.textContent = '';
      this.statusElement.removeAttribute('aria-live');
      this.statusElement.classList.remove('rb-success-message');
    }

    setLoadingState(isLoading) {
      if (!this.nextButton) return;
      this.nextButton.classList.toggle('rb-loading', isLoading);
      this.nextButton.disabled = isLoading;
    }

    async handleSubmit() {
      if (!this.validateStepThree()) {
        this.showStatus('error', this.strings.stepInvalid);
        return;
      }

      const formData = new FormData(this.form);
      const payload = Object.fromEntries(formData.entries());
      const bookingPayload = {
        ...payload,
        location_id: this.bookingData.location_id,
        party_size: this.bookingData.party_size,
        date: this.bookingData.date,
        time: this.bookingData.time
      };

      this.setLoadingState(true);
      this.showStatus('info', this.strings.loading);

      try {
        const response = await this.postAjax('rb_create_booking', bookingPayload);
        const data = await response.json();

        if (data?.success) {
          this.showStatus('success', data.message || this.strings.success);
          this.form.reset();
          this.bookingData = {
            location_id: '',
            location_label: '',
            party_size: '',
            date: '',
            time: '',
            time_label: ''
          };
          this.updateSummary();
          setTimeout(() => this.closeModal(), 1200);
        } else {
          this.showStatus('error', data?.message || this.strings.error);
        }
      } catch (error) {
        console.error('ModernBookingWidget: unable to submit booking', error);
        this.showStatus('error', this.strings.error);
      } finally {
        this.setLoadingState(false);
      }
    }

    setSelectLoading(select, isLoading) {
      if (!select) return;
      select.disabled = isLoading;
      if (isLoading) {
        select.dataset.originalText = select.options[0]?.textContent || '';
        select.innerHTML = `<option value="">${this.strings.loading}</option>`;
      } else if (!select.options.length && select.dataset.originalText !== undefined) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = select.dataset.originalText || '';
        select.appendChild(option);
      }
    }

    async postAjax(action, params) {
      if (!this.ajaxUrl) {
        throw new Error('AJAX URL missing.');
      }

      const body = new URLSearchParams({
        action,
        nonce: this.nonce,
        ...params
      });

      return fetch(this.ajaxUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body
      });
    }
  }

  window.ModernBookingWidget = ModernBookingWidget;
  new ModernBookingWidget();
})();
