/* student_dashboard.js — AJAX booking + cancel for Student Dashboard */
(function () {
  'use strict';

  let bookedIntervals = [];
  let availabilityWired = false;

  const FIXED_SLOTS = [
    { start: '07:30', end: '09:00', label: '7:30 AM – 9:00 AM' },
    { start: '09:00', end: '10:30', label: '9:00 AM – 10:30 AM' },
    { start: '10:30', end: '12:00', label: '10:30 AM – 12:00 PM' },
    { start: '12:00', end: '13:30', label: '12:00 PM – 1:30 PM' },
    { start: '13:30', end: '15:00', label: '1:30 PM – 3:00 PM' },
    { start: '15:00', end: '16:30', label: '3:00 PM – 4:30 PM' },
    { start: '16:30', end: '18:00', label: '4:30 PM – 6:00 PM' },
  ];

  function isFlexibleFacilityName(name) {
    const n = String(name || '').toLowerCase();
    return n.includes('faculty') || n.includes('reading area') || n.includes('eirc') || n.includes('irc') || n.includes('museum');
  }

  function isFlexMode() {
    const g = document.getElementById('flexTimeGroup');
    return !!(g && !g.classList.contains('d-none'));
  }

  function setHiddenTimes(start, end) {
    const s = document.getElementById('startTime');
    const e = document.getElementById('endTime');
    if (s) s.value = start || '';
    if (e) e.value = end || '';
  }

  function hasBootstrapModal() {
    return !!(window.bootstrap && bootstrap.Modal);
  }

  function showModalById(id) {
    const el = document.getElementById(id);
    if (!el) return;

    if (hasBootstrapModal()) {
      try {
        bootstrap.Modal.getOrCreateInstance(el).show();
        return;
      } catch (e) {
        // fall through to fallback
      }
    }

    fallbackShowModal(el);
  }

  function hideModalById(id) {
    const el = document.getElementById(id);
    if (!el) return;

    if (hasBootstrapModal()) {
      try {
        const inst = bootstrap.Modal.getInstance(el) || bootstrap.Modal.getOrCreateInstance(el);
        if (inst) inst.hide();
        return;
      } catch (e) {
        // fall through to fallback
      }
    }

    fallbackHideModal(el);
  }

  function fallbackWireModal(el) {
    if (el.dataset.fallbackWired === '1') return;
    el.dataset.fallbackWired = '1';

    // Close when clicking the modal backdrop area
    el.addEventListener('click', function (e) {
      if (e.target === el) fallbackHideModal(el);
    });

    // Close buttons
    el.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        fallbackHideModal(el);
      });
    });
  }

  function fallbackShowModal(el) {
    fallbackWireModal(el);

    // Backdrop
    if (!el.__fallbackBackdrop) {
      const bd = document.createElement('div');
      bd.className = 'modal-backdrop fade show';
      bd.addEventListener('click', function () { fallbackHideModal(el); });
      document.body.appendChild(bd);
      el.__fallbackBackdrop = bd;
    }

    el.style.display = 'block';
    el.classList.add('show');
    el.setAttribute('aria-modal', 'true');
    el.removeAttribute('aria-hidden');
    document.body.classList.add('modal-open');
  }

  function fallbackHideModal(el) {
    el.classList.remove('show');
    el.style.display = 'none';
    el.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');

    if (el.__fallbackBackdrop) {
      el.__fallbackBackdrop.remove();
      el.__fallbackBackdrop = null;
    }
  }

  function fillBookingModalFromButton(btn) {
    if (!btn) return;

    const facilityId   = btn.dataset.facilityId;
    const facilityName = btn.dataset.facilityName;
    const capacity     = parseInt(btn.dataset.capacity, 10);
    const isInstant    = btn.dataset.instant === '1';
    const needsLetter  = btn.dataset.requiresLetter === '1';

    // Fill hidden + labels
    document.getElementById('modalFacilityId').value = facilityId;
    document.getElementById('bookingModalLabel').textContent =
      (isInstant ? '⚡ Book — ' : '📋 Request — ') + facilityName;
    document.getElementById('modalSubtitle').textContent =
      isInstant ? 'Instant booking · confirmed immediately' : 'Admin approval required';

    // Save facility name on modal element for later validation in submit handler
    const bModal = document.getElementById('bookingModal');
    if (bModal) {
      bModal.dataset.facilityName = facilityName;
      // Also mark EIRC/Museum explicitly if button provided the flag
      try {
        if (btn && btn.dataset && btn.dataset.isEirc) bModal.dataset.isEirc = btn.dataset.isEirc;
        else bModal.dataset.isEirc = (String(facilityName || '').toLowerCase().includes('eirc') || String(facilityName || '').toLowerCase().includes('museum')) ? '1' : '0';
      } catch (e) { bModal.dataset.isEirc = '0'; }
    }

    const flex = isFlexibleFacilityName(facilityName);

    // Toggle time UI
    const slotOnlyGroup = document.getElementById('slotOnlyGroup');
    const flexGroup = document.getElementById('flexTimeGroup');
    if (slotOnlyGroup && slotOnlyGroup.classList) slotOnlyGroup.classList.toggle('d-none', flex);
    if (flexGroup && flexGroup.classList) flexGroup.classList.toggle('d-none', !flex);

    // Rebuild flexible start/end options depending on facility (remove 7:00/7:30 for EIRC/Museum)
    // Prefer explicit flag on the button (data-is-eirc) — fall back to modal facility name
    const modalEl = document.getElementById('bookingModal');
    const isEircAttr = btn && btn.dataset && (btn.dataset.isEirc === '1');
    const fnameLower = modalEl && modalEl.dataset && modalEl.dataset.facilityName ? modalEl.dataset.facilityName.toLowerCase() : '';
    const modalIsEircAttr = modalEl && modalEl.dataset && (modalEl.dataset.isEirc === '1');
    const isEirc = isEircAttr || modalIsEircAttr || fnameLower.includes('eirc') || fnameLower.includes('irc') || fnameLower.includes('museum');
    function makeOption(val, text) { const o = document.createElement('option'); o.value = val; o.textContent = text; return o; }
    if (flex) {
      const fs = document.getElementById('flexStart');
      const fe = document.getElementById('flexEnd');
      if (fs && fe) {
        fs.innerHTML = '';
        fe.innerHTML = '';
        // Start times: hourly
        const startHourFrom = isEirc ? 8 : 7;
        const startHourTo   = 17; // 5:00 PM
        for (let h = startHourFrom; h <= startHourTo; h++) {
          const val = (h < 10 ? '0' : '') + h + ':00';
          const disp = ((h % 12) === 0 ? 12 : (h % 12)) + ':00 ' + (h < 12 ? 'AM' : 'PM');
          fs.appendChild(makeOption(val, disp));
        }
        // End times: hourly with :30, plus 18:00 as final option
        const endHourFrom = isEirc ? 8 : 7;
        const endHourTo = 17; // 5:30 last hourly slot; we'll add 18:00 separately
        for (let h = endHourFrom; h <= endHourTo; h++) {
          const val = (h < 10 ? '0' : '') + h + ':30';
          const disp = ((h % 12) === 0 ? 12 : (h % 12)) + ':30 ' + (h < 12 ? 'AM' : 'PM');
          fe.appendChild(makeOption(val, disp));
        }
        fe.appendChild(makeOption('18:00', '6:00 PM'));
      }
    }

    // Toggle required/disabled so HTML5 validation matches the visible UI
    const slotSelEl = document.getElementById('slotSelect');
    const fsEl = document.getElementById('flexStart');
    const feEl = document.getElementById('flexEnd');

    if (slotSelEl) {
      slotSelEl.required = !flex;
      slotSelEl.disabled = flex;
    }
    if (fsEl) {
      fsEl.required = flex;
      fsEl.disabled = !flex;
    }
    if (feEl) {
      feEl.required = flex;
      feEl.disabled = !flex;
    }

    // Info chips
    const infoIsEirc = isEirc;
    const timeText = flex
      ? (isEirc ? '8:00 AM – 6:00 PM' : '7:00 AM – 12:00 PM / 1:00 PM – 5:00 PM')
      : '7:30 AM – 6:00 PM';
    document.getElementById('modalInfoRow').innerHTML =
      '<span class="booking-info-chip"><i class="fas fa-users"></i>Max ' + capacity + ' people</span>' +
      '<span class="booking-info-chip"><i class="fas fa-clock"></i>' + timeText + '</span>';

    // Initialize time values
    if (!flex) {
      const slotSel = document.getElementById('slotSelect');
      if (slotSel && !slotSel.value) slotSel.value = '07:30|09:00';
      if (slotSel && slotSel.value) {
        const parts = slotSel.value.split('|');
        setHiddenTimes(parts[0] || '', parts[1] || '');
      }
    } else {
      const fs = document.getElementById('flexStart');
      const fe = document.getElementById('flexEnd');
      if (fs && fe) {
        if (fe.selectedIndex <= fs.selectedIndex) fe.selectedIndex = Math.min(fs.selectedIndex + 1, fe.options.length - 1);
        setHiddenTimes(fs.value || '', fe.value || '');
      }
    }

    // Capacity
    document.getElementById('attendeesCount').max = capacity;
    document.getElementById('attendeesCount').value = 1;
    document.getElementById('capacityNote').textContent = '(max ' + capacity + ')';

    // Letter upload
    const letterGroup = document.getElementById('letterUploadGroup');
    const letterInput = document.getElementById('requestLetter');
    if (needsLetter) {
      letterGroup.style.display = '';
      letterInput.required = true;
    } else {
      letterGroup.style.display = 'none';
      letterInput.required = false;
      letterInput.value = '';
    }

    // Notices
    document.getElementById('instantNotice').classList.toggle('d-none', !isInstant);
    document.getElementById('requestNotice').classList.toggle('d-none',  isInstant);

    // Style submit button
    const submitBtn = document.getElementById('submitBooking');
    submitBtn.className = isInstant
      ? 'btn btn-success'
      : 'btn btn-warning';
    document.getElementById('submitText').innerHTML = isInstant
      ? '<i class="fas fa-bolt me-1"></i>Confirm Booking'
      : '<i class="fas fa-paper-plane me-1"></i>Submit Request';

    // Clear previous state
    clearModal();

    wireAvailabilityOnce();
    refreshAvailability();
  }

  // ── Fill booking modal on open ─────────────────────────────────────────────
  const bookingModal = document.getElementById('bookingModal');
  if (bookingModal) {
    // Normal path: Bootstrap modal event
    bookingModal.addEventListener('show.bs.modal', function (event) {
      fillBookingModalFromButton(event.relatedTarget);
    });

    // Always wire click handlers so booking still works even if Bootstrap JS is present but broken.
    document.querySelectorAll('.btn-book').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        fillBookingModalFromButton(btn);
        showModalById('bookingModal');
      });
    });
  }

  function wireAvailabilityOnce() {
    if (availabilityWired) return;
    availabilityWired = true;

    const dateEl = document.getElementById('bookingDate');
    const slotEl = document.getElementById('slotSelect');
    const fs     = document.getElementById('flexStart');
    const fe     = document.getElementById('flexEnd');

    if (dateEl) dateEl.addEventListener('change', refreshAvailability);

    if (slotEl) {
      slotEl.addEventListener('change', function () {
        const parts = String(slotEl.value || '').split('|');
        setHiddenTimes(parts[0] || '', parts[1] || '');
        applyDisabledTimes();
      });
    }

    if (fs) fs.addEventListener('change', function () {
      if (fe && fe.selectedIndex <= fs.selectedIndex) fe.selectedIndex = Math.min(fs.selectedIndex + 1, fe.options.length - 1);
      setHiddenTimes(fs.value || '', (fe && fe.value) || '');
      applyDisabledTimes();
    });
    if (fe) fe.addEventListener('change', function () {
      setHiddenTimes((fs && fs.value) || '', fe.value || '');
      applyDisabledTimes();
    });
  }

  function refreshAvailability() {
    const facilityId = (document.getElementById('modalFacilityId') || {}).value;
    const dateStr    = (document.getElementById('bookingDate') || {}).value;

    if (!facilityId || !dateStr) {
      bookedIntervals = [];
      applyDisabledTimes();
      return;
    }

    const fd = new FormData();
    fd.append('action', 'availability');
    fd.append('facility_id', facilityId);
    fd.append('booking_date', dateStr);

    fetch(BASE_URL + '/student/ajax_book.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        bookedIntervals = (data && data.success && Array.isArray(data.booked)) ? data.booked : [];
        applyDisabledTimes();
      })
      .catch(function () {
        bookedIntervals = [];
        applyDisabledTimes();
      });
  }

  function applyDisabledTimes() {
    // Flexible time mode (Faculty / Reading Area or EIRC/Museum)
    if (isFlexMode()) {
      const fs = document.getElementById('flexStart');
      const fe = document.getElementById('flexEnd');
      if (!fs || !fe) return;

      const modalEl = document.getElementById('bookingModal');
      const modalIsEircAttr = modalEl && modalEl.dataset && (modalEl.dataset.isEirc === '1');
      const fnameLower = modalEl && modalEl.dataset && modalEl.dataset.facilityName ? modalEl.dataset.facilityName.toLowerCase() : '';
      const isEirc = modalIsEircAttr || fnameLower.includes('eirc') || fnameLower.includes('irc') || fnameLower.includes('museum');

      // Disable START times that fall inside an existing booking (and enforce min/max for EIRC/Museum)
      Array.from(fs.options).forEach(function (opt) {
        const s = toMin(opt.value);
        if (isEirc) {
          opt.disabled = (s < 8 * 60) || (s >= 18 * 60) || insideAny(s, bookedIntervals);
        } else {
          opt.disabled = insideAny(s, bookedIntervals);
        }
      });
      if (fs.selectedOptions[0] && fs.selectedOptions[0].disabled) pickFirstEnabled(fs);

      // Ensure END is after START
      const sNowMin = toMin(fs.value);
      if (toMin(fe.value) <= sNowMin) pickFirstEnabledAfter(fe, sNowMin);

      // Disable END times that are invalid for the current time window or would overlap
      Array.from(fe.options).forEach(function (opt) {
        const e = toMin(opt.value);
        if (isEirc) {
          opt.disabled = (e <= sNowMin) || (e > 18 * 60) || overlapsAny(sNowMin, e, bookedIntervals);
        } else {
          const inMorning = sNowMin < 12 * 60;
          const inSameWindow = inMorning ? (e <= 12 * 60) : (e >= 13 * 60 && e <= 17 * 60);
          opt.disabled = !inSameWindow || (e <= sNowMin) || overlapsAny(sNowMin, e, bookedIntervals);
        }
      });

      if (fe.selectedOptions[0] && fe.selectedOptions[0].disabled) {
        const nextOk = Array.from(fe.options).find(function (o) {
          const e = toMin(o.value);
          return !o.disabled && e > sNowMin;
        });
        if (nextOk) fe.value = nextOk.value;
      }

      setHiddenTimes(fs.value || '', fe.value || '');

      const eNowMin = toMin(fe.value);
      if (sNowMin < eNowMin && overlapsAny(sNowMin, eNowMin, bookedIntervals)) {
        showModalAlert('warning', 'That time is already booked. Please choose another time (unavailable times are disabled).');
      }
      return;
    }

    // Slot mode (default)
    const slotEl = document.getElementById('slotSelect');
    if (!slotEl) return;

    Array.from(slotEl.options).forEach(function (opt, idx) {
      // Skip placeholder
      if (idx === 0 && !opt.value) return;

      if (!opt.dataset.label) opt.dataset.label = opt.textContent;
      const parts = String(opt.value || '').split('|');
      const s = toMin(parts[0]);
      const e = toMin(parts[1]);
      const disabled = overlapsAny(s, e, bookedIntervals);
      opt.disabled = disabled;
      opt.textContent = disabled ? (opt.dataset.label + ' — Booked') : opt.dataset.label;
    });

    // Auto-fix if selected slot is disabled
    if (slotEl.selectedOptions[0] && slotEl.selectedOptions[0].disabled) {
      const first = Array.from(slotEl.options).findIndex(function (o, i) {
        if (i === 0 && !o.value) return false;
        return !o.disabled;
      });
      if (first >= 0) slotEl.selectedIndex = first;
    }

    const parts = String(slotEl.value || '').split('|');
    setHiddenTimes(parts[0] || '', parts[1] || '');

    const sNowMin = toMin(parts[0]);
    const eNowMin = toMin(parts[1]);
    if (sNowMin < eNowMin && overlapsAny(sNowMin, eNowMin, bookedIntervals)) {
      showModalAlert('warning', 'That slot is already booked. Please choose another slot (unavailable slots are disabled).');
    }
  }

  function pickFirstEnabled(sel) {
    const opt = Array.from(sel.options).find(function (o) { return !o.disabled; });
    if (opt) sel.value = opt.value;
  }

  function pickFirstEnabledAfter(sel, minMinutes) {
    const opt = Array.from(sel.options).find(function (o) {
      return !o.disabled && toMin(o.value) > minMinutes;
    });
    if (opt) sel.value = opt.value;
  }

  function overlapsAny(sMin, eMin, intervals) {
    for (let i = 0; i < intervals.length; i++) {
      const b = intervals[i];
      const bs = toMin(b.start);
      const be = toMin(b.end);
      if (!(eMin <= bs || sMin >= be)) return true;
    }
    return false;
  }

  function insideAny(tMin, intervals) {
    for (let i = 0; i < intervals.length; i++) {
      const b = intervals[i];
      const bs = toMin(b.start);
      const be = toMin(b.end);
      if (tMin >= bs && tMin < be) return true;
    }
    return false;
  }

  function toMin(hm) {
    const parts = String(hm || '').split(':');
    const h = parseInt(parts[0] || '0', 10);
    const m = parseInt(parts[1] || '0', 10);
    return h * 60 + m;
  }

  // ── Submit booking via AJAX ────────────────────────────────────────────────
  const submitBtn = document.getElementById('submitBooking');
  if (submitBtn) {
    submitBtn.addEventListener('click', function () {
      const form = document.getElementById('bookingForm');

      // Basic HTML5 validation
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      // Student booking rule: Thu/Fri not open (4-day program)
      const dateStr = document.getElementById('bookingDate').value;
      if (dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        const dow = isNaN(d.getTime()) ? null : d.getDay();
        if (dow === 4 || dow === 5) {
          showModalAlert('danger', 'Thursday and Friday are not open for student bookings. Please choose another date.');
          return;
        }
      }

      // Determine and validate start/end
      let start = '';
      let end = '';

      if (isFlexMode()) {
        const fs = document.getElementById('flexStart');
        const fe = document.getElementById('flexEnd');
        start = (fs && fs.value) || '';
        end   = (fe && fe.value) || '';

        const sMin = toMin(start);
        const eMin = toMin(end);
        const modalEl = document.getElementById('bookingModal');
        const fnameLower = modalEl && modalEl.dataset && modalEl.dataset.facilityName ? modalEl.dataset.facilityName.toLowerCase() : '';
        const isEirc = fnameLower.includes('eirc') || fnameLower.includes('irc') || fnameLower.includes('museum');

        if (isEirc) {
          if (!(sMin >= 8*60 && eMin <= 18*60 && sMin < eMin)) {
            showModalAlert('danger', 'Allowed time is 8:00 AM–6:00 PM for this facility. Please choose start/end within the allowed range.');
            return;
          }
        } else {
          const morningOk = (sMin >= 7*60)  && (eMin <= 12*60);
          const aftOk     = (sMin >= 13*60) && (eMin <= 17*60);

          if (!(morningOk || aftOk) || !(sMin < eMin)) {
            showModalAlert('danger', 'Allowed time is 7:00 AM–12:00 PM or 1:00 PM–5:00 PM. Please choose start/end within the same time zone.');
            return;
          }
        }

        if (overlapsAny(sMin, eMin, bookedIntervals)) {
          showModalAlert('danger', 'That time is already booked. Please choose another time.');
          return;
        }
      } else {
        const slotSel = document.getElementById('slotSelect');
        const v = String((slotSel && slotSel.value) || '');
        if (!v) {
          showModalAlert('danger', 'Please select a time slot.');
          return;
        }
        const parts = v.split('|');
        start = parts[0] || '';
        end   = parts[1] || '';

        const ok = FIXED_SLOTS.some(sl => sl.start === start && sl.end === end);
        if (!ok) {
          showModalAlert('danger', 'Please select a valid time slot (7:30 AM – 6:00 PM).');
          return;
        }
        if (start >= end) {
          showModalAlert('danger', 'End time must be after start time.');
          return;
        }

        if (overlapsAny(toMin(start), toMin(end), bookedIntervals)) {
          showModalAlert('danger', 'That slot is already booked. Please choose another slot.');
          return;
        }
      }

      setHiddenTimes(start, end);

      // Show spinner
      setLoading(true);
      clearModal();

      const formData = new FormData(form);

      fetch(BASE_URL + '/student/ajax_book.php', {
        method: 'POST',
        body: formData,
      })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        setLoading(false);
        if (data.success) {
          // Close modal after short delay
          showModalAlert('success', data.message);
          setTimeout(function () {
            hideModalById('bookingModal');
            // Reload page to update table & stats
            window.location.href = BASE_URL + '/student/dashboard.php#my-requests';
          }, 1400);
        } else {
          showModalAlert('danger', data.message || 'Something went wrong.');
          refreshAvailability();
        }
      })
      .catch(function () {
        setLoading(false);
        showModalAlert('danger', 'Network error. Please try again.');
      });
    });
  }


  // ── Cancel booking ─────────────────────────────────────────────────────────
  let cancelBookingId = null;

  document.querySelectorAll('.btn-cancel').forEach(function (btn) {
    btn.addEventListener('click', function () {
      cancelBookingId = btn.dataset.id;
      document.getElementById('cancelFacilityName').textContent = btn.dataset.name;
      showModalById('cancelModal');
    });
  });

  const confirmCancelBtn = document.getElementById('confirmCancel');
  if (confirmCancelBtn) {
    confirmCancelBtn.addEventListener('click', function () {
      if (!cancelBookingId) return;
      confirmCancelBtn.disabled = true;
      confirmCancelBtn.textContent = 'Cancelling…';

      const fd = new FormData();
      fd.append('action', 'cancel');
      fd.append('booking_id', cancelBookingId);

      fetch(BASE_URL + '/student/ajax_book.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.success) {
            window.location.reload();
          } else {
            alert(data.message || 'Could not cancel.');
            confirmCancelBtn.disabled = false;
            confirmCancelBtn.textContent = 'Yes, Cancel';
          }
        })
        .catch(function () {
          alert('Network error.');
          confirmCancelBtn.disabled = false;
          confirmCancelBtn.textContent = 'Yes, Cancel';
        });
    });
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  function showModalAlert(type, msg) {
    const el = document.getElementById('modalAlert');
    el.className = 'alert alert-' + type + ' py-2 small';
    el.textContent = msg;
  }

  function clearModal() {
    const el = document.getElementById('modalAlert');
    el.className = 'd-none';
    el.textContent = '';
  }

  function setLoading(loading) {
    document.getElementById('submitText').classList.toggle('d-none', loading);
    document.getElementById('submitSpinner').classList.toggle('d-none', !loading);
    document.getElementById('submitBooking').disabled = loading;
  }

})();
