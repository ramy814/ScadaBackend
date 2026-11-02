<template>
  <div class="app-wrapper">
    <!-- Top Navbar -->
    <nav class="top-navbar">
      <div class="nav-brand">
        <div class="title">لوحة تحكم المدير</div>
        <div class="sub">
          متابعة المركبات والسائقين وحالة مزامنة الرحلات
          <span v-if="$page.props.system?.last_update" class="update-indicator" title="تاريخ سحب أحدث كود على السيرفر (Production Deploy)">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.59-9.21L21.5 8"/></svg>
            آخر تحديث للسيرفر: {{ $page.props.system.last_update }}
          </span>
        </div>
        <div class="nav-links">
          <a href="/" class="nav-link-portal" style="color: #3b82f6; font-weight: bold; margin-left: 10px;">🏠 البوابة</a>
          <Link :href="$page.props.urls.dashboard" :class="{ active: isActive('Admin/Dashboard') }">الداشبورد</Link>
          <Link v-if="$page.props.auth?.can_create_manual_trips" :href="$page.props.urls.manualTrips">رحلاتي اليدوية</Link>
          <Link :href="$page.props.urls.driverApps" :class="{ active: isActive('Admin/DriverApps') }">استخدام التطبيق</Link>
          <Link :href="$page.props.urls.locations" :class="{ active: isActive('Admin/Locations') }">المواقع</Link>
          <Link :href="$page.props.urls.missionTypes" :class="{ active: isActive('Admin/MissionTypes') }">أنواع المهام</Link>
          <Link :href="$page.props.urls.reports" :class="{ active: isActive('Admin/Reports') }">التقارير</Link>
          <Link :href="$page.props.urls.vehicleClaims" :class="{ active: isActive('Admin/VehicleClaims') }">المطالبات المالية</Link>
        </div>
      </div>

      <div style="display: flex; align-items: center; gap: 12px;">
        <button @click="toggleTheme" class="btn-theme-toggle" :title="isDark ? 'التبديل للوضع النهاري' : 'التبديل للوضع الليلي'">
          <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="4.22" x2="19.78" y2="5.64"/></svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <a
          :href="$page.props.urls.logout"
          class="btn-logout"
          rel="external"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
            <polyline points="16 17 21 12 16 7"></polyline>
            <line x1="21" y1="12" x2="9" y2="12"></line>
          </svg>
          تسجيل خروج
        </a>
      </div>
    </nav>

    <!-- Flash Messages -->
    <div class="container">
      <transition name="fade">
        <div v-if="flash.ok" class="alert-success">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
          {{ flash.ok }}
        </div>
      </transition>

      <transition name="fade">
        <div v-if="flash.error" class="alert-error">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          {{ flash.error }}
        </div>
      </transition>

      <!-- Page Content -->
      <slot />
    </div>
  </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const isDark = ref(false);

onMounted(() => {
  const savedTheme = localStorage.getItem('theme');
  if (savedTheme) {
    isDark.value = savedTheme === 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
  } else {
    isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
  }
});

function toggleTheme() {
  isDark.value = !isDark.value;
  const theme = isDark.value ? 'dark' : 'light';
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem('theme', theme);
}

function isActive(componentName) {
  return page.component === componentName;
}
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap');

:root {
  --bg-color: var(--theme-bg);
  --surface: var(--theme-surface);
  --surface-strong: var(--theme-surface-strong);
  --input-surface: var(--theme-input);
  --stroke: var(--theme-border);
  --text-primary: var(--theme-text);
  --text-secondary: var(--theme-text-muted);
  --brand: #3b82f6;
  --brand-light: #eff6ff;
  --brand-hover: #2563eb;
  --ok: #10b981;
  --ok-bg: var(--theme-success-bg);
  --warn: #f59e0b;
  --warn-bg: var(--theme-warning-bg);
  --danger: #ef4444;
  --danger-bg: var(--theme-danger-bg);
  --radius-lg: 16px;
  --radius-md: 12px;
  --shadow-sm: var(--theme-shadow-sm);
  --shadow-md: var(--theme-shadow-md);
  --shadow-lg: var(--theme-shadow-lg);
  --shadow-glow: 0 0 25px rgba(59, 130, 246, 0.1);
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  --glass-blur: blur(24px);
}

@media (prefers-color-scheme: dark) {
  html:not([data-theme="light"]) {
    --brand-light: rgba(59, 130, 246, 0.15);
    --brand-hover: #60a5fa;
    --shadow-glow: 0 0 25px rgba(59, 130, 246, 0.2);
  }
}

html[data-theme="dark"] {
  --brand-light: rgba(59, 130, 246, 0.15);
  --brand-hover: #60a5fa;
  --shadow-glow: 0 0 25px rgba(59, 130, 246, 0.2);
}

* { box-sizing: border-box; }

