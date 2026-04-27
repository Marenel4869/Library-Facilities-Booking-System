/* faculty_dashboard.js */
(function () {
  'use strict';

  let currentFac = null;
  let bookedIntervals = [];
  let availabilityWired = false;
  let syncingSlots = false;

  // ── Open modal ──────────────────────────────────────────────────────────────
  document.querySelectorAll('.btn-open-modal').forEach(function (btn) {
    btn.addEventListener('click', function () {
      try { currentFac = JSON.parse(btn.dataset.fac); } catch(e) { return; }
      resetModal();
      populateModal(currentFac);
      wireAvailabilityOnce();
      refreshAvailability();
      bootstrap.Modal.getOrCreateInstance(document.getElementById('bookingModal')).show();
    });
  });

  function populateModal(fac) {
    // Title & subtitle
    document.getElementById('modalTitle').textContent =
      (fac.instant ? '⚡ ' : '📋 ') + fac.name;
    document.getElementById('modalSubtitle').textContent =
      fac.instant ? 'Instant booking · confirmed immediately'
                  : 'Morelos Building · admin approval required';

    // Chips
    const chips = document.getElementById('chipRow');
    chips.innerHTML =
      '<span class="booking-info-chip"><i class="fas fa-users"></i>Capacity ' + fac.capacity + '</span>' +
      (fac.max_day > 0
        ? '<span class="booking-info-chip"><i class="fas fa-calendar-day"></i>Max ' + fac.max_day + '/day</span>'
        : '');

    // Hidden facility id
    document.getElementById('hiddenFacilityId').value = fac.id;

    // Attendees max
    document.getElementById('attendeesCount').max   = fac.capacity;
    document.getElementById('attendeesCount').value = 1;
    document.getElementById('capacityNote').textContent = '(max ' + fac.capacity + ')';

    // Time: slot-based (Morelos) vs custom (CL)
    if (fac.slots && fac.slots.length > 0) {
      document.getElementById('slotGroup').style.display = '';
      document.getElementById('customTimeGroup').style.display = 'none';
      buildSlots(fac.slots);
    } else {
      document.getElementById('slotGroup').style.display = 'none';
      document.getElementById('customTimeGroup').style.display = '';
    }

    // Purpose
    if (fac.purposes && fac.purposes.length > 0) {
      document.getElementById('purposeSelectGroup').style.display = '';
      document.getElementById('purposeTextGroup').style.display   = 'none';
      buildPurposes(fac.purposes);
    } else {
      document.getElementById('purposeSelectGroup').style.display = 'none';
      document.getElementById('purposeTextGroup').style.display   = '';
    }

    // Notices
    document.getElementById('instantNotice').classList.toggle('d-none', !fac.instant);
    document.getElementById('requestNotice').classList.toggle('d-none',  fac.instant);

    if (fac.max_day > 0) {
      document.getElementById('maxDayNotice').classList.remove('d-none');
      document.getElementById('maxDayText').textContent =
        'Max ' + fac.max_day + ' approved/pending bookings allowed per day for this facility.';
    } else {
      document.getElementById('maxDayNotice').classList.add('d-none');
    }

    // Letter upload
    document.getElementById('letterGroup').style.display = fac.requires_letter ? '' : 'none';

    // Submit button style
    const sb = document.getElementById('submitBtn');
    sb.className = fac.instant ? 'btn btn-success' : 'btn btn-primary';
    document.getElementById('submitText').innerHTML = fac.instant
      ? '<i class="fas fa-bolt me-1"></i>Confirm Booking'
      : '<i class="fas fa-paper-plane me-1"></i>Submit Request';
  }

  function wireAvailabilityOnce() {
    if (availabilityWired) return;
    availabilityWired = true;

    const dateEl = document.getElementById('bookingDate');
    if (dateEl) dateEl.addEventListener('change', refreshAvailability);

    // Time selects (both slot-based and custom)
    ['slotStart','slotEnd','startTimeCustom','endTimeCustom'].forEach(function (id) {
      const el = document.getElementById(id);
      if (el) el.addEventListener('change', function () {
        if (id === 'slotStart') syncSlotSelects('start');
        if (id === 'slotEnd')   syncSlotSelects('end');
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
    const startSel = document.getElementById('slotStart');
    const endSel   = document.getElementById('slotEnd');
    if (!startSel || !endSel) return;

    // Replace the generic half-hour options with the facility-defined slot list
    startSel.innerHTML = '';
    endSel.innerHTML = '';

    slots.forEach(function (sl, idx) {
      const startOpt = document.createElement('option');
      startOpt.value = sl.start;
      startOpt.textContent = sl.label;
      startOpt.dataset.idx = String(idx);
      startSel.appendChild(startOpt);

      const endOpt = document.createElement('option');
      endOpt.value = sl.end;
      endOpt.textContent = sl.label;
      endOpt.dataset.idx = String(idx);
      endSel.appendChild(endOpt);
    });

    startSel.selectedIndex = 0;
    endSel.selectedIndex = 0;
  }

  function syncSlotSelects(changed) {
    if (syncingSlots) return;

    const startSel = document.getElementById('slotStart');
    const endSel   = document.getElementById('slotEnd');
    if (!startSel || !endSel) return;

    syncingSlots = true;
    if (changed === 'start') endSel.selectedIndex = startSel.selectedIndex;
    if (changed === 'end')   startSel.selectedIndex = endSel.selectedIndex;
    syncingSlots = false;
  }

  function applyDisabledTimes() {
    if (!currentFac) return;

    // Slot-based (Morelos)
    if (currentFac.slots && currentFac.slots.length > 0) {
      const startSel = document.getElementById('slotStart');
      const endSel   = document.getElementById('slotEnd');
      if (!startSel || !endSel) return;

      // Disable whole slots that overlap any booked interval
      currentFac.slots.forEach(function (sl, idx) {
        const s = toMin(sl.start);
        const e = toMin(sl.end);
        const disabled = overlapsAny(s, e, bookedIntervals);
        if (startSel.options[idx]) startSel.options[idx].disabled = disabled;
        if (endSel.options[idx])   endSel.options[idx].disabled   = disabled;
      });

      // Auto-fix if selected is disabled
      if (startSel.selectedOptions[0] && startSel.selectedOptions[0].disabled) {
        pickFirstEnabledByIndex(startSel, endSel);
      }

      const sl = currentFac.slots[startSel.selectedIndex];
      if (sl) {
        const sNow = toMin(sl.start);
        const eNow = toMin(sl.end);
        if (overlapsAny(sNow, eNow, bookedIntervals)) {
          showAlert('warning', 'That slot is already booked. Please choose another slot (unavailable slots are disabled).');
        }
      }
      return;
    }

    // Custom time (CL / Library)
    const startEl = document.getElementById('startTimeCustom');
    const endEl   = document.getElementById('endTimeCustom');
    if (!startEl || !endEl) return;

    // 1) Disable START times that fall inside an existing booking
    Array.from(startEl.options).forEach(function (opt) {
      const s = toMin(opt.value);
      opt.disabled = insideAny(s, bookedIntervals);
    });

    if (startEl.selectedOptions[0] && startEl.selectedOptions[0].disabled) pickFirstEnabled(startEl);

    // 2) Ensure END is after START
    const sNowMin = toMin(startEl.value);
    if (toMin(endEl.value) <= sNowMin) {
      pickFirstEnabledAfter(endEl, sNowMin);
    }

    // 3) Disable END times that would overlap an existing booking
    Array.from(endEl.options).forEach(function (opt) {
      const e = toMin(opt.value);
      opt.disabled = (e <= sNowMin) || overlapsAny(sNowMin, e, bookedIntervals);
    });

    if (endEl.selectedOptions[0] && endEl.selectedOptions[0].disabled) pickFirstEnabledAfter(endEl, sNowMin);

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
  document.getElementById('submitBtn').addEventListener('click', function () {
    clearAlert();

    if (!currentFac) return;

    const date      = document.getElementById('bookingDate').value;
    const attendees = parseInt(document.getElementById('attendeesCount').value, 10);
    const program   = (document.getElementById('programSelect') || {}).value || '';

    if (!date) { showAlert('danger', 'Please select a booking date.'); return; }
    if (!attendees || attendees < 1) { showAlert('danger', 'Enter number of attendees.'); return; }
    if (!program) { showAlert('danger', 'Please select your program.'); return; }

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
      // Morelos: exact slot list (front-end keeps start/end in sync)
      const idx = document.getElementById('slotStart').selectedIndex;
      const sl = (currentFac.slots || [])[idx];
      if (!sl) { showAlert('danger', 'Please select a time slot.'); return; }
      startTime = sl.start + ':00';
      endTime   = sl.end   + ':00';

      if (overlapsAny(toMin(sl.start), toMin(sl.end), bookedIntervals)) {
        showAlert('danger', 'That slot is already booked. Please choose another slot.');
        return;
      }
    } else {
      // CL: read from custom time selects
      const sVal = document.getElementById('startTimeCustom').value;
      const eVal = document.getElementById('endTimeCustom').value;

      if (overlapsAny(toMin(sVal), toMin(eVal), bookedIntervals)) {
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

    const letter = document.getElementById('requestLetter');
    if (letter && letter.files[0]) fd.append('request_letter', letter.files[0]);

    fetch(BASE_URL + '/faculty/ajax_book.php', { method:'POST', body: fd })
      .then(r => r.json())
      .then(function (data) {
        setLoading(false);
        if (data.success) {
          showAlert('success', data.message);
          setTimeout(function () {
            bootstrap.Modal.getInstance(document.getElementById('bookingModal')).hide();
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
      bootstrap.Modal.getOrCreateInstance(document.getElementById('cancelModal')).show();
    });
  });

  document.getElementById('confirmCancel').addEventListener('click', function () {
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
    el.className = 'alert alert-' + type + ' py-2 small mb-3';
    el.textContent = msg;
  }
  function clearAlert() {
    const el = document.getElementById('modalAlert');
    el.className = 'd-none mb-3';
    el.textContent = '';
  }
  function setLoading(on) {
    document.getElementById('submitText').classList.toggle('d-none', on);
    document.getElementById('submitSpinner').classList.toggle('d-none', !on);
    document.getElementById('submitBtn').disabled = on;
  }
  function resetModal() {
    clearAlert();
    document.getElementById('bookingForm').reset();
    document.getElementById('othersGroup').style.display = 'none';
  }

})();
