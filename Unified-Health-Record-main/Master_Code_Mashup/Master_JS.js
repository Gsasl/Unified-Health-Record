
/* ==========================================
   ORIGINAL FILE: js/app.js
   ========================================== */

/* app.js — HRec Micro-interactions */

document.addEventListener('DOMContentLoaded', () => {
  // Sidebar mobile toggle
  const toggle = document.querySelector('.mobile-toggle');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');

  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    if (overlay) overlay.addEventListener('click', () => sidebar.classList.remove('open'));
  }

  // Stagger card animations
  document.querySelectorAll('.card, .stat-card, .alert, .brand-card').forEach((el, i) => {
    el.style.animationDelay = `${i * 0.05}s`;
  });

  // Confirm dialogs for destructive actions
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', e => {
      if (!confirm(btn.dataset.confirm)) e.preventDefault();
    });
  });

  // Auto-dismiss toasts
  document.querySelectorAll('.toast').forEach(t => {
    setTimeout(() => t.remove(), 4000);
  });

  // BMI category color coding
  document.querySelectorAll('[data-bmi]').forEach(el => {
    const bmi = parseFloat(el.dataset.bmi);
    if (bmi < 18.5) el.classList.add('badge-bmi-underweight');
    else if (bmi < 25) el.classList.add('badge-bmi-normal');
    else if (bmi < 30) el.classList.add('badge-bmi-overweight');
    else el.classList.add('badge-bmi-obese');
  });
});

// Show toast notification
function showToast(message, type = 'info') {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  const icons = { success: 'check_circle', error: 'error', info: 'info', warning: 'warning' };
  const colors = { success: '#10b981', error: '#ef4444', info: '#3b82f6', warning: '#f59e0b' };
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.innerHTML = `<span class="material-symbols-outlined" style="color:${colors[type]}">${icons[type]}</span>${message}`;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}