html, body {
  margin: 0;
  font-family: 'Tajawal', sans-serif;
  background-color: var(--bg-color);
  background-image: 
    radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.05) 0px, transparent 50%),
    radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.05) 0px, transparent 50%);
  background-attachment: fixed;
  color: var(--text-primary);
  min-height: 100vh;
  direction: rtl;
  -webkit-font-smoothing: antialiased;
}

/* Navbar */
.top-navbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 32px;
  background: var(--surface);
  backdrop-filter: var(--glass-blur);
  -webkit-backdrop-filter: var(--glass-blur);
  border-bottom: 1px solid var(--stroke);
  position: sticky;
  top: 0;
  z-index: 50;
  box-shadow: var(--shadow-md);
}
.nav-brand .title { font-size: 22px; font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px; }
.nav-brand .sub { display: flex; align-items: center; font-size: 13px; color: var(--text-secondary); margin-top: 4px; font-weight: 500; }
.update-indicator {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: var(--ok-bg);
  color: var(--ok);
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  margin-right: 12px;
  border: 1px solid rgba(16,185,129,0.3);
  box-shadow: inset 0 0 10px rgba(16,185,129,0.1);
}
.nav-links { display: flex; gap: 8px; margin-top: 12px; }
.nav-links a {
  text-decoration: none;
  padding: 8px 16px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  color: var(--text-secondary);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}
.nav-links a:hover { background: var(--stroke); color: var(--text-primary); }
.nav-links a.active { background: linear-gradient(90deg, rgba(59, 130, 246, 0.15) 0%, transparent 100%); color: var(--brand-hover); box-shadow: inset 3px 0 0 var(--brand-hover); }
.nav-links a.nav-link-portal { color: var(--brand-hover) !important; font-weight: bold; margin-left: 10px; }
.nav-links a.nav-link-security { color: var(--warn); }
.nav-links a.nav-link-security:hover { background: rgba(245, 158, 11, 0.05); color: var(--warn); }
.nav-links a.nav-link-security.active { background: linear-gradient(90deg, rgba(245, 158, 11, 0.15) 0%, transparent 100%); color: var(--warn); box-shadow: inset 3px 0 0 var(--warn); }

.btn-logout {
  border: none;
  border-radius: var(--radius-md);
  padding: 10px 20px;
  background: var(--danger-bg);
  color: var(--danger);
  font-family: 'Tajawal', sans-serif;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  gap: 8px;
}
.btn-logout:hover { background: var(--danger); color: #fff; transform: translateY(-1px); }

.btn-theme-toggle {
  border: none;
  border-radius: var(--radius-md);
  padding: 10px;
  background: var(--surface);
  color: var(--text-primary);
  border: 1px solid var(--stroke);
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-theme-toggle:hover { background: var(--brand-light); color: var(--brand); transform: translateY(-1px); }

/* Container */
.container { max-width: 1400px; margin: 0 auto; padding: 32px 24px; }

/* Flash Alerts */
.alert-success, .alert-error {
  border-radius: var(--radius-md);
  padding: 16px 20px;
  margin-bottom: 24px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: var(--shadow-sm);
}
.alert-success { background: var(--ok-bg); border: 1px solid rgba(16,185,129,0.2); color: var(--ok); }
.alert-error { background: var(--danger-bg); border: 1px solid rgba(239,68,68,0.2); color: var(--danger); }

/* Table Card */
.table-card {
  background: var(--surface);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  border: 1px solid var(--stroke);
  overflow: hidden;
}
.table-header {
  padding: 20px 24px;
  border-bottom: 1px solid var(--stroke);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--bg-color);
}
.table-title { font-size: 18px; font-weight: 800; color: var(--text-primary); }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 900px; }
th {
  background: var(--surface);
  padding: 14px 16px;
  text-align: right;
  font-size: 13px;
  font-weight: 700;
  color: var(--text-secondary);
  border-bottom: 1px solid var(--stroke);
}
td {
  padding: 16px;
  border-bottom: 1px solid var(--stroke);
  font-size: 14px;
  vertical-align: middle;
  color: var(--text-primary);
  font-weight: 500;
}
tr:last-child td { border-bottom: none; }
tr:hover td { background: var(--stroke); }

/* Badges */
.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.badge.ok { background: var(--ok-bg); color: var(--ok); border: 1px solid rgba(16,185,129,0.3); }
.badge.warn { background: var(--warn-bg); color: var(--warn); border: 1px solid rgba(245,158,11,0.3); }
.badge.danger { background: var(--danger-bg); color: var(--danger); border: 1px solid rgba(239,68,68,0.3); }
.badge.neutral { background: var(--stroke); color: var(--text-primary); border: 1px solid var(--stroke); }

