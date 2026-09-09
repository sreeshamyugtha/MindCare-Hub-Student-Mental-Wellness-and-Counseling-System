// js/main.js

document.addEventListener('DOMContentLoaded', function() {
    // 1. Responsive Sidebar Toggling
    const toggleBtn = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if(sidebar.classList.contains('active')) {
                toggleBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
            } else {
                toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
            }
        });
    }

    // 2. Interactive Mood Selection (For Mood Loggers)
    const moodOptions = document.querySelectorAll('.mood-option');
    const moodInput = document.getElementById('mood_score_input');
    
    if (moodOptions.length > 0 && moodInput) {
        moodOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                moodOptions.forEach(opt => opt.classList.remove('selected'));
                
                // Add selected class to the clicked option
                this.classList.add('selected');
                
                // Set hidden input value
                const moodValue = this.getAttribute('data-mood');
                moodInput.value = moodValue;
            });
        });
    }

    // 3. Auto-fade Alerts
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 5000);
    });

    // 4. Dark Theme Toggling
    const themeToggleBtn = document.getElementById('themeToggle');
    const sidebarThemeToggleBtn = document.getElementById('sidebarThemeToggle');
    
    function getTheme() {
        try {
            return localStorage.getItem('theme') || 'light';
        } catch (e) {
            return 'light';
        }
    }
    
    function setTheme(theme) {
        try {
            localStorage.setItem('theme', theme);
        } catch (e) {
            console.warn('LocalStorage is disabled or insecure:', e);
        }
        document.documentElement.setAttribute('data-bs-theme', theme);
        updateToggleIcons(theme);
    }
    
    function updateToggleIcons(theme) {
        const themeToggleIcon = document.getElementById('themeToggleIcon');
        const sidebarThemeToggleIcon = document.getElementById('sidebarThemeToggleIcon');
        
        const isDark = theme === 'dark';
        const iconClass = isDark ? 'bi-sun-fill' : 'bi-moon-fill';
        
        if (themeToggleIcon) {
            themeToggleIcon.className = `bi ${iconClass}`;
        }
        if (sidebarThemeToggleIcon) {
            sidebarThemeToggleIcon.className = `bi ${iconClass}`;
        }
    }
    
    // Initial icon state
    updateToggleIcons(getTheme());
    
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function() {
            const currentTheme = getTheme();
            setTheme(currentTheme === 'light' ? 'dark' : 'light');
        });
    }
    
    if (sidebarThemeToggleBtn) {
        sidebarThemeToggleBtn.addEventListener('click', function() {
            const currentTheme = getTheme();
            setTheme(currentTheme === 'light' ? 'dark' : 'light');
        });
    }

    // 5. Password Visibility Toggling
    document.addEventListener('click', function(e) {
        const toggle = e.target.closest('.toggle-password');
        if (toggle) {
            const targetId = toggle.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (input) {
                const icon = toggle.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    if (icon) {
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    }
                } else {
                    input.type = 'password';
                    if (icon) {
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    }
                }
            }
        }
    });
});

