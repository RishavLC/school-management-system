document.addEventListener('DOMContentLoaded', function () {
    // Mobile sidebar toggle
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => sidebar.classList.toggle('show'));
    }

    // Confirm before any destructive action (delete buttons etc.)
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // Attendance: "Mark All Present" shortcut
    const markAllBtn = document.getElementById('markAllPresent');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            document.querySelectorAll('.attendance-row').forEach(function (row) {
                const presentRadio = row.querySelector('input[value="present"]');
                if (presentRadio) presentRadio.checked = true;
            });
        });
    }

    // Auto-dismiss alerts after 6s
    document.querySelectorAll('.alert').forEach(function (alertEl) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
            bsAlert.close();
        }, 6000);
    });
});
