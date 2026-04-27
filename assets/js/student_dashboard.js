/* student_dashboard.js — AJAX booking + cancel for Student Dashboard */
(function () {
  'use strict';

  let bookedIntervals = [];
  let availabilityWired = false;

  // ── Fill booking modal on open ─────────────────────────────────────────────
  const bookingModal = document.getElementById('bookingModal');
  if (bookingModal) {
    bookingModal.addEventListener('show.bs.modal', function (event) {
      const btn = event.relatedTarget;
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

      // Info chips
      document.getElementById('modalInfoRow').innerHTML =
        '<span class="booking-info-chip"><i class="fas fa-users"></i>Max ' + capacity + ' people</span>' +
        '<span class="booking-info-chip"><i class="fas fa-clock"></i>8:00 AM – 6:00 PM</span>';

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
    });
  }

  function wireAvailabilityOnce() {
    if (availabilityWired) return;
    availabilityWired = true;

    const dateEl  = document.getElementById('bookingDate');
    const startEl = document.getElementById('startTime');
    const endEl   = document.getElementById('endTime');

    if (!dateEl || !startEl || !endEl) return;

    dateEl.addEventListener('change', function () {
      refreshAvailability();
    });
    startEl.addEventListener('change', function () {
      applyDisabledTimes();
    });
    endEl.addEventListener('change', function () {
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
    const startEl = document.getElementById('startTime');
    const endEl   = document.getElementById('endTime');
    if (!startEl || !endEl) return;

    // 1) Disable START times that fall inside an existing booking
    Array.from(startEl.options).forEach(function (opt) {
      const s = toMin(opt.value);
      opt.disabled = insideAny(s, bookedIntervals);
    });

    // Auto-fix if selected start is now disabled
    if (startEl.selectedOptions[0] && startEl.selectedOptions[0].disabled) {
      pickFirstEnabled(startEl);
    }

    // 2) Ensure END is after START (auto-advance end if needed)
    const sNowMin = toMin(startEl.value);
    if (toMin(endEl.value) <= sNowMin) {
      pickFirstEnabledAfter(endEl, sNowMin);
    }

    // 3) Disable END times that would overlap an existing booking
    Array.from(endEl.options).forEach(function (opt) {
      const e = toMin(opt.value);
      opt.disabled = (e <= sNowMin) || overlapsAny(sNowMin, e, bookedIntervals);
    });

    // Auto-fix if selected end is now disabled
    if (endEl.selectedOptions[0] && endEl.selectedOptions[0].disabled) {
      pickFirstEnabledAfter(endEl, sNowMin);
    }

    // If still overlapping, show a helpful message
    const eNowMin = toMin(endEl.value);
    if (sNowMin < eNowMin && overlapsAny(sNowMin, eNowMin, bookedIntervals)) {
      showModalAlert('warning', 'That time is already booked. Please choose another time (unavailable times are disabled).');
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

      // Time range check client-side
      const start = document.getElementById('startTime').value;
      const end   = document.getElementById('endTime').value;
      if (start < '08:00' || end > '18:00') {
        showModalAlert('danger', 'Booking must be between 8:00 AM and 6:00 PM.');
        return;
      }
      if (start >= end) {
        showModalAlert('danger', 'End time must be after start time.');
        return;
      }

      // If our availability call already knows this overlaps, block early
      if (overlapsAny(toMin(start), toMin(end), bookedIntervals)) {
        showModalAlert('danger', 'That time is already booked. Please choose another time.');
        return;
      }

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
            const modal = bootstrap.Modal.getInstance(document.getElementById('bookingModal'));
            if (modal) modal.hide();
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
      new bootstrap.Modal(document.getElementById('cancelModal')).show();
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
