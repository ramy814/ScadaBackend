<template>
  <AppLayout>
    <!-- Toolbar: Period + Response Status Filters -->
    <div class="toolbar">
      <div class="tabs-group">
        <Link
          v-for="p in periods"
          :key="p.value"
          :href="route('admin.dashboard', { period: p.value, response_status: responseStatus })"
          :class="{ active: period === p.value }"
        >{{ p.label }}</Link>
      </div>
      <div class="filters-group">
        <Link
          v-for="f in responseFilters"
          :key="f.value"
          :href="route('admin.dashboard', { period, response_status: f.value })"
          :class="{ active: responseStatus === f.value }"
        >{{ f.label }}</Link>
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
        <div class="table-title">آخر الرحلات المرفوعة</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
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
            <tr v-if="recentTrips.length === 0">
              <td colspan="7" style="text-align:center; padding:48px; color:var(--text-secondary);">
                لا توجد رحلات ضمن الفترة المحددة.
              </td>
            </tr>
            <tr v-for="trip in recentTrips" :key="trip.id">
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
                    <Link :href="route('admin.trips.show', { trip: trip.id })" class="btn-icon view" title="التفاصيل">
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
    </div>

    <div class="footer-note">
      آخر تحديث للبيانات: <span dir="ltr">{{ now }}</span>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

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
  recentTrips: Array,
});

const now = new Date().toLocaleString('ar-EG', { hour12: false });
const clarifyNotes = ref({});

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

function submitReview(action, tripId) {
  router.post(route(`admin.trips.${action}`, { trip: tripId, period: props.period }));
}

function confirmReject(tripId) {
  if (!confirm('رفض هذه الرحلة؟')) return;
  router.post(route('admin.trips.reject', { trip: tripId, period: props.period }));
}

function submitClarify(tripId, note) {
  if (!note) return;
  router.post(
    route('admin.trips.clarify', { trip: tripId, period: props.period }),
    { note },
    { onSuccess: () => { clarifyNotes.value[tripId] = ''; } }
  );
}
</script>
