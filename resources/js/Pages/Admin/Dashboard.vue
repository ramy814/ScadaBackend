<template>
  <AppLayout>
    <!-- Toolbar: Period + Response Status Filters -->
    <div class="toolbar">
      <div class="tabs-group">
        <Link
          v-for="p in periods"
          :key="p.value"
          :href="dashboardUrl({ period: p.value, date_from: '', date_to: '' })"
          :class="{ active: period === p.value }"
        >{{ p.label }}</Link>
      </div>
      <div class="toolbar-actions">
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
          <div class="stat-item"><div class="label">المركبات الفعالة على التطبيق</div><div class="value">{{ activeVehicles }}</div></div>
          <div class="stat-item"><div class="label">السائقون الفعالون على التطبيق</div><div class="value">{{ activeDrivers }}</div></div>
          <div class="stat-item fuel-stat">
            <div class="label">استهلاك السولار للفترة</div>
            <div class="value fuel-value">
              <span class="fuel-number" dir="ltr">{{ formatLiters(fuelConsumptionLiters) }}</span>
              <small>لتر</small>
            </div>
            <div class="metric-note">من {{ fuelConsumptionTrips }} رحلة مؤكدة محسوبة</div>
            <form class="date-range-form" @submit.prevent="submitDateRange">
              <label>
                <span>من</span>
                <input v-model="dateFromInput" type="date" aria-label="من تاريخ" required />
              </label>
              <label>
                <span>إلى</span>
                <input v-model="dateToInput" type="date" :min="dateFromInput" aria-label="إلى تاريخ" required />
              </label>
              <button type="submit">تطبيق الفترة</button>
            </form>
          </div>
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
        <div class="table-heading">
          <div class="table-title">رحلات الفترة المحددة</div>
          <div v-if="recentTrips.total" class="table-count">
            عرض {{ recentTrips.from }} - {{ recentTrips.to }} من {{ recentTrips.total }}
          </div>
        </div>
        <Link
          :href="dashboardUrl({ show_experimental: showExperimental ? false : true })"
          :class="['experimental-toggle', { active: showExperimental }]"
          title="الرحلات المنتهية التي تحتوي على أقل من 50 نقطة"
        >
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 3h6"></path>
            <path d="M10 3v6l-5 9a2 2 0 0 0 1.7 3h10.6a2 2 0 0 0 1.7-3l-5-9V3"></path>
            <path d="M8.5 14h7"></path>
          </svg>
          {{ showExperimental ? 'إخفاء الرحلات التجريبية' : 'إظهار الرحلات التجريبية' }}
        </Link>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>رقم الرحلة</th>
              <th>المركبة / السائق</th>
              <th>المسار (انطلاق ← وجهة)</th>
              <th>النقاط</th>
              <th>سلامة التتبع</th>
              <th>الحالة الإدارية</th>
              <th>رد السائق</th>
              <th>الوقت</th>
              <th>إجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="recentTrips.data.length === 0">
              <td colspan="9" style="text-align:center; padding:48px; color:var(--text-secondary);">
                لا توجد رحلات ضمن الفترة المحددة.
              </td>
            </tr>
            <tr v-for="trip in recentTrips.data" :key="trip.id">
              <td style="font-weight:800;" dir="ltr">#{{ trip.id }}</td>
              <td>
                <div style="font-weight:700;">{{ trip.vehicle?.plate_number ?? '-' }}</div>
                <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">{{ trip.driver?.name ?? '-' }}</div>
                <div v-if="trip.driver?.phone" dir="ltr" style="font-size:11px; color:var(--text-secondary); margin-top:2px;">{{ trip.driver.phone }}</div>
              </td>
              <td>
                <div style="font-weight:600;">{{ trip.origin_text }}</div>
                <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">إلى: {{ trip.destination_text }}</div>
              </td>
              <td>
                <span class="badge neutral">{{ trip.points_received }} / {{ trip.points_expected }}</span>
                <span v-if="trip.ended_at && Number(trip.points_received) < 50" class="badge warn" style="margin-right:4px;">تجريبية</span>
              </td>
              <td>
                <span :class="['badge', trackingHealthClass(trip)]">{{ trackingHealthLabel(trip) }}</span>
                <div v-if="trackingIncidentLabel(trip)" style="font-size:11px;color:var(--danger);margin-top:4px;font-weight:700;">
                  {{ trackingIncidentLabel(trip) }} — يحتاج مراجعة
                </div>
                <div v-if="trip.last_point_at" style="font-size:11px;color:var(--text-secondary);margin-top:4px;">
                  آخر نقطة: {{ formatDate(trip.last_point_at) }}
                </div>
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
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();

