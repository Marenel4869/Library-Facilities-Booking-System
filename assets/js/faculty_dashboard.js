/* faculty_dashboard.js */
(function () {
  'use strict';

  // Debug flags: helps diagnose cases where buttons are clickable but booking JS doesn't run.
  window.__FAC_JS_LOADED = true;
  window.__FAC_OPEN_WIRED = false;
  window.__FAC_CAPTURE_SEEN = false;
  window.__FAC_HANDLER_RAN = false;

  document.addEventListener('click', function (e) {
    const btn = e.target && e.target.closest ? e.target.closest('.btn-open-modal') : null;
    if (btn) window.__FAC_CAPTURE_SEEN = true;
  }, true);

  let currentFac = null;
  let bookedIntervals = [];
  let availabilityWired = false;
  let syncingSlots = false; // legacy (kept to avoid breaking older references)

  function isFlexibleFacility(fac) {
    const n = String((fac && fac.name) || '').toLowerCase();
    return n.includes('faculty') || n.includes('reading area') || n.includes('eirc') || n.includes('irc') || n.includes('museum');
  }

  function requiresLevelFacility(fac) {
    const n = String((fac && fac.name) || '').toLowerCase();
    return n.includes('faculty area') || n.includes('reading area');
  }

  function applyLevelUiForFacility(fac) {
    const programCol = document.getElementById('programCol');
    const levelCol = document.getElementById('levelCol');
    const levelSel = document.getElementById('levelSelect');

    if (!programCol || !levelCol || !levelSel) return;

    const needsLevel = requiresLevelFacility(fac);
    levelCol.style.display = needsLevel ? '' : 'none';
    levelSel.required = !!needsLevel;
    if (!needsLevel) levelSel.value = '';

    // Make Program full width unless Level is shown
    programCol.classList.toggle('col-md-12', !needsLevel);
    programCol.classList.toggle('col-md-6', needsLevel);
  }

  // System-wide fixed slots (7:30 AM – 6:00 PM)
  const DEFAULT_SLOTS = [
    { start: '07:30', end: '09:00', label: '7:30 AM – 9:00 AM' },
    { start: '09:00', end: '10:30', label: '9:00 AM – 10:30 AM' },
    { start: '10:30', end: '12:00', label: '10:30 AM – 12:00 PM' },
    { start: '12:00', end: '13:30', label: '12:00 PM – 1:30 PM' },
    { start: '13:30', end: '15:00', label: '1:30 PM – 3:00 PM' },
    { start: '15:00', end: '16:30', label: '3:00 PM – 4:30 PM' },
    { start: '16:30', end: '18:00', label: '4:30 PM – 6:00 PM' },
  ];

  function hasBootstrapModal() {
    return !!(window.bootstrap && bootstrap.Modal);
  }

  function showModalById(id) {
    const el = document.getElementById(id);
    if (!el) return;

    // Prefer Bootstrap if available, but never let a Bootstrap error break booking.
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

    el.addEventListener('click', function (e) {
      if (e.target === el) fallbackHideModal(el);
    });

    el.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        fallbackHideModal(el);
      });
    });
  }

  function fallbackShowModal(el) {
    fallbackWireModal(el);

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

  function parseFacilityData(btn) {
    const raw = (btn && (btn.dataset.fac || btn.getAttribute('data-fac'))) || '';

    try { return JSON.parse(raw); } catch (e) {}

    // If HTML entities were not decoded for some reason, try decoding the common ones.
    const decoded = String(raw)
      .replace(/&quot;/g, '"')
      .replace(/&#0*39;/g, "'")
      .replace(/&amp;/g, '&');

    try { return JSON.parse(decoded); } catch (e) {
      return null;
    }
  }

  function wireOpenButtons() {
    const btns = document.querySelectorAll('.btn-open-modal');
    window.__FAC_OPEN_WIRED = true;

    btns.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        window.__FAC_HANDLER_RAN = true;
        e.preventDefault();

        const fac = parseFacilityData(btn);
        if (!fac) {
          // Don’t fail silently — this is exactly what looks like an “unclickable” button.
          alert('Booking form could not be opened. Please hard refresh (Ctrl+F5) and try again.');
          return;
        }

        try {
          currentFac = fac;
          // Mark EIRC/Museum explicitly if button provides it
          try {
            const isEircAttr = btn && btn.dataset && (btn.dataset.isEirc === '1');
            const fname = (fac && fac.name) ? fac.name.toLowerCase() : '';
            currentFac.isEirc = isEircAttr || fname.includes('eirc') || fname.includes('irc') || fname.includes('museum');
          } catch (e) { currentFac.isEirc = false; }
          // Default: fixed slot schedule; Exception: Faculty / Reading Area use flexible time windows
          currentFac.slots = isFlexibleFacility(currentFac) ? null : DEFAULT_SLOTS;
          resetModal();
          populateModal(currentFac);
          showModalById('bookingModal');
          wireAvailabilityOnce();
          refreshAvailability();
        } catch (err) {
          console.error(err);
          try { showModalById('bookingModal'); } catch (e) {}
          try {
            const msg = (err && err.message) ? err.message : String(err);
            showAlert('danger', 'Booking UI error: ' + msg);
          } catch (e) {
            alert('Booking UI error: ' + ((err && err.message) ? err.message : String(err)));
          }
        }
      });
    });
  }

  // ── Open modal ──────────────────────────────────────────────────────────────
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wireOpenButtons);
  } else {
    wireOpenButtons();
  }

  function populateModal(fac) {
    // Title & subtitle
    document.getElementById('modalTitle').textContent =
      (fac.instant ? '⚡ ' : '📋 ') + fac.name;
    document.getElementById('modalSubtitle').textContent =
      fac.instant ? 'Instant booking · confirmed immediately'
                  : 'Morelos Building · admin approval required';

    // Chips
    const chips = document.getElementById('chipRow');
    if (chips) {
      chips.innerHTML =
        '<span class="booking-info-chip"><i class="fas fa-users"></i>Capacity ' + fac.capacity + '</span>' +
        (fac.max_day > 0
          ? '<span class="booking-info-chip"><i class="fas fa-calendar-day"></i>Max ' + fac.max_day + '/day</span>'
          : '');
    }

    // Hidden facility id
    document.getElementById('hiddenFacilityId').value = fac.id;
    // Save facility name on modal for consistency (used by availability/validation)
    const fModal = document.getElementById('bookingModal'); if (fModal) fModal.dataset.facilityName = fac.name;

    // Attendees max
    document.getElementById('attendeesCount').max   = fac.capacity;
    document.getElementById('attendeesCount').value = 1;
    document.getElementById('capacityNote').textContent = '(max ' + fac.capacity + ')';

    // Program/Level UI
    applyLevelUiForFacility(fac);

    // Time UI
    const slotGroupEl = document.getElementById('slotGroup');
    const customGroupEl = document.getElementById('customTimeGroup');

    if (isFlexibleFacility(fac)) {
      if (slotGroupEl) slotGroupEl.style.display = 'none';
      if (customGroupEl) customGroupEl.style.display = '';

      // Rebuild start/end selects for flexible facilities; remove 7:00/7:30 for EIRC/Museum
      const fnameLower = (fac && fac.name) ? fac.name.toLowerCase() : '';
      const isEirc = !!fac.isEirc || fnameLower.includes('eirc') || fnameLower.includes('irc') || fnameLower.includes('museum');
      const startEl = document.getElementById('startTimeCustom');
      const endEl = document.getElementById('endTimeCustom');
      if (startEl && endEl) {
        startEl.innerHTML = '';
        endEl.innerHTML = '';
        const startFrom = isEirc ? 8 : 7;
        const startTo = 17; // 5:00 PM
        for (let h = startFrom; h <= startTo; h++) {
          const val = (h < 10 ? '0' : '') + h + ':00';
          const disp = ((h % 12) === 0 ? 12 : (h % 12)) + ':00 ' + (h < 12 ? 'AM' : 'PM');
          const o = document.createElement('option'); o.value = val; o.textContent = disp; startEl.appendChild(o);
        }
        for (let h = (isEirc ? 8 : 7); h <= 17; h++) {
          const val = (h < 10 ? '0' : '') + h + ':30';
          const disp = ((h % 12) === 0 ? 12 : (h % 12)) + ':30 ' + (h < 12 ? 'AM' : 'PM');
          const o = document.createElement('option'); o.value = val; o.textContent = disp; endEl.appendChild(o);
        }
        const o18 = document.createElement('option'); o18.value = '18:00'; o18.textContent = '6:00 PM'; endEl.appendChild(o18);
      }
    } else {
      if (slotGroupEl) slotGroupEl.style.display = '';
      if (customGroupEl) customGroupEl.style.display = 'none';
      buildSlots((fac.slots && fac.slots.length) ? fac.slots : DEFAULT_SLOTS);
    }

    // Purpose
    const purposeSelectGroup = document.getElementById('purposeSelectGroup');
    const purposeTextGroup   = document.getElementById('purposeTextGroup');

    if (fac.purposes && fac.purposes.length > 0) {
      if (purposeSelectGroup) purposeSelectGroup.style.display = '';
      if (purposeTextGroup)   purposeTextGroup.style.display   = 'none';
      buildPurposes(fac.purposes);
    } else {
      if (purposeSelectGroup) purposeSelectGroup.style.display = 'none';
      if (purposeTextGroup)   purposeTextGroup.style.display   = '';
    }

    // Notices (guarded so missing DOM never breaks booking)
    const instantEl = document.getElementById('instantNotice');
    const requestEl = document.getElementById('requestNotice');
    if (instantEl) instantEl.classList.toggle('d-none', !fac.instant);
    if (requestEl) requestEl.classList.toggle('d-none',  fac.instant);

    const maxDayNotice = document.getElementById('maxDayNotice');
    const maxDayText   = document.getElementById('maxDayText');
    if (fac.max_day > 0) {
      if (maxDayNotice) maxDayNotice.classList.remove('d-none');
      if (maxDayText) maxDayText.textContent =
        'Max ' + fac.max_day + ' approved/pending bookings allowed per day for this facility.';
    } else {
      if (maxDayNotice) maxDayNotice.classList.add('d-none');
    }

    // Letter upload
    const letterGroup = document.getElementById('letterGroup');
    if (letterGroup) letterGroup.style.display = fac.requires_letter ? '' : 'none';

    // Submit button style
    const sb = document.getElementById('submitBtn');
    if (sb) sb.className = fac.instant ? 'btn btn-success' : 'btn btn-primary';

    const submitTextEl = document.getElementById('submitText');
    if (submitTextEl) {
      submitTextEl.innerHTML = fac.instant
        ? '<i class="fas fa-bolt me-1"></i>Confirm Booking'
        : '<i class="fas fa-paper-plane me-1"></i>Submit Request';
    }
  }

  function wireAvailabilityOnce() {
    if (availabilityWired) return;
    availabilityWired = true;

    const dateEl = document.getElementById('bookingDate');
    if (dateEl) dateEl.addEventListener('change', refreshAvailability);

    // Time selects (slot-based and flexible)
    ['slotSelect','startTimeCustom','endTimeCustom'].forEach(function (id) {
      const el = document.getElementById(id);
      if (el) el.addEventListener('change', function () {
        applyDisabledTimes();
      });
    });
  }

  function refreshAvailability() {
    if (!currentFac) return;
    const dateStr = (document.getElementById('bookingDate') || {}).value;

    if (!dateStr) {
      bookedIntervals = [];
      applyDisabledTimes();
      return;
    }

    const fd = new FormData();
    fd.append('action', 'availability');
    fd.append('facility_id', currentFac.id);
    fd.append('booking_date', dateStr);

    fetch(BASE_URL + '/faculty/ajax_book.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(function (data) {
        bookedIntervals = (data && data.success && Array.isArray(data.booked)) ? data.booked : [];
        applyDisabledTimes();
      })
      .catch(function () {
        bookedIntervals = [];
        applyDisabledTimes();
      });
  }

  function buildSlots(slots) {
    const sel = document.getElementById('slotSelect');
    if (!sel) return;

    sel.innerHTML = '';
    slots.forEach(function (sl) {
      const opt = document.createElement('option');
      opt.value = sl.start + '|' + sl.end;
      opt.textContent = sl.label;
      sel.appendChild(opt);
    });

    sel.selectedIndex = 0;
  }

  function applyDisabledTimes() {
    if (!currentFac) return;

    // Slot-based (default)
    if (currentFac.slots && currentFac.slots.length > 0) {
      const sel = document.getElementById('slotSelect');
      if (!sel) return;

      currentFac.slots.forEach(function (sl, idx) {
        const opt = sel.options[idx];
        if (!opt) return;

        if (!opt.dataset.label) opt.dataset.label = opt.textContent;
        const disabled = overlapsAny(toMin(sl.start), toMin(sl.end), bookedIntervals);
        opt.disabled = disabled;
        opt.textContent = disabled ? (opt.dataset.label + ' — Booked') : opt.dataset.label;
      });

      // Auto-fix if selected is disabled
      if (sel.selectedOptions[0] && sel.selectedOptions[0].disabled) {
        pickFirstEnabled(sel);
      }

      const sl = currentFac.slots[sel.selectedIndex];
      if (sl && overlapsAny(toMin(sl.start), toMin(sl.end), bookedIntervals)) {
        showAlert('warning', 'That slot is already booked. Please choose another slot (unavailable slots are disabled).');
      }
      return;
    }

    // Custom time (CL / Library)
    const startEl = document.getElementById('startTimeCustom');
    const endEl   = document.getElementById('endTimeCustom');
    if (!startEl || !endEl) return;

    const fnameLower = (currentFac && currentFac.name) ? currentFac.name.toLowerCase() : (document.getElementById('bookingModal') && document.getElementById('bookingModal').dataset.facilityName ? document.getElementById('bookingModal').dataset.facilityName.toLowerCase() : '');
    const modalIsEirc = (document.getElementById('bookingModal') && document.getElementById('bookingModal').dataset && document.getElementById('bookingModal').dataset.isEirc === '1');
    const isEirc = !!currentFac.isEirc || modalIsEirc || fnameLower.includes('eirc') || fnameLower.includes('museum');

    // 1) Disable START times that fall inside an existing booking (and enforce min/max for EIRC/Museum)
    Array.from(startEl.options).forEach(function (opt) {
      const s = toMin(opt.value);
      if (isEirc) {
        opt.disabled = (s < 8 * 60) || (s >= 18 * 60) || insideAny(s, bookedIntervals);
      } else {
        opt.disabled = insideAny(s, bookedIntervals);
      }
    });

    if (startEl.selectedOptions[0] && startEl.selectedOptions[0].disabled) pickFirstEnabled(startEl);

    // 2) Ensure END is after START
    const sNowMin = toMin(startEl.value);
    if (toMin(endEl.value) <= sNowMin) {
      pickFirstEnabledAfter(endEl, sNowMin);
    }

    // 3) Disable END times that are invalid for the current time window or would overlap
    Array.from(endEl.options).forEach(function (opt) {
      const e = toMin(opt.value);
      if (isEirc) {
        opt.disabled = (e <= sNowMin) || (e > 18 * 60) || overlapsAny(sNowMin, e, bookedIntervals);
      } else {
        const inMorning = sNowMin < 12 * 60;
        const inSameWindow = inMorning ? (e <= 12 * 60) : (e >= 13 * 60 && e <= 17 * 60);
        opt.disabled = !inSameWindow || (e <= sNowMin) || overlapsAny(sNowMin, e, bookedIntervals);
      }
    });

    if (endEl.selectedOptions[0] && endEl.selectedOptions[0].disabled) {
      const nextOk = Array.from(endEl.options).find(function (o) {
        const e = toMin(o.value);
        return !o.disabled && e > sNowMin;
      });
      if (nextOk) endEl.value = nextOk.value;
    }

    const eNow = toMin(endEl.value);
    if (sNowMin < eNow && overlapsAny(sNowMin, eNow, bookedIntervals)) {
      showAlert('warning', 'That time is already booked. Please choose another time (unavailable times are disabled).');
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

  function pickFirstEnabledByIndex(startSel, endSel) {
    for (let i = 0; i < startSel.options.length; i++) {
      if (!startSel.options[i].disabled) {
        startSel.selectedIndex = i;
        endSel.selectedIndex = i;
        return;
      }
    }
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

  function buildPurposes(purposes) {
    const sel = document.getElementById('purposeChoice');
    if (!sel) return;
    sel.innerHTML = '<option value="">-- Select Purpose --</option>';
    purposes.forEach(function (p) {
      const opt = document.createElement('option');
      opt.value = p; opt.textContent = p;
      sel.appendChild(opt);
    });

    sel.onchange = function () {
      const show = sel.value === 'Others';
      document.getElementById('othersGroup').style.display = show ? '' : 'none';
      if (!show) document.getElementById('purposeOther').value = '';
    };
  }

  // ── Submit ──────────────────────────────────────────────────────────────────
  const __submitBtn = document.getElementById('submitBtn');
  if (__submitBtn) __submitBtn.addEventListener('click', function () {
    clearAlert();

    if (!currentFac) return;

    const date      = document.getElementById('bookingDate').value;
    const attendees = parseInt(document.getElementById('attendeesCount').value, 10);
    const program   = (document.getElementById('programSelect') || {}).value || '';
    const level     = (document.getElementById('levelSelect')   || {}).value || '';

    if (!date) { showAlert('danger', 'Please select a booking date.'); return; }
    if (!attendees || attendees < 1) { showAlert('danger', 'Enter number of attendees.'); return; }
    if (!program) { showAlert('danger', 'Please select your program.'); return; }
    if (requiresLevelFacility(currentFac) && !level) { showAlert('danger', 'Please select the level (GS/JHS/SHS).'); return; }

    // Booking rule: Thu/Fri not open (4-day program)
    if (date) {
      const d = new Date(date + 'T00:00:00');
      const dow = isNaN(d.getTime()) ? null : d.getDay();
      if (dow === 4 || dow === 5) {
        showAlert('danger', 'Thursday and Friday are not open for bookings. Please choose another date.');
        return;
      }
    }

    // Determine start/end times
    let startTime, endTime;
    if (currentFac.slots && currentFac.slots.length > 0) {
      const idx = document.getElementById('slotSelect').selectedIndex;
      const sl = (currentFac.slots || [])[idx];
      if (!sl) { showAlert('danger', 'Please select a time slot.'); return; }
      startTime = sl.start + ':00';
      endTime   = sl.end   + ':00';

      if (overlapsAny(toMin(sl.start), toMin(sl.end), bookedIntervals)) {
        showAlert('danger', 'That slot is already booked. Please choose another slot.');
        return;
      }
    } else {
      // Flexible time (Faculty / Reading Area)
      const sVal = document.getElementById('startTimeCustom').value;
      const eVal = document.getElementById('endTimeCustom').value;

      const sMin = toMin(sVal);
      const eMin = toMin(eVal);
      const fnameLower = (currentFac && currentFac.name) ? currentFac.name.toLowerCase() : (document.getElementById('bookingModal') && document.getElementById('bookingModal').dataset.facilityName ? document.getElementById('bookingModal').dataset.facilityName.toLowerCase() : '');
      const isEirc = fnameLower.includes('eirc') || fnameLower.includes('museum');

      if (isEirc) {
        if (!(sMin >= 8*60 && eMin <= 18*60 && sMin < eMin)) {
          showAlert('danger', 'Allowed time is 8:00 AM–6:00 PM for this facility. Please choose start/end within the allowed range.');
          return;
        }
      } else {
        const morningOk = (sMin >= 7*60)  && (eMin <= 12*60);
        const aftOk     = (sMin >= 13*60) && (eMin <= 17*60);
        if (!(morningOk || aftOk) || !(sMin < eMin)) {
          showAlert('danger', 'Allowed time is 7:00 AM–12:00 PM or 1:00 PM–5:00 PM. Please choose start/end within the same time zone.');
          return;
        }
      }

      if (overlapsAny(sMin, eMin, bookedIntervals)) {
        showAlert('danger', 'That time is already booked. Please choose another time.');
        return;
      }

      startTime = sVal + ':00';
      endTime   = eVal + ':00';
    }

    if (!startTime || !endTime || startTime >= endTime) {
      showAlert('danger', 'End time must be after start time.');
      return;
    }

    // Determine purpose
    let purpose = '';
    if (currentFac.purposes && currentFac.purposes.length > 0) {
      const choice = document.getElementById('purposeChoice').value;
      if (!choice) { showAlert('danger', 'Please select a purpose.'); return; }
      if (choice === 'Others') {
        const other = document.getElementById('purposeOther').value.trim();
        if (!other) { showAlert('danger', 'Please specify the purpose.'); return; }
        purpose = other;
      } else {
        purpose = choice;
      }
    } else {
      purpose = document.getElementById('purposeText').value.trim();
      if (!purpose) { showAlert('danger', 'Please enter the purpose.'); return; }
    }

    setLoading(true);

    const fd = new FormData();
    fd.append('facility_id',    currentFac.id);
    fd.append('booking_date',   date);
    fd.append('start_time',     startTime);
    fd.append('end_time',       endTime);
    fd.append('purpose',         purpose);
    fd.append('attendees_count',  attendees);
    fd.append('program',          program);
    fd.append('level',            level);

    const letter = document.getElementById('requestLetter');
    if (letter && letter.files[0]) fd.append('request_letter', letter.files[0]);

    fetch(BASE_URL + '/faculty/ajax_book.php', { method:'POST', body: fd })
      .then(r => r.json())
      .then(function (data) {
        setLoading(false);
        if (data.success) {
          showAlert('success', data.message);
          setTimeout(function () {
            hideModalById('bookingModal');
            window.location.href = BASE_URL + '/faculty/dashboard.php#my-bookings';
          }, 1400);
        } else {
          showAlert('danger', data.message || 'Something went wrong.');
        }
      })
      .catch(function () {
        setLoading(false);
        showAlert('danger', 'Network error. Please try again.');
      });
  });

  // ── Cancel ──────────────────────────────────────────────────────────────────
  let cancelId = null;
  document.querySelectorAll('.btn-cancel').forEach(function (btn) {
    btn.addEventListener('click', function () {
      cancelId = btn.dataset.id;
      document.getElementById('cancelName').textContent = btn.dataset.name;
      showModalById('cancelModal');
    });
  });

  const __confirmCancel = document.getElementById('confirmCancel');
  if (__confirmCancel) __confirmCancel.addEventListener('click', function () {
    if (!cancelId) return;
    this.disabled = true; this.textContent = 'Cancelling…';
    const fd = new FormData();
    fd.append('action', 'cancel');
    fd.append('booking_id', cancelId);
    fetch(BASE_URL + '/faculty/ajax_book.php', { method:'POST', body:fd })
      .then(r => r.json())
      .then(data => {
        if (data.success) window.location.reload();
        else { alert(data.message); this.disabled=false; this.textContent='Yes, Cancel'; }
      })
      .catch(() => { alert('Network error.'); this.disabled=false; this.textContent='Yes, Cancel'; });
  });

  // ── Helpers ─────────────────────────────────────────────────────────────────
  function showAlert(type, msg) {
    const el = document.getElementById('modalAlert');
    if (!el) return;
    el.className = 'alert alert-' + type + ' py-2 small mb-3';
    el.textContent = msg;
  }
  function clearAlert() {
    const el = document.getElementById('modalAlert');
    if (!el) return;
    el.className = 'd-none mb-3';
    el.textContent = '';
  }
  function setLoading(on) {
    const submitText = document.getElementById('submitText');
    const submitSpinner = document.getElementById('submitSpinner');
    const submitBtn = document.getElementById('submitBtn');

    if (submitText && submitText.classList) submitText.classList.toggle('d-none', on);
    if (submitSpinner && submitSpinner.classList) submitSpinner.classList.toggle('d-none', !on);
    if (submitBtn) submitBtn.disabled = on;
  }
  function resetModal() {
    clearAlert();
    const form = document.getElementById('bookingForm');
    if (form) form.reset();
    const others = document.getElementById('othersGroup');
    if (others) others.style.display = 'none';

    // Reset Level UI state to defaults (full-width Program)
    try { applyLevelUiForFacility({ name: '' }); } catch (e) {}
  }

})();
