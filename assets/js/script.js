document.addEventListener('DOMContentLoaded', () => {
    const burger = document.querySelector('.burger-menu');
    const navMenu = document.querySelector('.nav-menu');

    if (burger && navMenu) {
        burger.addEventListener('click', () => {
            const isOpen = navMenu.classList.toggle('active');
            burger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    const alerts = document.querySelectorAll('.alert');
    if (alerts.length) {
        setTimeout(() => {
            alerts.forEach((alert) => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 4500);
    }

    document.querySelectorAll('form.validate-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            let isValid = true;
            form.querySelectorAll('[required]').forEach((field) => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#c53030';
                    isValid = false;
                } else {
                    field.style.borderColor = '';
                }
            });

            if (!isValid) {
                event.preventDefault();
                alert('Please complete the required fields.');
            }
        });
    });

    const bookingForm = document.querySelector('[data-booking-form]');
    if (bookingForm) {
        const serviceSelect = bookingForm.querySelector('#service_id');
        const stylistSelect = bookingForm.querySelector('#stylist_id');
        const dateInput = bookingForm.querySelector('#appointment_date');
        const timeInput = bookingForm.querySelector('#appointment_time');
        const slotContainer = bookingForm.querySelector('[data-slot-grid]');
        const slotMessage = bookingForm.querySelector('[data-slot-message]');
        const stylistOptions = Array.from(stylistSelect.querySelectorAll('option[data-services]'));
        const blockedSlots = JSON.parse(bookingForm.dataset.blockedSlots || '{}');
        const availabilityMap = JSON.parse(bookingForm.dataset.availability || '{}');
        const baseSlots = JSON.parse(bookingForm.dataset.baseSlots || '[]');
        const summaryNodes = {
            service: bookingForm.querySelector('[data-summary-service]'),
            stylist: bookingForm.querySelector('[data-summary-stylist]'),
            date: bookingForm.querySelector('[data-summary-date]'),
            time: bookingForm.querySelector('[data-summary-time]')
        };

        const formatDate = (value) => {
            if (!value) return 'Select a date';
            return new Date(`${value}T00:00:00`).toLocaleDateString(undefined, {
                weekday: 'short',
                month: 'short',
                day: 'numeric'
            });
        };

        const serviceMatchesStylist = (option, serviceId) => {
            if (!serviceId) return true;
            return (option.dataset.services || '').split(',').includes(serviceId);
        };

        const stylistAvailableOn = (stylistId, dateValue, slot) => {
            const day = new Date(`${dateValue}T00:00:00`).getDay() || 7;
            const rule = availabilityMap[stylistId]?.[day];
            if (!rule || !rule.available) return false;
            return slot >= rule.start && slot < rule.end;
        };

        const refreshStylists = () => {
            const serviceId = serviceSelect.value;
            stylistOptions.forEach((option) => {
                const show = serviceMatchesStylist(option, serviceId);
                option.hidden = !show;
            });

            if (stylistSelect.selectedOptions[0]?.hidden) {
                stylistSelect.value = '';
            }
            updateSummary();
            renderSlots();
        };

        const updateSummary = () => {
            if (summaryNodes.service) {
                summaryNodes.service.textContent = serviceSelect.selectedOptions[0]?.textContent || 'Choose a service';
            }
            if (summaryNodes.stylist) {
                summaryNodes.stylist.textContent = stylistSelect.selectedOptions[0]?.textContent || 'Choose a stylist';
            }
            if (summaryNodes.date) {
                summaryNodes.date.textContent = formatDate(dateInput.value);
            }
            if (summaryNodes.time) {
                summaryNodes.time.textContent = timeInput.value || 'Choose an available slot';
            }
        };

        const renderSlots = () => {
            slotContainer.innerHTML = '';
            timeInput.value = '';
            updateSummary();

            if (!stylistSelect.value || !dateInput.value) {
                slotMessage.textContent = 'Select a stylist and date to view available times.';
                return;
            }

            const stylistId = stylistSelect.value;
            const blocked = blockedSlots[stylistId]?.[dateInput.value] || [];
            let hasAvailableSlot = false;

            baseSlots.forEach((slot) => {
                let status = 'available';
                if (!stylistAvailableOn(stylistId, dateInput.value, slot)) {
                    status = 'unavailable';
                } else if (blocked.includes(slot)) {
                    status = 'booked';
                }

                const button = document.createElement('button');
                button.type = 'button';
                button.className = `slot-btn ${status}`;
                button.textContent = slot;
                button.disabled = status !== 'available';
                button.dataset.slot = slot;

                if (status === 'available') {
                    hasAvailableSlot = true;
                    button.addEventListener('click', () => {
                        slotContainer.querySelectorAll('.slot-btn.active').forEach((node) => node.classList.remove('active'));
                        button.classList.add('active');
                        timeInput.value = slot;
                        updateSummary();
                    });
                }

                slotContainer.appendChild(button);
            });

            slotMessage.textContent = hasAvailableSlot
                ? 'Choose one of the highlighted time slots below.'
                : 'No available slots for this date. Please select another day or stylist.';
        };

        [serviceSelect, stylistSelect, dateInput].forEach((field) => {
            field?.addEventListener('change', () => {
                if (field === serviceSelect) {
                    refreshStylists();
                } else {
                    renderSlots();
                }
                updateSummary();
            });
        });

        refreshStylists();
    }

    const printBtn = document.querySelector('#print-btn');
    if (printBtn) {
        printBtn.addEventListener('click', () => window.print());
    }
});
