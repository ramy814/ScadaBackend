<template>
  <AppLayout>
    <!-- Toolbar: Period + Response Status Filters -->
    <div class="toolbar">
      <div class="tabs-group">
        <Link
          v-for="p in periods"
          :key="p.value"
          :href="dashboardUrl({ period: p.value })"
          :class="{ active: period === p.value }"
        >{{ p.label }}</Link>
      </div>
      <div style="display: flex; gap: 12px; align-items: center;">
        <form @submit.prevent="submitSearch" style="display:flex; gap:8px; align-items:center;">
          <input
            v-model="searchTerm"
            type="search"
            placeholder="ابحث برقم الرحلة أو المركبة أو اسم السائق"
            style="min-width:320px; height:38px; border:1px solid var(--stroke); border-radius:8px; padding:0 12px; color:var(--text-primary); background:var(--card-bg);"
          />
          <button type="submit" class="btn-icon view" title="بحث" style="width:38px; height:38px;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </button>
          <Link
            v-if="search"
            :href="dashboardUrl({ search: '' })"
            class="btn-icon reject"
            title="مسح البحث"
            style="width:38px; height:38px;"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </Link>
        </form>

        <div class="filters-group">
          <Link
            v-for="f in responseFilters"
            :key="f.value"
            :href="dashboardUrl({ response_status: f.value })"
            :class="{ active: responseStatus === f.value }"
          >{{ f.label }}</Link>
        </div>

        <a
          v-if="$page.props.auth.can_view_logs"
          :href="$page.props.urls.logs"
          target="_blank"
          class="btn-log-server"
          title="عرض سجل الأخطاء على السيرفر"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: 6px;">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          سجل الأخطاء (Log)
        </a>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <!-- Performance -->
      <div class="stat-group primary">
        <div class="group-title">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
          </svg>
          ملخص الأداء
        </div>
        <div class="stat-items">
          <div class="stat-item"><div class="label">رحلات اليوم</div><div class="value">{{ todayTrips }}</div></div>
          <div class="stat-item"><div class="label">المركبات الفعالة</div><div class="value">{{ activeVehicles }}</div></div>
          <div class="stat-item"><div class="label">السائقون الفعالون</div><div class="value">{{ activeDrivers }}</div></div>
        </div>
      </div>

      <!-- Approvals -->
      <div class="stat-group success">
        <div class="group-title">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
          حالات الاعتماد
        </div>
        <div class="stat-items">
          <div class="stat-item"><div class="label">المؤكدة</div><div class="value">{{ confirmedTrips }}</div></div>
          <div class="stat-item"><div class="label">المعتمدة إداريًا</div><div class="value">{{ approvedTrips }}</div></div>
          <div class="stat-item"><div class="label">المرفوضة</div><div class="value">{{ rejectedTrips }}</div></div>
        </div>
      </div>

      <!-- Clarifications -->
      <div class="stat-group warning">
        <div class="group-title">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          حالات التوضيح
        </div>
        <div class="stat-items">
          <div class="stat-item"><div class="label">إجمالي التوضيحات</div><div class="value">{{ clarificationTrips }}</div></div>
          <div class="stat-item"><div class="label">بانتظار رد</div><div class="value">{{ clarificationPendingResponse }}</div></div>
          <div class="stat-item"><div class="label">تم الرد عليها</div><div class="value">{{ clarificationResponded }}</div></div>
        </div>
      </div>
    </div>

    <!-- Trips Table -->
    <div class="table-card">
      <div class="table-header">
        <div class="table-title">رحلات الفترة المحددة</div>
        <div v-if="recentTrips.total" style="color:var(--text-secondary); font-size:13px; font-weight:600;">
          عرض {{ recentTrips.from }} - {{ recentTrips.to }} من {{ recentTrips.total }}
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>رقم الرحلة</th>
              <th>المركبة / السائق</th>
              <th>المسار (انطلاق ← وجهة)</th>
              <th>النقاط</th>
              <th>الحالة الإدارية</th>
              <th>رد السائق</th>
              <th>الوقت</th>
              <th>إجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="recentTrips.data.length === 0">
              <td colspan="8" style="text-align:center; padding:48px; color:var(--text-secondary);">
                لا توجد رحلات ضمن الفترة المحددة.
              </td>
            </tr>
            <tr v-for="trip in recentTrips.data" :key="trip.id">
              <td style="font-weight:800;" dir="ltr">#{{ trip.id }}</td>
              <td>
                <div style="font-weight:700;">{{ trip.vehicle?.plate_number ?? '-' }}</div>
                <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">{{ trip.driver?.name ?? '-' }}</div>
              </td>
              <td>
                <div style="font-weight:600;">{{ trip.origin_text }}</div>
                <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">إلى: {{ trip.destination_text }}</div>
              </td>
              <td>
                <span class="badge neutral">{{ trip.points_received }} / {{ trip.points_expected }}</span>
              </td>
              <td>
                <span :class="['badge', statusBadge(trip.status)]">{{ statusLabel(trip.status) }}</span>
                <span style="margin:0 4px; color:var(--stroke);">|</span>
                <span :class="['badge', reviewBadge(trip.review_status)]">{{ reviewLabel(trip.review_status) }}</span>
              </td>
              <td>
                <template v-if="trip.clarification_response">
                  <div style="max-width:200px; white-space:normal; line-height:1.4; font-size:13px;">{{ trip.clarification_response }}</div>
                  <div style="color:var(--text-secondary); font-size:11px; margin-top:4px;">{{ formatDate(trip.clarification_responded_at) }}</div>
                </template>
                <span v-else-if="trip.review_status === 'needs_clarification'" class="badge warn">بانتظار الرد</span>
                <span v-else style="color:var(--stroke);">-</span>
              </td>
              <td style="color:var(--text-secondary); font-size:13px;">{{ formatDate(trip.started_at) }}</td>
              <td>
                <div style="display:flex; flex-direction:column; gap:8px;">
                  <div class="actions-cell">
                    <!-- View -->
                    <Link :href="`${$page.props.urls.trips_base}/${trip.id}`" class="btn-icon view" title="التفاصيل">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </Link>

                    <template v-if="!['approved','rejected'].includes(trip.review_status)">
                      <!-- Approve -->
                      <button class="btn-icon approve" title="اعتماد" @click="submitReview('approve', trip.id)">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                      </button>
                      <!-- Reject -->
                      <button class="btn-icon reject" title="رفض" @click="confirmReject(trip.id)">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                      </button>
                    </template>
                  </div>

                  <!-- Clarify -->
                  <template v-if="!['approved','rejected'].includes(trip.review_status)">
                    <div v-if="trip.review_status === 'needs_clarification'" style="font-size:12px; background:var(--warn-bg); border:1px solid rgba(245,158,11,0.25); border-radius:8px; padding:6px 10px; max-width:220px;">
                      <div style="font-weight:700; color:var(--warn); margin-bottom:2px;">💬 توضيح مُرسل</div>
                      <div style="color:var(--text-primary); white-space:normal; line-height:1.4;">{{ trip.review_note }}</div>
                      <div style="color:var(--text-secondary); font-size:11px; margin-top:3px;">{{ formatDate(trip.reviewed_at) }}</div>
                    </div>
                    <form v-else class="action-form" @submit.prevent="submitClarify(trip.id, clarifyNotes[trip.id])">
                      <input class="note-input" v-model="clarifyNotes[trip.id]" placeholder="سؤال التوضيح للسائق..." required />
                      <button type="submit" class="btn-icon clarify" title="طلب توضيح">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                      </button>
                    </form>
                  </template>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="recentTrips.last_page > 1" style="padding:16px 24px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
        <Link
          v-for="link in recentTrips.links"
          :key="link.label"
          :href="link.url ?? '#'"
          :class="['page-link', { active: link.active, disabled: !link.url }]"
          v-html="link.label"
        />
      </div>
    </div>

    <div class="footer-note">
      آخر تحديث للبيانات: <span dir="ltr">{{ now }}</span>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();

