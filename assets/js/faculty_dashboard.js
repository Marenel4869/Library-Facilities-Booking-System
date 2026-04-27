/* faculty_dashboard.js */
(function () {
  'use strict';

  let currentFac = null;

  // ── Open modal ──────────────────────────────────────────────────────────────
  document.querySelectorAll('.btn-open-modal').forEach(function (btn) {
    btn.addEventListener('click', function () {
      try { currentFac = JSON.parse(btn.dataset.fac); } catch(e) { return; }
      resetModal();
      populateModal(currentFac);
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

  function buildPurposes(purposes) {
    const sel = document.getElementById('purposeChoice');
    sel.innerHTML = '<option value="">-- Select Purpose --</option>';
    purposes.forEach(function (p) {
      const opt = document.createElement('option');
      opt.value = p; opt.textContent = p;
      sel.appendChild(opt);
    });

    sel.addEventListener('change', function () {
      const show = sel.value === 'Others';
      document.getElementById('othersGroup').style.display = show ? '' : 'none';
      if (!show) document.getElementById('purposeOther').value = '';
    });
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
      // Morelos: read from slot select dropdowns
      startTime = document.getElementById('slotStart').value + ':00';
      endTime   = document.getElementById('slotEnd').value   + ':00';
    } else {
      // CL: read from custom time selects
      startTime = document.getElementById('startTimeCustom').value + ':00';
      endTime   = document.getElementById('endTimeCustom').value   + ':00';
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