const props = defineProps({
  todayTrips: Number,
  confirmedTrips: Number,
  activeVehicles: Number,
  activeDrivers: Number,
  fuelConsumptionLiters: Number,
  fuelConsumptionTrips: Number,
  approvedTrips: Number,
  rejectedTrips: Number,
  clarificationTrips: Number,
  clarificationPendingResponse: Number,
  clarificationResponded: Number,
  period: String,
  dateFrom: String,
  dateTo: String,
  responseStatus: String,
  search: String,
  showExperimental: Boolean,
  recentTrips: Object,
});

const now = new Date().toLocaleString('ar-EG', { hour12: false });
const clarifyNotes = ref({});
const searchTerm = ref(props.search ?? '');
const dateFromInput = ref(props.dateFrom ?? '');
const dateToInput = ref(props.dateTo ?? '');
let liveRefreshTimer = null;

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

function formatLiters(value) {
  return new Intl.NumberFormat('en-US', { maximumFractionDigits: 3 }).format(Number(value) || 0);
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

const criticalTrackingEvents = new Set([
  'gps_disabled',
  'airplane_mode_on',
  'device_shutdown',
  'location_permission_missing',
  'tracking_gap',
  'clock_anomaly',
]);

function trackingHealthClass(trip) {
  if (trackingIncidentLabel(trip)) return 'danger';
  if (trip.ended_at) return 'neutral';
  if (criticalTrackingEvents.has(trip.latest_tracking_event?.event_type)) return 'danger';
  if (trip.latest_tracking_event?.event_type === 'tracking_error') return 'warn';
  if (!trip.last_point_at) return 'warn';
  return Date.now() - new Date(trip.last_point_at).getTime() <= 90000 ? 'ok' : 'danger';
}

function trackingHealthLabel(trip) {
  if (trackingIncidentLabel(trip)) return 'حدث تتبع يحتاج مراجعة';
  if (trip.ended_at) return 'منتهية دون حدث تعطيل مسجّل';
  const event = trip.latest_tracking_event?.event_type;
  const labels = {
    gps_disabled: 'GPS معطّل',
    airplane_mode_on: 'وضع الطيران مفعّل',
    device_shutdown: 'تم إطفاء الجهاز',
    location_permission_missing: 'إذن الموقع متوقف',
    tracking_gap: 'انقطاع طويل',
    clock_anomaly: 'خلل في وقت الجهاز',
    tracking_error: 'خطأ في خدمة التتبع',
  };
  if (labels[event]) return labels[event];
  if (!trip.last_point_at) return 'بانتظار أول نقطة';
  return Date.now() - new Date(trip.last_point_at).getTime() <= 90000
    ? 'متصل مباشرة'
    : 'لا توجد نقاط حديثة';
}

function trackingIncidentLabel(trip) {
  const incidents = [];
  if (Number(trip.gps_disabled_events_count) > 0) {
    incidents.push(`إيقاف GPS: ${trip.gps_disabled_events_count}`);
  }
  if (Number(trip.airplane_mode_events_count) > 0) {
    incidents.push(`تشغيل وضع الطيران: ${trip.airplane_mode_events_count}`);
  }
  if (Number(trip.tracking_gap_events_count) > 0) {
    incidents.push(`فجوات GPS: ${trip.tracking_gap_events_count}`);
  }
  return incidents.join(' | ');
}

onMounted(() => {
  if (!props.recentTrips.data.some(trip => !trip.ended_at)) return;
  liveRefreshTimer = window.setInterval(() => {
    router.reload({ only: ['recentTrips'], preserveScroll: true, preserveState: true });
  }, 15000);
});

onBeforeUnmount(() => {
  if (liveRefreshTimer) window.clearInterval(liveRefreshTimer);
});

function dashboardUrl(overrides = {}) {
  const params = new URLSearchParams();
  const values = {
    period: props.period ?? 'daily',
    response_status: props.responseStatus ?? 'all',
    date_from: props.dateFrom,
    date_to: props.dateTo,
    search: props.search,
    show_experimental: props.showExperimental,
    ...overrides,
  };

  Object.entries(values).forEach(([key, value]) => {
    if (value === true) params.set(key, '1');
    else if (value !== false && value !== null && value !== undefined && value !== '') params.set(key, value);
  });

  return `${page.props.urls.dashboard}?${params.toString()}`;
}

function submitDateRange() {
  router.get(page.props.urls.dashboard, {
    period: 'custom',
    date_from: dateFromInput.value,
    date_to: dateToInput.value,
    response_status: props.responseStatus,
    search: props.search,
    show_experimental: props.showExperimental ? 1 : undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
}

function submitSearch() {
  router.get(page.props.urls.dashboard, {
    period: props.period,
    date_from: props.dateFrom,
    date_to: props.dateTo,
    response_status: props.responseStatus,
    search: searchTerm.value,
    show_experimental: props.showExperimental ? 1 : undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
}

function submitReview(action, tripId) {
  router.post(`${page.props.urls.trips_base}/${tripId}/${action}`, {
    period: props.period,
    date_from: props.dateFrom,
    date_to: props.dateTo,
    response_status: props.responseStatus,
    search: props.search,
    show_experimental: props.showExperimental ? 1 : undefined,
    page: props.recentTrips.current_page,
  });
}

function confirmReject(tripId) {
  if (!confirm('رفض هذه الرحلة؟')) return;
  router.post(`${page.props.urls.trips_base}/${tripId}/reject`, {
    period: props.period,
    date_from: props.dateFrom,
    date_to: props.dateTo,
    response_status: props.responseStatus,
    search: props.search,
    show_experimental: props.showExperimental ? 1 : undefined,
    page: props.recentTrips.current_page,
  });
}

function submitClarify(tripId, note) {
  if (!note) return;
  router.post(
    `${page.props.urls.trips_base}/${tripId}/clarify`,
    { note, period: props.period, date_from: props.dateFrom, date_to: props.dateTo, response_status: props.responseStatus, search: props.search, show_experimental: props.showExperimental ? 1 : undefined, page: props.recentTrips.current_page },
    { onSuccess: () => { clarifyNotes.value[tripId] = ''; } }
  );
}
</script>

<style scoped>
.date-range-form {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 14px;
  padding: 12px;
  border: 1px solid var(--stroke);
  border-radius: 10px;
  background: var(--bg-color);
  color: var(--text-secondary);
  font-size: 12px;
}

.date-range-form label {
  display: flex;
  align-items: center;
  gap: 6px;
}

.toolbar-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.fuel-stat {
  grid-column: 1 / -1;
  margin-top: 8px;
  padding-top: 16px;
  border-top: 1px solid var(--stroke);
}

.fuel-value {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.fuel-number {
  unicode-bidi: isolate;
}

.date-range-form input,
.date-range-form button {
  height: 38px;
  border: 1px solid var(--stroke);
  border-radius: 8px;
  background: var(--card-bg);
  color: var(--text-primary);
  padding: 0 9px;
  font-family: inherit;
}

.date-range-form button {
  border-color: var(--brand);
  background: var(--brand);
  color: #fff;
  cursor: pointer;
  font-weight: 700;
  padding-inline: 16px;
}

.value small {
  font-size: 12px;
}

.metric-note {
  margin-top: 3px;
  color: var(--text-secondary);
  font-size: 11px;
}

.table-heading {
  display: flex;
  align-items: baseline;
  gap: 14px;
  flex-wrap: wrap;
}

.table-count {
  color: var(--text-secondary);
  font-size: 13px;
  font-weight: 600;
}

.experimental-toggle {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 14px;
  border: 1px solid var(--warn);
  border-radius: var(--radius-md);
  color: var(--warn);
  background: var(--warn-bg);
  font-size: 13px;
  font-weight: 800;
  text-decoration: none;
  white-space: nowrap;
  transition: var(--transition);
}

.experimental-toggle svg {
  width: 18px;
  height: 18px;
}

.experimental-toggle:hover,
.experimental-toggle.active {
  color: #fff;
  background: var(--warn);
}

@media (max-width: 700px) {
  .table-header {
    align-items: stretch;
    flex-direction: column;
    gap: 12px;
  }

  .experimental-toggle {
    justify-content: center;
  }
}

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