/* Action Buttons */
.actions-cell { display: flex; gap: 8px; flex-wrap: wrap; }
.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  transition: var(--transition);
  color: #fff;
  text-decoration: none;
}
.btn-icon svg { width: 16px; height: 16px; }
.btn-icon.view { background: var(--brand); }
.btn-icon.view:hover { background: var(--brand-hover); transform: translateY(-2px); box-shadow: 0 4px 6px rgba(59,130,246,0.2); }
.btn-icon.approve { background: var(--ok); }
.btn-icon.approve:hover { filter: brightness(0.9); transform: translateY(-2px); }
.btn-icon.reject { background: var(--danger); }
.btn-icon.reject:hover { filter: brightness(0.9); transform: translateY(-2px); }
.btn-icon.clarify { background: var(--warn); }
.btn-icon.clarify:hover { filter: brightness(0.9); transform: translateY(-2px); }

/* Form inputs */
.note-input {
  border: 1px solid var(--stroke);
  border-radius: 8px;
  padding: 6px 12px;
  font-size: 12px;
  font-family: 'Tajawal', sans-serif;
  width: 160px;
  transition: var(--transition);
  background: var(--surface);
  color: var(--text-primary);
}
.note-input:focus {
  outline: none;
  border-color: var(--brand);
  background: var(--bg-color);
  box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
}
.action-form { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }

/* Toolbar */
.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  flex-wrap: wrap;
  gap: 16px;
}
.tabs-group {
  display: inline-flex;
  background: var(--surface);
  backdrop-filter: blur(8px);
  padding: 6px;
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--stroke);
}
.tabs-group a {
  text-decoration: none;
  color: var(--text-secondary);
  padding: 8px 24px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  transition: var(--transition);
}
.tabs-group a:hover { color: var(--text-primary); }
.tabs-group a.active { background: var(--brand-light); color: var(--brand-hover); box-shadow: inset 0 0 10px rgba(59,130,246,0.2); }

.filters-group { display: inline-flex; gap: 8px; }
.filters-group a {
  text-decoration: none;
  background: var(--surface);
  backdrop-filter: blur(8px);
  border: 1px solid var(--stroke);
  color: var(--text-secondary);
  padding: 8px 16px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  transition: var(--transition);
  box-shadow: var(--shadow-sm);
}
.filters-group a:hover { border-color: var(--brand); color: var(--brand-hover); background: rgba(59, 130, 246, 0.05); }
.filters-group a.active { background: var(--brand); border-color: var(--brand); color: #fff; box-shadow: 0 4px 10px rgba(59,130,246,0.3); }

.btn-log-server {
  display: inline-flex;
  align-items: center;
  text-decoration: none;
  background: var(--surface);
  border: 1px solid var(--stroke);
  color: var(--text-secondary);
  padding: 8px 16px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 700;
  transition: var(--transition);
  box-shadow: var(--shadow-sm);
  cursor: pointer;
  backdrop-filter: blur(8px);
}
.btn-log-server:hover {
  background: var(--stroke);
  border-color: var(--brand);
  color: var(--text-primary);
  transform: translateY(-1px);
}

/* Fade transition */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* Footer */
.footer-note {
  margin-top: 24px;
  text-align: center;
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 500;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  margin-bottom: 32px;
}
.stat-group {
  background: var(--surface);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-radius: var(--radius-lg);
  padding: 24px;
  box-shadow: var(--shadow-md);
  border: 1px solid var(--stroke);
  position: relative;
  overflow: hidden;
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.stat-group:hover {
  transform: translateY(-4px) scale(1.02);
  box-shadow: var(--shadow-lg), var(--shadow-glow);
  border-color: rgba(255, 255, 255, 0.1);
}
.stat-group::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; height: 3px;
  opacity: 0.8;
  transition: opacity 0.3s ease, height 0.3s ease;
}
.stat-group:hover::before {
  opacity: 1;
  height: 4px;
}
.stat-group.primary::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); box-shadow: 0 0 15px rgba(59,130,246,0.5); }
.stat-group.success::before { background: linear-gradient(90deg, #10b981, #34d399); box-shadow: 0 0 15px rgba(16,185,129,0.5); }
.stat-group.warning::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); box-shadow: 0 0 15px rgba(245,158,11,0.5); }

.group-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.group-title svg { width: 20px; height: 20px; }
.primary .group-title svg { color: var(--brand); }
.success .group-title svg { color: var(--ok); }
.warning .group-title svg { color: var(--warn); }

.stat-items { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.stat-item { display: flex; flex-direction: column; gap: 4px; }
.stat-item .label { font-size: 12px; color: var(--text-secondary); font-weight: 600; }
.stat-item .value { font-size: 26px; font-weight: 800; color: var(--text-primary); text-shadow: 0 0 10px rgba(255, 255, 255, 0.2); transition: all 0.3s ease; }
.stat-group:hover .stat-item .value { color: var(--text-primary); text-shadow: 0 0 15px rgba(59, 130, 246, 0.2); transform: scale(1.05); transform-origin: right; }

@media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
  .stats-grid { grid-template-columns: 1fr; }
  .top-navbar { flex-direction: column; align-items: flex-start; gap: 16px; }
}
</style>
