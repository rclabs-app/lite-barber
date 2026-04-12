document.addEventListener('DOMContentLoaded', () => {
    // PREMIUM STATE
    let appointments = JSON.parse(localStorage.getItem('b_appointments')) || [];
    let queue = JSON.parse(localStorage.getItem('b_queue')) || [];
    let services = JSON.parse(localStorage.getItem('b_services')) || [
        { id: 1, name: 'CLÁSSICO', price: 45, icon: 'fa-cut' },
        { id: 2, name: 'BARBA REAL', price: 35, icon: 'fa-feather' },
        { id: 3, name: 'EXECUTIVO', price: 70, icon: 'fa-crown' }
    ];
    let storeConfig = JSON.parse(localStorage.getItem('b_config')) || {
        open: '08:00', close: '20:00', days: [1, 2, 3, 4, 5, 6]
    };
    let selectedTime = null;

    document.getElementById('booking-date').onchange = () => renderTimeGrid();
    
    // Global date wrapper click handler
    document.querySelectorAll('.date-selector-wrapper').forEach(wrapper => {
        wrapper.onclick = () => {
            const input = wrapper.querySelector('input[type="date"]');
            if (input.showPicker) input.showPicker();
            else input.focus();
        };
    });

    // NAVIGATION SYSTEM
    const views = document.querySelectorAll('.view');
    const toast = document.getElementById('toast');

    const showView = (id) => {
        views.forEach(v => v.classList.remove('active'));
        const target = document.getElementById(id);
        target.classList.add('active');
        window.scrollTo(0, 0);

        // Sub-view updates
        if (id === 'booking') { 
            const bDate = document.getElementById('booking-date');
            if (!bDate.value) bDate.value = new Date().toISOString().split('T')[0];
            bDate.min = new Date().toISOString().split('T')[0]; // Prevent past dates
            renderBookingServices(); 
            renderTimeGrid(); 
        }
        if (id === 'dashboard') { showDashboardTab('admin-agenda'); renderDailyAppointments(); updateAdminSummary(); }
        if (id === 'user-bookings') renderUserBookings();
        if (id === 'queue') renderQueue();
    };

    // DASHBOARD TABS
    const showDashboardTab = (tabId) => {
        document.querySelectorAll('.dash-content').forEach(c => c.classList.add('hidden'));
        document.querySelectorAll('.pro-tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tabId).classList.remove('hidden');
        document.querySelector(`[data-target="${tabId}"]`).classList.add('active');

        if (tabId === 'admin-services') renderServicesAdmin();
        if (tabId === 'admin-config') renderStoreConfig();
    };

    document.querySelectorAll('.pro-tab-btn').forEach(btn => {
        btn.onclick = () => showDashboardTab(btn.dataset.target);
    });

    // BINDINGS
    document.getElementById('btn-goto-booking').onclick = () => showView('booking');
    document.getElementById('btn-goto-queue').onclick = () => showView('queue');
    document.getElementById('btn-goto-dashboard').onclick = () => showView('login');
    document.getElementById('btn-goto-my-bookings').onclick = () => showView('user-bookings');
    document.querySelectorAll('.btn-back-luxury').forEach(btn => btn.onclick = () => showView('home'));

    // LOGIN LOGIC
    document.getElementById('form-login').onsubmit = (e) => {
        e.preventDefault();
        const pass = document.getElementById('admin-pass').value;
        if (pass === '1234') { // Simple password for now
            showView('dashboard');
            document.getElementById('admin-pass').value = '';
            showToast('ACESSO AUTORIZADO');
        } else {
            showToast('CÓDIGO INCORRETO');
        }
    };

    // NOTIFICATIONS
    const showToast = (msg) => {
        toast.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3500);
    };

    // INPUT MASKING (BRAZILIAN PHONE)
    const maskPhone = (input) => {
        let v = input.value.replace(/\D/g, '');
        if (v.length > 11) v = v.slice(0, 11);
        if (v.length > 10) input.value = `(${v.slice(0, 2)}) ${v.slice(2, 7)}-${v.slice(7)}`;
        else if (v.length > 6) input.value = `(${v.slice(0, 2)}) ${v.slice(2, 6)}-${v.slice(6)}`;
        else if (v.length > 2) input.value = `(${v.slice(0, 2)}) ${v.slice(2)}`;
        else if (v.length > 0) input.value = `(${v.slice(0, 2)}`;
    };
    document.getElementById('phone').oninput = (e) => maskPhone(e.target);
    document.getElementById('q-phone').oninput = (e) => maskPhone(e.target);

    // BOOKING RENDERERS
    const renderBookingServices = () => {
        const container = document.getElementById('service-options');
        container.innerHTML = '';
        services.forEach((s, i) => {
            const lbl = document.createElement('label');
            lbl.className = 'service-card';
            lbl.style.setProperty('--delay', `${0.1 * i}s`);
            lbl.innerHTML = `
                <input type="radio" name="srv" value="${s.name}" data-price="${s.price}" ${i === 0 ? 'checked' : ''}>
                <div class="card-content">
                    <strong>${s.name}</strong>
                    <span>VALOR: R$ ${s.price}</span>
                </div>
            `;
            container.appendChild(lbl);
        });
    };

    const renderTimeGrid = () => {
        const grid = document.getElementById('time-grid');
        grid.innerHTML = '';
        selectedTime = null;
        document.getElementById('selected-time').value = '';

        const start = parseInt(storeConfig.open.split(':')[0]);
        const end = parseInt(storeConfig.close.split(':')[0]);
        const selectedDate = document.getElementById('booking-date').value || new Date().toISOString().split('T')[0];
        const occupied = appointments.filter(a => a.date === selectedDate).map(a => a.time);

        for (let h = start; h < end; h++) {
            const t = `${h.toString().padStart(2, '0')}:00`;
            const slot = document.createElement('div');
            slot.className = 'time-slot';
            slot.textContent = t;
            if (occupied.includes(t)) slot.classList.add('occupied');
            else {
                slot.onclick = () => {
                    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                    slot.classList.add('selected');
                    selectedTime = t;
                    document.getElementById('selected-time').value = t;
                };
            }
            grid.appendChild(slot);
        }
    };

    // FORM SUBMISSION
    document.getElementById('form-booking').onsubmit = (e) => {
        e.preventDefault();
        const time = document.getElementById('selected-time').value;
        if (!time) return showToast('POR FAVOR, SELECIONE UM HORÁRIO.');

        const srv = document.querySelector('input[name="srv"]:checked');
        const appointment = {
            id: Date.now(),
            date: document.getElementById('booking-date').value,
            time: time,
            name: document.getElementById('name').value,
            phone: document.getElementById('phone').value,
            service: srv.value,
            price: srv.dataset.price,
            note: document.getElementById('note').value
        };

        appointments.push(appointment);
        localStorage.setItem('b_appointments', JSON.stringify(appointments));

        const msg = encodeURIComponent(`ESTILO AGENDADO:\n👤 ${appointment.name}\n⏰ ${appointment.time}\n✂️ ${appointment.service}\n🏛️ Barbearia Elite`);
        showToast('HORÁRIO CONFIRMADO.');
        setTimeout(() => {
            window.open(`https://wa.me/55${appointment.phone.replace(/\D/g, '')}?text=${msg}`, '_blank');
            showView('home');
            document.getElementById('form-booking').reset();
        }, 1500);
    };

    // ADMIN RENDERERS
    const updateAdminSummary = () => {
        const today = new Date().toISOString().split('T')[0];
        const dayApps = appointments.filter(a => a.date === today);
        const revenue = dayApps.reduce((sum, a) => sum + parseFloat(a.price), 0);
        
        document.getElementById('sum-total').textContent = dayApps.length;
        document.getElementById('sum-revenue').textContent = `R$ ${revenue}`;
        document.getElementById('sum-queue').textContent = queue.length;
    };

    const renderDailyAppointments = () => {
        const list = document.getElementById('daily-appointments-list');
        list.innerHTML = '';
        const day = document.getElementById('admin-date-picker').value || new Date().toISOString().split('T')[0];
        const filtered = appointments.filter(a => a.date === day).sort((a,b) => a.time.localeCompare(b.time));

        if (filtered.length === 0) {
            list.innerHTML = '<p class="empty-list">SEM EXPERIÊNCIAS HOJE.</p>';
            return;
        }

        filtered.forEach(a => {
            const div = document.createElement('div');
            div.className = 'pro-agenda-item';
            div.innerHTML = `
                <div class="time-box"><span class="hour">${a.time}</span></div>
                <div class="client-box">
                    <strong>${a.name}</strong>
                    <span>${a.service} • R$ ${a.price}</span>
                </div>
                <button class="btn-whatsapp-luxury-mini" onclick="window.open('https://wa.me/55${a.phone.replace(/\D/g, '')}', '_blank')">
                    <i class="fab fa-whatsapp"></i>
                </button>
            `;
            list.appendChild(div);
        });
    };

    const renderServicesAdmin = () => {
        const list = document.getElementById('services-manage-list');
        list.innerHTML = '';
        services.forEach(s => {
            const div = document.createElement('div');
            div.className = 'luxury-menu-item';
            div.style.padding = '20px';
            div.style.borderBottom = '1px solid #111';
            div.style.display = 'flex';
            div.style.justifyContent = 'space-between';
            div.innerHTML = `
                <span>${s.name} - R$ ${s.price}</span>
                <button onclick="deleteService(${s.id})" style="background:none; border:none; color:var(--danger); cursor:pointer;">REMOVER</button>
            `;
            list.appendChild(div);
        });
    };

    document.getElementById('form-manage-service').onsubmit = (e) => {
        e.preventDefault();
        const s = {
            id: Date.now(),
            name: document.getElementById('s-name').value.toUpperCase(),
            price: document.getElementById('s-price').value
        };
        services.push(s);
        localStorage.setItem('b_services', JSON.stringify(services));
        document.getElementById('s-name').value = '';
        document.getElementById('s-price').value = '';
        renderServicesAdmin();
    };

    window.deleteService = (id) => {
        services = services.filter(s => s.id !== id);
        localStorage.setItem('b_services', JSON.stringify(services));
        renderServicesAdmin();
    };

    const renderStoreConfig = () => {
        document.getElementById('store-open').value = storeConfig.open;
        document.getElementById('store-close').value = storeConfig.close;
        document.querySelectorAll('.day-opt input').forEach(input => {
            input.checked = storeConfig.days.includes(parseInt(input.value));
        });
    };

    document.getElementById('btn-save-config').onclick = () => {
        const days = Array.from(document.querySelectorAll('.day-opt input:checked')).map(i => parseInt(i.value));
        storeConfig = {
            open: document.getElementById('store-open').value,
            close: document.getElementById('store-close').value,
            days: days
        };
        localStorage.setItem('b_config', JSON.stringify(storeConfig));
        showToast('SISTEMA ATUALIZADO.');
    };

    // USER DASHBOARD
    const renderUserBookings = () => {
        const list = document.getElementById('user-booking-list');
        list.innerHTML = '';
        if (appointments.length === 0) { list.innerHTML = '<p class="empty-list">SEM RESERVAS ATIVAS.</p>'; return; }
        
        appointments.sort((a,b) => b.id - a.id).forEach(a => {
            const div = document.createElement('div');
            div.className = 'pro-agenda-item';
            div.innerHTML = `
                <div class="time-box"><span class="hour" style="font-size:1rem">${a.date.split('-').reverse().join('/')}</span><br><span>${a.time}</span></div>
                <div class="client-box"><strong>${a.service}</strong><span>R$ ${a.price}</span></div>
                <button onclick="cancelBooking(${a.id})" style="background:none; border:none; color:var(--danger); cursor:pointer;">X</button>
            `;
            list.appendChild(div);
        });
    };

    window.cancelBooking = (id) => {
        if (!confirm('CANCELAR RESERVA?')) return;
        appointments = appointments.filter(a => a.id !== id);
        localStorage.setItem('b_appointments', JSON.stringify(appointments));
        renderUserBookings();
        showToast('CANCELADO.');
    };

    // QUEUE
    const renderQueue = () => {
        const list = document.getElementById('queue-list');
        if (!list) return;
        list.innerHTML = '';
        if (queue.length === 0) {
            list.innerHTML = '<p class="empty-list" style="text-align:center; padding:20px; color:var(--text-dim);">SEM ESPERA NO MOMENTO.</p>';
            return;
        }
        queue.slice(0, 5).forEach((q, idx) => {
            const div = document.createElement('div');
            div.className = 'lineup-item';
            div.innerHTML = `
                <span class="pos">${idx + 1}º</span>
                <span class="name">${q.name.toUpperCase()}</span>
                <span class="status">${idx === 0 ? 'NA CADEIRA' : 'AGUARDANDO'}</span>
            `;
            list.appendChild(div);
        });
    };

    document.getElementById('form-queue').onsubmit = (e) => {
        e.preventDefault();
        const q = { name: document.getElementById('q-name').value };
        queue.push(q);
        localStorage.setItem('b_queue', JSON.stringify(queue));
        
        document.getElementById('queue-join').classList.add('hidden');
        document.getElementById('queue-status').classList.remove('hidden');
        
        const pos = queue.length;
        document.getElementById('queue-pos').textContent = pos;
        document.getElementById('queue-time').textContent = pos * 25;
        
        renderQueue();
        showToast('POSIÇÃO RESERVADA.');
    };
});
