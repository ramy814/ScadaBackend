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
          <Link :href="$page.props.urls.dashboard" :class="{ active: isActive('Admin/Dashboard') }">الداشبورد</Link>
          <Link :href="$page.props.urls.locations" :class="{ active: isActive('Admin/Locations') }">المواقع</Link>
          <Link :href="$page.props.urls.missionTypes" :class="{ active: isActive('Admin/MissionTypes') }">أنواع المهام</Link>
          <Link :href="$page.props.urls.reports" :class="{ active: isActive('Admin/Reports') }">التقارير</Link>
          <Link v-if="$page.props.urls.allowlist" :href="$page.props.urls.allowlist" :class="{ active: isActive('Admin/Allowlist') }" class="nav-link-security">🔒 الصلاحيات</Link>
        </div>
      </div>

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
import { computed } from 'vue';

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

function isActive(componentName) {
  return page.component === componentName;
}
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap');

:root {
  --bg-color: #f0f4f8;
  --surface: #ffffff;
  --stroke: #e2e8f0;
  --text-primary: #1e293b;
  --text-secondary: #64748b;
  --brand: #3b82f6;
  --brand-light: #eff6ff;
  --brand-hover: #2563eb;
  --ok: #10b981;
  --ok-bg: #d1fae5;
  --warn: #f59e0b;
  --warn-bg: #fef3c7;
  --danger: #ef4444;
  --danger-bg: #fee2e2;
  --radius-lg: 16px;
  --radius-md: 12px;
  --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
  --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
  --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.025);
  --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

* { box-sizing: border-box; }

html, body {
  margin: 0;
  font-family: 'Tajawal', sans-serif;
  background-color: var(--bg-color);
  background-image: radial-gradient(circle at top right, #e0eaff 0%, #f0f4f8 20%);
  color: var(--text-primary);
  min-height: 100vh;
}

/* Navbar */
.top-navbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 32px;
  background: rgba(255,255,255,0.85);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(226,232,240,0.6);
  position: sticky;
  top: 0;
  z-index: 50;
  box-shadow: var(--shadow-sm);
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
  border: 1px solid rgba(16,185,129,0.2);
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
}
.nav-links a:hover { background: var(--brand-light); color: var(--brand); }
.nav-links a.active { background: var(--brand); color: #fff; box-shadow: 0 4px 6px rgba(59,130,246,0.2); }
.nav-links a.nav-link-security { color: #d97706; }
.nav-links a.nav-link-security:hover { background: #fef3c7; color: #d97706; }
.nav-links a.nav-link-security.active { background: #d97706; color: #fff; box-shadow: 0 4px 6px rgba(217,119,6,0.2); }

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
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  border: 1px solid rgba(255,255,255,0.8);
  overflow: hidden;
}
.table-header {
  padding: 20px 24px;
  border-bottom: 1px solid var(--stroke);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(255,255,255,0.5);
}
.table-title { font-size: 18px; font-weight: 800; color: var(--text-primary); }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 900px; }
th {
  background: #f8fafc;
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
tr:hover td { background: rgba(248,250,252,0.5); }

/* Badges */
.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}
.badge.ok { background: var(--ok-bg); color: var(--ok); }
.badge.warn { background: var(--warn-bg); color: var(--warn); }
.badge.danger { background: var(--danger-bg); color: var(--danger); }
.badge.neutral { background: #f1f5f9; color: var(--text-secondary); }

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
  background: #f8fafc;
}
.note-input:focus {
  outline: none;
  border-color: var(--brand);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
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
.tabs-group a.active { background: var(--brand-light); color: var(--brand); }

.filters-group { display: inline-flex; gap: 8px; }
.filters-group a {
  text-decoration: none;
  background: var(--surface);
  border: 1px solid var(--stroke);
  color: var(--text-secondary);
  padding: 8px 16px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  transition: var(--transition);
  box-shadow: var(--shadow-sm);
}
.filters-group a:hover { border-color: var(--brand); color: var(--brand); }
.filters-group a.active { background: var(--text-primary); border-color: var(--text-primary); color: #fff; }

.btn-log-server {
  display: inline-flex;
  align-items: center;
  text-decoration: none;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  color: #475569;
  padding: 8px 16px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 700;
  transition: var(--transition);
  box-shadow: var(--shadow-sm);
  cursor: pointer;
}
.btn-log-server:hover {
  background: #f1f5f9;
  border-color: #94a3b8;
  color: #0f172a;
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
  border-radius: var(--radius-lg);
  padding: 24px;
  box-shadow: var(--shadow-md);
  border: 1px solid rgba(255,255,255,0.8);
  position: relative;
  overflow: hidden;
}
.stat-group::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; height: 4px;
}
.stat-group.primary::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.stat-group.success::before { background: linear-gradient(90deg, #10b981, #34d399); }
.stat-group.warning::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }

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
.stat-item .value { font-size: 24px; font-weight: 800; color: var(--text-primary); }

@media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
  .stats-grid { grid-template-columns: 1fr; }
  .top-navbar { flex-direction: column; align-items: flex-start; gap: 16px; }
}
</style>
