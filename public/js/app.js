/* LexSched front-end behaviour: booking wizard, walk-in form and confirmations. No build step needed. */
(function () {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const peso = (amount) => '₱' + Number(amount).toLocaleString('en-PH', { maximumFractionDigits: 2 });
    const pad = (n) => String(n).padStart(2, '0');
    const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const parseIso = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const longDate = (s) => parseIso(s).toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const initials = (name) => name.replace('Atty. ', '').split(' ').filter(Boolean).map((w) => w[0]).slice(0, 2).join('');

    async function getJson(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) { throw new Error('Could not load availability'); }
        return response.json();
    }

    /* Ask before destructive actions: <form data-confirm="Cancel this appointment?"> */
    document.addEventListener('submit', (event) => {
        const message = event.target.dataset.confirm;
        if (message && !window.confirm(message)) { event.preventDefault(); }
    });

    /* Rows that open a detail panel: <tr class="row" data-href="..."> */
    document.addEventListener('click', (event) => {
        const row = event.target.closest('tr[data-href]');
        if (row && !event.target.closest('a,button,input,select,label')) { window.location = row.dataset.href; }
    });

    /**
     * Calendar + time slot picker shared by the booking wizard and the walk-in form.
     */
    function schedulePicker({ endpoint, serviceId, lawyerId, dateInput, timeInput, calendar, title, prev, next, slots, slotsTitle, onChange }) {
        const today = new Date(); today.setHours(0, 0, 0, 0);
        let month = dateInput.value ? parseIso(dateInput.value) : new Date(today);
        month.setDate(1);
        let openDays = [];

        async function loadMonth() {
            title.textContent = month.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
            prev.disabled = month <= new Date(today.getFullYear(), today.getMonth(), 1);
            calendar.innerHTML = '<p class="muted small" style="grid-column:1/-1">Loading…</p>';
            const data = await getJson(`${endpoint}?service=${serviceId()}&lawyer=${lawyerId()}&month=${month.getFullYear()}-${pad(month.getMonth() + 1)}`);
            openDays = data.days || [];
            renderCalendar();
        }

        function renderCalendar() {
            const names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => `<span class="dh">${d}</span>`).join('');
            const first = new Date(month.getFullYear(), month.getMonth(), 1).getDay();
            const count = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
            let cells = '<span></span>'.repeat(first);
            for (let day = 1; day <= count; day++) {
                const value = iso(new Date(month.getFullYear(), month.getMonth(), day));
                const open = openDays.includes(value);
                const classes = [value === iso(today) ? 'today' : '', value === dateInput.value ? 'on' : ''].join(' ');
                cells += `<button type="button" class="${classes}" data-date="${value}" ${open ? '' : 'disabled'} aria-label="${longDate(value)}">${day}</button>`;
            }
            calendar.innerHTML = names + cells;
        }

        async function loadSlots() {
            if (!dateInput.value) {
                slotsTitle.textContent = 'Open times';
                slots.innerHTML = '<p class="muted small">Pick a date to see times.</p>';
                return;
            }
            slotsTitle.textContent = 'Open times · ' + parseIso(dateInput.value).toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric' });
            slots.innerHTML = '<p class="muted small">Loading…</p>';
            const data = await getJson(`${endpoint}?service=${serviceId()}&lawyer=${lawyerId()}&date=${dateInput.value}`);
            if (!data.slots.some((slot) => slot.open)) { timeInput.value = ''; }
            if (timeInput.value && !data.slots.find((slot) => slot.time === timeInput.value && slot.open)) { timeInput.value = ''; }
            slots.innerHTML = '<div class="slots">' + data.slots.map((slot) =>
                `<button type="button" data-time="${slot.time}" class="${slot.time === timeInput.value ? 'on' : ''}" ${slot.open ? '' : 'disabled'}>${slot.label}</button>`).join('') + '</div>';
            onChange();
        }

        calendar.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-date]');
            if (!button) { return; }
            dateInput.value = button.dataset.date;
            timeInput.value = '';
            renderCalendar();
            loadSlots();
            onChange();
        });
        slots.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-time]');
            if (!button) { return; }
            timeInput.value = button.dataset.time;
            $$('button', slots).forEach((b) => b.classList.toggle('on', b === button));
            onChange();
        });
        prev.addEventListener('click', () => { month.setMonth(month.getMonth() - 1); loadMonth(); });
        next.addEventListener('click', () => { month.setMonth(month.getMonth() + 1); loadMonth(); });

        return {
            async refresh({ keepSelection = false } = {}) {
                if (!keepSelection) { dateInput.value = ''; timeInput.value = ''; }
                await loadMonth();
                await loadSlots();
            },
        };
    }

    /* ---------------- Booking wizard ---------------- */
    const form = $('#booking');
    if (form) {
        const old = JSON.parse(form.dataset.old || '{}') || {};
        const errorFields = JSON.parse(form.dataset.errors || '[]');
        const copyFee = Number(form.dataset.copyFee);
        const endpoint = form.dataset.availability;
        const sections = $$('section[data-step]', form);
        const stepError = $('#step-error');
        let step = 1;
        let lawyers = [];

        const service = () => $('input[name=service_id]:checked', form);
        const isNotarial = () => service()?.dataset.notarial === '1';
        const lawyerValue = () => $('input[name=lawyer_id]:checked', form)?.value || '';
        const documentOption = () => $('#notarial_document_id').selectedOptions[0];
        const copies = () => Math.min(10, Math.max(1, parseInt($('#copies').value, 10) || 1));
        const fee = () => (isNotarial() ? Number(documentOption().dataset.fee) + (copies() - 1) * copyFee : Number(service()?.dataset.fee || 0));

        const picker = schedulePicker({
            endpoint,
            serviceId: () => service()?.value || '',
            lawyerId: lawyerValue,
            dateInput: $('#f-date'),
            timeInput: $('#f-time'),
            calendar: $('#cal'),
            title: $('#cal-title'),
            prev: $('#cal-prev'),
            next: $('#cal-next'),
            slots: $('#slots'),
            slotsTitle: $('#slots-title'),
            onChange: () => { stepError.hidden = true; },
        });

        async function loadLawyers(selected) {
            const box = $('#lawyers');
            box.innerHTML = '<p class="muted small">Loading lawyers…</p>';
            const data = await getJson(`${endpoint}?service=${service().value}`);
            lawyers = data.lawyers;
            const options = lawyers.map((l) => `<label class="lawyer"><input type="radio" name="lawyer_id" value="${l.id}">
                <span class="avatar">${escapeHtml(initials(l.name))}</span><span><b>${escapeHtml(l.name)}</b><br><span class="small muted">${escapeHtml(l.services.join(' · '))}</span></span></label>`);
            options.push(`<label class="lawyer"><input type="radio" name="lawyer_id" value="any"><span class="avatar">Any</span>
                <span><b>Any available lawyer</b><br><span class="small muted">Shows the most open times</span></span></label>`);
            box.innerHTML = lawyers.length ? options.join('') : '<p class="muted">No lawyer is taking bookings for this service right now. Please call the office.</p>';
            const target = $(`input[name=lawyer_id][value="${selected}"]`, box) || $('input[name=lawyer_id][value=any]', box);
            if (target) { target.checked = true; }
        }

        function applyServiceType() {
            const notarial = isNotarial();
            $$('[data-only=notarial]', form).forEach((el) => { el.hidden = !notarial; });
            $$('[data-only=regular]', form).forEach((el) => { el.hidden = notarial; });
            $('label[for=concern]').textContent = notarial ? 'Notes for the notary (optional)' : 'What is your legal concern?';
            $('#concern').placeholder = notarial ? 'What was lost, names of the parties, property or vehicle details' : 'Briefly describe what happened and what you need help with.';
            renderRequirements();
        }

        function renderRequirements() {
            if (!isNotarial()) { return; }
            $('#reqs').innerHTML = JSON.parse(documentOption().dataset.reqs).map((r) => `<li>${escapeHtml(r)}</li>`).join('');
        }

        function renderSummary() {
            const lawyerInput = $('input[name=lawyer_id]:checked', form);
            const lawyerName = lawyerInput?.value === 'any' ? 'First available lawyer' : lawyers.find((l) => String(l.id) === lawyerInput?.value)?.name;
            const time = $('#slots button.on')?.textContent || $('#f-time').value;
            const rows = [['Service', service().dataset.name]];
            if (isNotarial()) { rows.push(['Document', `${documentOption().textContent.split(' · ')[0]} · ${copies()} ${copies() > 1 ? 'copies' : 'copy'}`]); }
            rows.push([isNotarial() ? 'Notary public' : 'Lawyer', lawyerName || '']);
            rows.push(['Schedule', `${longDate($('#f-date').value)}, ${time}`]);
            rows.push(['Mode', isNotarial() || $('input[name=mode]:checked').value === 'in_office' ? 'In-office' : 'Video call']);
            if (!isNotarial()) { rows.push(['Opposing party', $('#opposing_party').value || 'None given']); }
            rows.push([isNotarial() ? 'Notes' : 'Concern', $('#concern').value || 'None']);
            if ($('#attachment').files[0]) { rows.push(['Attachment', $('#attachment').files[0].name]); }
            $('#summary').innerHTML = rows.map(([k, v]) => `<dt>${k}</dt><dd>${escapeHtml(v)}</dd>`).join('');
            $('#summary-reqs').innerHTML = isNotarial() ? `<div class="reqs"><div><div class="label" style="margin-bottom:6px">Bring on your visit</div><ul>${$('#reqs').innerHTML}</ul></div></div>` : '';

            const lines = isNotarial()
                ? `<div class="line"><span>${escapeHtml(documentOption().textContent.split(' · ')[0])}</span><span class="num">${peso(documentOption().dataset.fee)}</span></div>`
                  + (copies() > 1 ? `<div class="line"><span>${copies() - 1} extra ${copies() > 2 ? 'copies' : 'copy'} × ${peso(copyFee)}</span><span class="num">${peso((copies() - 1) * copyFee)}</span></div>` : '')
                : '';
            $('#fee-lines').innerHTML = lines;
            $('#fee-label').textContent = isNotarial() ? 'Notarial fee' : 'Consultation fee';
            $('#fee-total').textContent = peso(fee());
            applyPayment();
        }

        function applyPayment() {
            const method = $('input[name=payment_method]:checked', form)?.value;
            $$('[data-wallet]', form).forEach((el) => { el.hidden = el.dataset.wallet !== method; });
            $('#payment_reference').closest('.field').hidden = !['gcash', 'maya'].includes(method);
            $('#submit').textContent = method === 'online' ? 'Submit and pay online' : 'Submit booking request';
        }

        function validate(current) {
            if (current === 1 && !service()) { return 'Choose a service to continue.'; }
            if (current === 2 && !lawyerValue()) { return 'Choose a lawyer.'; }
            if (current === 2 && !$('#f-time').value) { return 'Pick a date and an open time.'; }
            if (current === 3 && !isNotarial() && !$('#concern').value.trim()) { $('#concern').focus(); return 'Please describe your concern so the lawyer can prepare.'; }
            if (current === 3 && !$('#consent').checked) { return 'Please give your consent under the Data Privacy Act to continue.'; }
            return null;
        }

        function show(target) {
            step = target;
            sections.forEach((section) => { section.hidden = Number(section.dataset.step) !== step; });
            $$('#steps > div').forEach((el, i) => {
                el.classList.toggle('done', i + 1 < step);
                el.classList.toggle('on', i + 1 === step);
            });
            $('#back').style.visibility = step === 1 ? 'hidden' : 'visible';
            $('#next').hidden = step === 4;
            $('#submit').hidden = step !== 4;
            $('#next').textContent = ['', 'Next: Lawyer & schedule →', 'Next: Case details →', 'Next: Confirm & pay →'][step] || '';
            stepError.hidden = true;
            if (step === 3) { applyServiceType(); }
            if (step === 4) { renderSummary(); }
            window.scrollTo({ top: 0 });
        }

        form.addEventListener('change', async (event) => {
            if (event.target.name === 'service_id') {
                await loadLawyers('any');
                await picker.refresh();
                show(2);
            }
            if (event.target.name === 'lawyer_id') { await picker.refresh(); }
            if (event.target.id === 'notarial_document_id') { renderRequirements(); }
            if (event.target.name === 'payment_method') { applyPayment(); }
        });

        $('#next').addEventListener('click', () => {
            const problem = validate(step);
            if (problem) { stepError.textContent = problem; stepError.hidden = false; return; }
            show(step + 1);
        });
        $('#back').addEventListener('click', () => show(step - 1));
        form.addEventListener('submit', (event) => {
            const problem = validate(2) || validate(3);
            if (problem) { event.preventDefault(); stepError.textContent = problem; stepError.hidden = false; return; }
            $('#submit').disabled = true;
            $('#submit').textContent = 'Sending…';
        });

        /* Restore the previous attempt after a validation error, or a service chosen on the home page. */
        (async function restore() {
            const radio = old.service_id && $(`input[name=service_id][value="${old.service_id}"]`, form);
            if (!radio) { show(1); return; }
            radio.checked = true;
            await loadLawyers(old.lawyer_id || 'any');
            await picker.refresh({ keepSelection: true });
            const stepFor = (field) => (['service_id'].includes(field) ? 1
                : ['lawyer_id', 'date', 'time'].includes(field) ? 2
                : ['payment_method', 'payment_reference'].includes(field) ? 4 : 3);
            const errorStep = errorFields.length ? Math.min(...errorFields.map(stepFor)) : (old.lawyer_id ? 4 : 2);
            show(errorStep === 4 && !$('#f-time').value ? 2 : errorStep);
        })();
    }

    /* ---------------- Walk-in form ---------------- */
    const walkIn = $('#walkin');
    if (walkIn) {
        const endpoint = walkIn.dataset.availability;
        const serviceSelect = $('#service_id', walkIn);
        const lawyerSelect = $('#lawyer_id', walkIn);
        const isNotarial = () => serviceSelect.selectedOptions[0]?.dataset.notarial === '1';

        const picker = schedulePicker({
            endpoint,
            serviceId: () => serviceSelect.value,
            lawyerId: () => lawyerSelect.value,
            dateInput: $('#f-date', walkIn),
            timeInput: $('#f-time', walkIn),
            calendar: $('#cal', walkIn),
            title: $('#cal-title', walkIn),
            prev: $('#cal-prev', walkIn),
            next: $('#cal-next', walkIn),
            slots: $('#slots', walkIn),
            slotsTitle: $('#slots-title', walkIn),
            onChange: () => {},
        });

        async function loadLawyers(keep) {
            const data = await getJson(`${endpoint}?service=${serviceSelect.value}`);
            const selected = keep || lawyerSelect.dataset.old || 'any';
            lawyerSelect.innerHTML = '<option value="any">First available</option>'
                + data.lawyers.map((l) => `<option value="${l.id}" ${String(l.id) === String(selected) ? 'selected' : ''}>${escapeHtml(l.name)}</option>`).join('');
            $$('[data-only=notarial]', walkIn).forEach((el) => { el.hidden = !isNotarial(); });
            $$('[data-only=regular]', walkIn).forEach((el) => { el.hidden = isNotarial(); });
        }

        serviceSelect.addEventListener('change', async () => { lawyerSelect.dataset.old = ''; await loadLawyers(); await picker.refresh(); });
        lawyerSelect.addEventListener('change', () => picker.refresh());
        (async () => {
            await loadLawyers();
            if (!$('#f-date', walkIn).value) { $('#f-date', walkIn).value = walkIn.dataset.today; }
            await picker.refresh({ keepSelection: true });
        })();
    }

    /* ---------------- Reschedule form ---------------- */
    const reschedule = $('#reschedule');
    if (reschedule) {
        const endpoint = reschedule.dataset.availability;
        const lawyerSelect = $('#lawyer_id', reschedule);
        const picker = schedulePicker({
            endpoint,
            serviceId: () => reschedule.dataset.service,
            lawyerId: () => lawyerSelect.value,
            dateInput: $('#f-date', reschedule),
            timeInput: $('#f-time', reschedule),
            calendar: $('#cal', reschedule),
            title: $('#cal-title', reschedule),
            prev: $('#cal-prev', reschedule),
            next: $('#cal-next', reschedule),
            slots: $('#slots', reschedule),
            slotsTitle: $('#slots-title', reschedule),
            onChange: () => {},
        });
        lawyerSelect.addEventListener('change', () => picker.refresh());
        (async () => {
            const data = await getJson(`${endpoint}?service=${reschedule.dataset.service}`);
            const current = reschedule.dataset.lawyer;
            lawyerSelect.innerHTML = data.lawyers.map((l) => `<option value="${l.id}" ${String(l.id) === current ? 'selected' : ''}>${escapeHtml(l.name)}</option>`).join('')
                + '<option value="any">Any available lawyer</option>';
            await picker.refresh({ keepSelection: true });
        })();
        reschedule.addEventListener('submit', (event) => {
            if (!$('#f-time', reschedule).value) { event.preventDefault(); window.alert('Pick a date and an open time first.'); }
        });
    }
}());