const props = defineProps({
  todayTrips: Number,
  confirmedTrips: Number,
  activeVehicles: Number,
  activeDrivers: Number,
  approvedTrips: Number,
  rejectedTrips: Number,
  clarificationTrips: Number,
  clarificationPendingResponse: Number,
  clarificationResponded: Number,
  period: String,
  responseStatus: String,
  search: String,
  recentTrips: Object,
});

const now = new Date().toLocaleString('ar-EG', { hour12: false });
const clarifyNotes = ref({});
const searchTerm = ref(props.search ?? '');

const periods = [
  { value: 'daily', label: 'يومي' },
  { value: 'weekly', label: 'أسبوعي' },
  { value: 'monthly', label: 'شهري' },
];
const responseFilters = [
  { value: 'all', label: 'كل الحالات' },
  { value: 'pending', label: 'بانتظار الرد' },
  { value: 'responded', label: 'تم الرد' },
];

function formatDate(dateStr) {
  if (!dateStr) return '-';
  return new Date(dateStr).toLocaleString('sv-SE', { hour12: false }).replace('T', ' ').slice(0, 16);
}

function statusBadge(s) {
  return s === 'confirmed' ? 'ok' : s === 'uploaded' ? 'warn' : 'danger';
}
function statusLabel(s) {
  return s === 'confirmed' ? 'مؤكدة' : s === 'uploaded' ? 'مرفوعة' : s;
}
function reviewBadge(s) {
  return s === 'approved' ? 'ok' : s === 'rejected' ? 'danger' : s === 'needs_clarification' ? 'warn' : 'neutral';
}
function reviewLabel(s) {
  return s === 'approved' ? 'مُعتمدة' : s === 'rejected' ? 'مرفوضة' : s === 'needs_clarification' ? 'توضيح' : 'قيد المراجعة';
}

