/* =========================================
   ELEGANCE SALON MANAGEMENT SYSTEM JS
========================================= */

document.addEventListener('DOMContentLoaded', () => {

    // 1. Mobile Menu Toggle
    const burger = document.querySelector('.burger-menu');
    const navMenu = document.querySelector('.nav-menu');

    if (burger) {
        burger.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
    }

    // 2. Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(() => {
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    }

    // 3. Simple Form Validation (Client-Side)
    const forms = document.querySelectorAll('form.validate-form');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#dc3545';
                } else {
                    field.style.borderColor = '#ddd';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });

    // 4. Print Invoice Button specific to payments or reports
    const printBtn = document.querySelector('#print-btn');
    if (printBtn) {
        printBtn.addEventListener('click', () => {
            window.print();
        });
    }
});
