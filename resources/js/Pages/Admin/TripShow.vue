<template>
  <AppLayout>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
      <div>
        <h2 style="font-size:22px;font-weight:800;margin:0 0 4px;">تفاصيل الرحلة #{{ trip.id }}</h2>
        <p style="color:var(--text-secondary);margin:0;font-size:14px;">تتبع المسار ومراجعة التوقفات والنقاط المستلمة</p>
      </div>
      <Link :href="route('admin.dashboard')" class="btn-back">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        العودة للداشبورد
      </Link>
    </div>

    <!-- Stats -->
    <div class="stats-grid-5">
      <div class="stat-card"><div class="label">النقاط</div><div class="value" :style="pointsColor">{{ trip.points_received }} / {{ trip.points_expected }}</div></div>
      <div class="stat-card"><div class="label">المسافة التقريبية</div><div class="value">{{ distanceKm }} <span class="unit">كم</span></div></div>
      <div class="stat-card"><div class="label">مدة الرحلة</div><div class="value">{{ tripDurationMinutes ?? '-' }} <span class="unit">دقيقة</span></div></div>
      <div class="stat-card"><div class="label">وقت الانتظار</div><div class="value" :style="totalWait > 0 ? 'color:var(--warn)' : ''">{{ totalWait > 0 ? totalWait + ' دقيقة' : 'لا يوجد' }}</div></div>
      <div class="stat-card"><div class="label">الوقود الافتراضي</div><div class="value">{{ trip.virtual_fuel_liters ?? '-' }} <span class="unit">لتر</span></div></div>
    </div>

    <!-- Info Panels -->
    <div class="two-cols">
      <div class="panel">
        <div class="panel-header">معلومات المهمة</div>
        <div class="panel-body">
          <div class="kv-group">
            <div class="kv-item"><div class="kv-label">مسار الرحلة</div><div class="kv-val">{{ trip.origin_text }} ← {{ trip.destination_text }}</div></div>
            <div class="kv-item"><div class="kv-label">نوع المهمة</div><div class="kv-val">{{ trip.mission_type ?? '-' }}</div></div>
            <div class="kv-item"><div class="kv-label">بداية الرحلة</div><div class="kv-val mono">{{ formatDate(trip.started_at) }}</div></div>
            <div class="kv-item"><div class="kv-label">نهاية الرحلة</div><div class="kv-val mono">{{ formatDate(trip.ended_at) }}</div></div>
            <div class="kv-item">
              <div class="kv-label">حالة الرفع</div>
              <div class="kv-val"><span :class="['badge', trip.status === 'confirmed' ? 'ok' : 'neutral']">{{ trip.status }}</span></div>
            </div>
            <div class="kv-item">
              <div class="kv-label">مراجعة الإدارة</div>
              <div class="kv-val"><span :class="['badge', reviewBadge(trip.review_status)]">{{ reviewLabel(trip.review_status) }}</span></div>
            </div>
            <div v-if="trip.notes" class="kv-item" style="align-items:flex-start;">
              <div class="kv-label" style="margin-top:10px;">ملاحظات السائق</div>
              <div class="alert-box" style="border-right:4px solid var(--text-secondary);">{{ trip.notes }}</div>
            </div>
            <div v-if="trip.review_note" class="kv-item" style="align-items:flex-start;">
              <div class="kv-label" style="margin-top:10px;">ملاحظة الإدارة</div>
              <div class="alert-box" style="border-right:4px solid var(--brand);">{{ trip.review_note }}</div>
            </div>
            <div v-if="trip.clarification_response" class="kv-item" style="align-items:flex-start;">
              <div class="kv-label" style="margin-top:10px;">رد السائق على التوضيح</div>
              <div class="alert-box" style="border-right:4px solid var(--warn);">
                {{ trip.clarification_response }}
                <div style="font-size:11px;color:var(--text-secondary);margin-top:6px;">{{ formatDate(trip.clarification_responded_at) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">بيانات السائق والمركبة</div>
        <div class="panel-body">
          <div class="kv-group">
            <div class="kv-item"><div class="kv-label">السائق</div><div class="kv-val">{{ trip.driver_name ?? '-' }}</div></div>
            <div class="kv-item"><div class="kv-label">رقم الجوال</div><div class="kv-val mono" style="direction:ltr;text-align:right;">{{ trip.driver_phone ?? '-' }}</div></div>
            <div class="kv-item"><div class="kv-label">رقم الموظف</div><div class="kv-val mono">{{ trip.driver_employee_number ?? '-' }}</div></div>
            <div style="height:1px;background:var(--stroke);margin:8px 0;"></div>
            <div class="kv-item"><div class="kv-label">المركبة</div><div class="kv-val">{{ trip.vehicle_name ?? '-' }}</div></div>
            <div class="kv-item"><div class="kv-label">رقم اللوحة</div><div class="kv-val"><span class="badge neutral mono">{{ trip.vehicle_plate ?? '-' }}</span></div></div>
            <div class="kv-item"><div class="kv-label">نوع المركبة</div><div class="kv-val">{{ trip.vehicle_type ?? '-' }}</div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Map -->
    <div class="panel">
      <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div>مسار الرحلة الفعلي (GPS)</div>
        <span class="badge neutral">{{ points.length }} نقطة مسجلة</span>
      </div>
      <div ref="mapEl" style="height:400px;width:100%;z-index:10;"></div>
    </div>

    <!-- Stops Table -->
    <div class="panel">
      <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div>التوقفات المسجلة</div>
        <span :class="['badge', trip.stops?.length > 0 ? 'warn' : 'neutral']">{{ trip.stops?.length ?? 0 }} توقف</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>وقت بدء التوقف</th><th>وقت الاستئناف</th><th>المدة</th><th>السبب</th><th>ملاحظات</th></tr></thead>
          <tbody>
            <tr v-if="!trip.stops?.length">
              <td colspan="6" style="text-align:center;padding:32px;">
                <span v-if="trip.wait_minutes > 0" style="color:var(--warn);font-weight:600;">تم تسجيل {{ trip.wait_minutes }} دقيقة انتظار دون توقفات محددة.</span>
                <span v-else style="color:var(--text-secondary);">لم يسجل السائق أي توقف.</span>
              </td>
            </tr>
            <tr v-for="(stop, i) in trip.stops" :key="stop.id">
              <td style="font-weight:700;color:var(--text-secondary);">{{ i + 1 }}</td>
              <td class="mono">{{ formatDate(stop.started_at) }}</td>
              <td class="mono">{{ formatDate(stop.ended_at) }}</td>
              <td><span v-if="stopDuration(stop) !== null" class="badge warn">{{ stopDuration(stop) }} دقيقة</span><span v-else style="color:var(--text-secondary);">-</span></td>
              <td><span v-if="stop.reason" class="badge neutral">{{ stop.reason }}</span><span v-else style="color:var(--text-secondary);">-</span></td>
              <td style="color:var(--text-secondary);">{{ stop.notes ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Passengers -->
    <div class="panel">
      <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div>الركاب المرافقون</div>
        <span :class="['badge', trip.passengers?.length > 0 ? 'ok' : 'neutral']">{{ trip.passengers?.length ?? 0 }} راكب</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>اسم الراكب</th><th>الرقم الوظيفي</th><th>طريقة الإضافة</th></tr></thead>
          <tbody>
            <tr v-if="!trip.passengers?.length"><td colspan="4" style="text-align:center;padding:32px;color:var(--text-secondary);">لم يُسجّل السائق أي ركاب.</td></tr>
            <tr v-for="(p, i) in trip.passengers" :key="p.id">
              <td style="font-weight:700;color:var(--text-secondary);">{{ i + 1 }}</td>
              <td style="font-weight:700;">{{ p.name }}</td>
              <td class="mono">{{ p.employee_id ?? '-' }}</td>
              <td><span :class="['badge', sourceClass(p.source)]">{{ sourceLabel(p.source) }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tracking Points -->
    <div class="panel">
      <div class="panel-header">السجل الكامل لنقاط التتبع</div>
      <div class="table-wrap" style="max-height:400px;overflow-y:auto;">
        <table>
          <thead style="position:sticky;top:0;z-index:5;"><tr><th>#</th><th>الوقت</th><th>الإحداثيات</th><th>الدقة</th><th>السرعة</th></tr></thead>
          <tbody>
            <tr v-if="!points.length"><td colspan="5" style="text-align:center;padding:24px;color:var(--text-secondary);">السجل فارغ.</td></tr>
            <tr v-for="pt in points" :key="pt.id">
              <td style="font-weight:700;color:var(--text-secondary);">{{ pt.sequence_number }}</td>
              <td class="mono">{{ formatDate(pt.captured_at) }}</td>
              <td><a :href="`https://maps.google.com/?q=${pt.latitude},${pt.longitude}`" target="_blank" class="badge neutral mono" style="text-decoration:none;">{{ pt.latitude }}, {{ pt.longitude }}</a></td>
              <td>{{ pt.accuracy ?? '-' }} <span style="font-size:12px;color:var(--text-secondary);">متر</span></td>
              <td>{{ pt.speed ?? '-' }} <span style="font-size:12px;color:var(--text-secondary);">كم/س</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Review Panel -->
    <div class="review-panel">
      <div class="review-panel-header">
        <div style="font-size:16px;font-weight:800;">⚖️ إجراءات المراجعة الإدارية</div>
        <span :class="['badge', reviewBadge(trip.review_status)]">{{ reviewLabel(trip.review_status) }}</span>
      </div>

      <!-- Already reviewed -->
      <div v-if="['approved','rejected'].includes(trip.review_status)" :class="['already-reviewed', trip.review_status === 'approved' ? 'ok' : 'danger']">
        <div class="icon">{{ trip.review_status === 'approved' ? '✅' : '❌' }}</div>
        <div class="info">
          <div class="ar-title" :style="trip.review_status === 'approved' ? 'color:var(--ok)' : 'color:var(--danger)'">
            {{ trip.review_status === 'approved' ? 'تمت الموافقة على هذه الرحلة' : 'تم رفض هذه الرحلة' }}
          </div>
          <div style="font-size:13px;color:var(--text-secondary);margin-top:4px;">
            <span v-if="trip.review_note">{{ trip.review_note }}</span>
            <span v-else>بدون ملاحظة إضافية.</span>
          </div>
          <div v-if="trip.clarification_response" style="margin-top:10px;padding-top:10px;border-top:1px dashed rgba(0,0,0,0.1);">
            <div style="font-weight:700;margin-bottom:4px;">💬 رد السائق على التوضيح:</div>
            <div style="color:var(--text-secondary);">{{ trip.clarification_response }}</div>
          </div>
        </div>
      </div>

      <!-- Needs clarification: show history + approve/reject -->
      <template v-else-if="trip.review_status === 'needs_clarification'">
        <div style="padding:24px;display:flex;flex-direction:column;gap:20px;">
          <div style="background:var(--warn-bg);border:1px solid rgba(245,158,11,0.3);border-right:4px solid var(--warn);border-radius:var(--radius-md);padding:16px 20px;">
            <div style="font-weight:800;color:var(--warn);margin-bottom:8px;">💬 طلب توضيح مُرسل للسائق</div>
            <div style="font-size:14px;line-height:1.6;font-weight:600;">{{ trip.review_note }}</div>
            <div v-if="trip.clarification_response" style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(245,158,11,0.3);">
              <div style="font-size:12px;font-weight:700;color:var(--ok);margin-bottom:6px;">✅ رد السائق:</div>
              <div>{{ trip.clarification_response }}</div>
            </div>
            <div v-else style="margin-top:12px;font-size:12px;color:var(--warn);font-weight:600;">⏳ بانتظار رد السائق...</div>
          </div>

          <div class="review-actions">
            <div class="review-form">
              <textarea class="review-textarea" v-model="approveNote" placeholder="ملاحظة الموافقة (اختياري)..."></textarea>
              <div class="review-btns">
                <button class="btn-approve" @click="submitApprove">✓ الموافقة على الرحلة</button>
              </div>
            </div>
            <div style="width:1px;background:var(--stroke);align-self:stretch;"></div>
            <div class="review-form">
              <textarea class="review-textarea" v-model="rejectNote" placeholder="سبب الرفض (اختياري)..."></textarea>
              <div class="review-btns">
                <button class="btn-reject" @click="confirmReject">✕ رفض الرحلة</button>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Pending: full 3-action form -->
      <template v-else>
        <div class="review-actions">
          <div class="review-form">
            <textarea class="review-textarea" v-model="approveNote" placeholder="ملاحظة الموافقة (اختياري)..."></textarea>
            <div class="review-btns"><button class="btn-approve" @click="submitApprove">✓ الموافقة على الرحلة</button></div>
          </div>
          <div style="width:1px;background:var(--stroke);align-self:stretch;"></div>
          <div class="review-form">
            <textarea class="review-textarea" v-model="rejectNote" placeholder="سبب الرفض (مستحسن)..."></textarea>
            <div class="review-btns"><button class="btn-reject" @click="confirmReject">✕ رفض الرحلة</button></div>
          </div>
          <div style="width:1px;background:var(--stroke);align-self:stretch;"></div>
          <div class="review-form">
            <textarea class="review-textarea" v-model="clarifyNote" placeholder="نص السؤال المطلوب من السائق (مطلوب)..." required></textarea>
            <div class="review-btns"><button class="btn-clarify" @click="submitClarify">💬 طلب توضيح من السائق</button></div>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  trip: Object,
  points: Array,
  mappedPoints: Array,
  distanceKm: Number,
  stopsMinutes: Number,
  tripDurationMinutes: Number,
});

const mapEl = ref(null);
const approveNote = ref('');
const rejectNote = ref('');
const clarifyNote = ref('');

const totalWait = computed(() =>
  Math.max(props.stopsMinutes ?? 0, props.trip.wait_minutes ?? 0)
);

const pointsColor = computed(() => {
  if (!props.trip.points_expected) return '';
  const pct = (props.trip.points_received / props.trip.points_expected) * 100;
  return `color: ${pct >= 80 ? 'var(--ok)' : pct >= 50 ? 'var(--warn)' : 'var(--danger)'}`;
});

function formatDate(d) {
  if (!d) return '-';
  return new Date(d).toLocaleString('sv-SE').replace('T', ' ').slice(0, 16);
}
function reviewBadge(s) {
  return s === 'approved' ? 'ok' : s === 'rejected' ? 'danger' : s === 'needs_clarification' ? 'warn' : 'neutral';
}
function reviewLabel(s) {
  return s === 'approved' ? 'مُعتمدة' : s === 'rejected' ? 'مرفوضة' : s === 'needs_clarification' ? 'تحتاج توضيح' : 'قيد المراجعة';
}
function stopDuration(stop) {
  if (!stop.started_at || !stop.ended_at) return null;
  return Math.max(0, Math.round((new Date(stop.ended_at) - new Date(stop.started_at)) / 60000));
}
function sourceLabel(s) {
  return s === 'free_text' ? 'نص حر' : s === 'employee_list' ? 'من قائمة الموظفين' : s === 'manual' ? 'يدوي' : s;
}
function sourceClass(s) {
  return s === 'employee_list' ? 'ok' : s === 'manual' ? 'warn' : 'neutral';
}

function submitApprove() {
  router.post(route('admin.trips.approve', { trip: props.trip.id }), { note: approveNote.value, _from: 'show' });
}
function confirmReject() {
  if (!confirm('هل تريد رفض هذه الرحلة؟')) return;
  router.post(route('admin.trips.reject', { trip: props.trip.id }), { note: rejectNote.value, _from: 'show' });
}
function submitClarify() {
  if (!clarifyNote.value) return;
  router.post(route('admin.trips.clarify', { trip: props.trip.id }), { note: clarifyNote.value, _from: 'show' });
}

onMounted(async () => {
  if (!mapEl.value) return;
  const L = (await import('leaflet')).default;
  await import('leaflet/dist/leaflet.css');

  const map = L.map(mapEl.value);
  map.setView([31.5017, 34.4668], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);

  if (!props.mappedPoints.length) return;
  const latLngs = props.mappedPoints.map(p => [p.lat, p.lng]);
  const route = L.polyline(latLngs, { color: '#3b82f6', weight: 5, opacity: 0.8 }).addTo(map);
  L.marker(latLngs[0]).addTo(map).bindPopup('<b>نقطة الانطلاق</b>');
  L.marker(latLngs[latLngs.length - 1]).addTo(map).bindPopup('<b>نقطة الوصول</b>');
  map.fitBounds(route.getBounds(), { padding: [32, 32] });
});
</script>

<style scoped>
.stats-grid-5 { display:grid; grid-template-columns:repeat(5,1fr); gap:16px; margin-bottom:24px; }
.stat-card { background:var(--surface); border-radius:var(--radius-lg); padding:20px; box-shadow:var(--shadow-md); }
.stat-card .label { font-size:13px; color:var(--text-secondary); font-weight:600; }
.stat-card .value { font-size:26px; font-weight:800; margin-top:8px; }
.unit { font-size:14px; color:var(--text-secondary); }

.two-cols { display:grid; grid-template-columns:1.3fr 1fr; gap:24px; margin-bottom:24px; }
.panel { background:var(--surface); border-radius:var(--radius-lg); box-shadow:var(--shadow-lg); overflow:hidden; margin-bottom:24px; }
.panel-header { padding:16px 24px; border-bottom:1px solid var(--stroke); font-size:16px; font-weight:800; }
.panel-body { padding:24px; }
.kv-group { display:flex; flex-direction:column; gap:16px; }
.kv-item { display:grid; grid-template-columns:140px 1fr; gap:12px; align-items:center; }
.kv-label { color:var(--text-secondary); font-size:14px; font-weight:600; }
.kv-val { font-size:14px; font-weight:700; }
.mono { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:13px; }
.alert-box { background:#f8fafc; border:1px solid var(--stroke); border-radius:var(--radius-md); padding:12px 16px; font-size:13px; line-height:1.5; }

.btn-back { border:none; border-radius:var(--radius-md); padding:10px 20px; background:var(--brand); color:#fff; text-decoration:none; font-weight:700; font-size:14px; display:flex; align-items:center; gap:8px; transition:var(--transition); }
.btn-back:hover { background:var(--brand-hover); transform:translateY(-1px); }

.review-panel { background:linear-gradient(135deg,#fff 0%,#f8faff 100%); border-radius:var(--radius-lg); box-shadow:var(--shadow-lg); border:1px solid rgba(59,130,246,0.15); overflow:hidden; margin-bottom:24px; }
.review-panel-header { padding:16px 24px; border-bottom:1px solid var(--stroke); background:linear-gradient(135deg,rgba(59,130,246,0.06),rgba(139,92,246,0.04)); display:flex; align-items:center; justify-content:space-between; }
.review-actions { padding:24px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-start; }
.review-form { flex:1; min-width:280px; }
.review-textarea { width:100%; border:1px solid var(--stroke); border-radius:var(--radius-md); padding:12px 14px; font-family:'Tajawal',sans-serif; font-size:14px; resize:vertical; min-height:80px; background:#fafbfc; color:var(--text-primary); outline:none; transition:var(--transition); }
.review-textarea:focus { border-color:var(--brand); background:#fff; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
.review-btns { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
.btn-approve { padding:10px 22px; border:none; border-radius:var(--radius-md); background:var(--ok); color:#fff; font-family:'Tajawal',sans-serif; font-size:14px; font-weight:700; cursor:pointer; transition:var(--transition); }
.btn-approve:hover { background:#059669; transform:translateY(-1px); }
.btn-reject { padding:10px 22px; border:none; border-radius:var(--radius-md); background:var(--danger); color:#fff; font-family:'Tajawal',sans-serif; font-size:14px; font-weight:700; cursor:pointer; transition:var(--transition); }
.btn-reject:hover { background:#dc2626; transform:translateY(-1px); }
.btn-clarify { padding:10px 22px; border:none; border-radius:var(--radius-md); background:var(--warn); color:#fff; font-family:'Tajawal',sans-serif; font-size:14px; font-weight:700; cursor:pointer; transition:var(--transition); }
.btn-clarify:hover { background:#d97706; transform:translateY(-1px); }
.already-reviewed { padding:24px; display:flex; align-items:center; gap:16px; }
.already-reviewed.ok { background:rgba(16,185,129,0.04); }
.already-reviewed.danger { background:rgba(239,68,68,0.04); }
.icon { font-size:32px; }
.ar-title { font-size:15px; font-weight:700; }

@media (max-width:1100px) { .stats-grid-5 { grid-template-columns:repeat(2,1fr); } .two-cols { grid-template-columns:1fr; } }
</style>