function dashboardUrl(overrides = {}) {
  const params = new URLSearchParams({
    period: props.period ?? 'daily',
    response_status: props.responseStatus ?? 'all',
  });
  const nextSearch = Object.prototype.hasOwnProperty.call(overrides, 'search')
    ? overrides.search
    : props.search;

  Object.entries(overrides).forEach(([key, value]) => {
    if (key !== 'search') params.set(key, value);
  });

  if (nextSearch) {
    params.set('search', nextSearch);
  }

  return `${page.props.urls.dashboard}?${params.toString()}`;
}

function submitSearch() {
  router.get(page.props.urls.dashboard, {
    period: props.period,
    response_status: props.responseStatus,
    search: searchTerm.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
}

function submitReview(action, tripId) {
  router.post(`${page.props.urls.trips_base}/${tripId}/${action}`, {
    period: props.period,
    response_status: props.responseStatus,
    search: props.search,
    page: props.recentTrips.current_page,
  });
}

function confirmReject(tripId) {
  if (!confirm('رفض هذه الرحلة؟')) return;
  router.post(`${page.props.urls.trips_base}/${tripId}/reject`, {
    period: props.period,
    response_status: props.responseStatus,
    search: props.search,
    page: props.recentTrips.current_page,
  });
}

function submitClarify(tripId, note) {
  if (!note) return;
  router.post(
    `${page.props.urls.trips_base}/${tripId}/clarify`,
    { note, period: props.period, response_status: props.responseStatus, search: props.search, page: props.recentTrips.current_page },
    { onSuccess: () => { clarifyNotes.value[tripId] = ''; } }
  );
}
</script>

<style scoped>
.page-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  height: 36px;
  padding: 0 12px;
  border-radius: 8px;
  text-decoration: none;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  background: var(--surface);
  border: 1px solid var(--stroke);
  transition: var(--transition);
}

.page-link.active {
  background: var(--brand);
  color: #fff;
  border-color: var(--brand);
}

.page-link.disabled {
  opacity: 0.4;
  pointer-events: none;
}

.page-link:not(.active):not(.disabled):hover {
  border-color: var(--brand);
  color: var(--brand);
}
</style>
