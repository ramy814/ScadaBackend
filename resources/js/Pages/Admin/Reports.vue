<template>
  <AppLayout>
    <div style="margin-bottom:24px;">
      <h2 style="font-size:22px; font-weight:800; margin:0 0 4px;">التقارير</h2>
      <p style="color:var(--text-secondary); margin:0; font-size:14px;">تقارير يومية وأسبوعية وشهرية مع تصدير CSV وPDF</p>
    </div>

    <!-- Filters Bar -->
    <div class="table-card" style="margin-bottom:24px;">
      <div style="padding:20px;">
        <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
          <!-- Period Tabs -->
          <div class="tabs-group">
            <Link
              v-for="p in periods"
              :key="p.value"
              :href="route('admin.reports', { period: p.value, driver_id: driverId, vehicle_id: vehicleId })"
              :class="{ active: period === p.value }"
            >{{ p.label }}</Link>
          </div>

          <!-- Driver Filter -->
          <select class="form-select" v-model="selectedDriver" @change="applyFilters">
            <option value="">كل السائقين</option>
            <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>

          <!-- Vehicle Filter -->
          <select class="form-select" v-model="selectedVehicle" @change="applyFilters">
            <option value="">كل المركبات</option>
            <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.plate_number }} - {{ v.name }}</option>
          </select>

          <button class="btn-primary" @click="applyFilters">تحديث</button>

          <!-- Export Links -->
          <a :href="csvUrl" class="btn-export csv">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            تنزيل CSV
          </a>
          <a :href="pdfUrl" class="btn-export pdf">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
            تنزيل PDF
          </a>
        </div>
      </div>
    </div>

    <!-- Summary Stats -->
    <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:16px; margin-bottom:24px;">
      <div class="stat-group primary">
        <div class="stat-item">
          <div class="label">عدد الرحلات</div>
          <div class="value">{{ summary.trips_count }}</div>
        </div>
      </div>
      <div class="stat-group success">
        <div class="stat-item">
          <div class="label">إجمالي الوقود الافتراضي (لتر)</div>
          <div class="value">{{ summary.sum_virtual_fuel }}</div>
        </div>
      </div>
    </div>

    <!-- Trips Table -->
    <div class="table-card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>الوقت</th>
              <th>السائق</th>
              <th>المركبة</th>
              <th>الانطلاق</th>
              <th>الوجهة</th>
              <th>الحالة</th>
              <th>المراجعة</th>
              <th>وقود افتراضي</th>
              <th>النقاط</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="trips.data.length === 0">
              <td colspan="10" style="text-align:center; padding:32px; color:var(--text-secondary);">
                لا توجد بيانات ضمن الفلاتر المحددة.
              </td>
            </tr>
            <tr v-for="trip in trips.data" :key="trip.id">
              <td style="font-weight:700; color:var(--text-secondary);">{{ trip.id }}</td>
              <td style="font-size:13px; color:var(--text-secondary);">{{ formatDate(trip.started_at) }}</td>
              <td>{{ trip.driver?.name ?? '-' }}</td>
              <td>{{ trip.vehicle?.plate_number ?? '-' }}</td>
              <td>{{ trip.origin_text }}</td>
              <td>{{ trip.destination_text }}</td>
              <td><span :class="['badge', statusBadge(trip.status)]">{{ trip.status }}</span></td>
              <td><span :class="['badge', reviewBadge(trip.review_status)]">{{ reviewLabel(trip.review_status) }}</span></td>
              <td>{{ trip.virtual_fuel_liters ?? '-' }}</td>
              <td><span class="badge neutral">{{ trip.points_received }} / {{ trip.points_expected }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="trips.last_page > 1" style="padding:16px 24px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
        <Link
          v-for="link in trips.links"
          :key="link.label"
          :href="link.url ?? '#'"
          :class="['page-link', { active: link.active, disabled: !link.url }]"
          v-html="link.label"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  period: String,
  driverId: String,
  vehicleId: String,
  from: String,
  to: String,
  drivers: Array,
  vehicles: Array,
  trips: Object,
  summary: Object,
});

const periods = [
  { value: 'daily', label: 'يومي' },
  { value: 'weekly', label: 'أسبوعي' },
  { value: 'monthly', label: 'شهري' },
];

const selectedDriver = ref(props.driverId ?? '');
const selectedVehicle = ref(props.vehicleId ?? '');

function applyFilters() {
  router.get(route('admin.reports'), {
    period: props.period,
    driver_id: selectedDriver.value,
    vehicle_id: selectedVehicle.value,
  }, { preserveState: true });
}

const csvUrl = computed(() =>
  route('admin.reports.csv', { period: props.period, driver_id: props.driverId, vehicle_id: props.vehicleId })
);
const pdfUrl = computed(() =>
  route('admin.reports.pdf', { period: props.period, driver_id: props.driverId, vehicle_id: props.vehicleId })
);

function formatDate(d) {
  if (!d) return '-';
  return new Date(d).toLocaleString('sv-SE', { hour12: false }).replace('T', ' ').slice(0, 16);
}
function statusBadge(s) {
  return s === 'confirmed' ? 'ok' : s === 'uploaded' ? 'warn' : 'danger';
}
function reviewBadge(s) {
  return s === 'approved' ? 'ok' : s === 'rejected' ? 'danger' : s === 'needs_clarification' ? 'warn' : 'neutral';
}
function reviewLabel(s) {
  return s === 'approved' ? 'مُعتمدة' : s === 'rejected' ? 'مرفوضة' : s === 'needs_clarification' ? 'توضيح' : 'قيد المراجعة';
}
</script>

<style scoped>
.form-select {
  border: 1px solid var(--stroke);
  border-radius: 8px;
  padding: 9px 12px;
  font-size: 13px;
  font-family: 'Tajawal', sans-serif;
  background: #f8fafc;
  color: var(--text-primary);
  transition: var(--transition);
}
.form-select:focus { outline: none; border-color: var(--brand); }

.btn-primary {
  border: none; border-radius: var(--radius-md);
  padding: 10px 20px; background: var(--brand); color: #fff;
  font-family: 'Tajawal', sans-serif; font-weight: 700; font-size: 14px;
  cursor: pointer; transition: var(--transition);
}
.btn-primary:hover { background: var(--brand-hover); }

.btn-export {
  display: inline-flex; align-items: center; gap: 6px;
  text-decoration: none; border-radius: var(--radius-md);
  padding: 10px 16px; font-family: 'Tajawal', sans-serif;
  font-weight: 700; font-size: 13px; transition: var(--transition);
}
.btn-export.csv { background: #d1fae5; color: #065f46; }
.btn-export.csv:hover { background: #10b981; color: #fff; }
.btn-export.pdf { background: #fef3c7; color: #92400e; }
.btn-export.pdf:hover { background: #b45309; color: #fff; }

.page-link {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 36px; height: 36px; padding: 0 12px;
  border-radius: 8px; text-decoration: none;
  font-size: 13px; font-weight: 600;
  color: var(--text-secondary); background: var(--surface);
  border: 1px solid var(--stroke); transition: var(--transition);
}
.page-link.active { background: var(--brand); color: #fff; border-color: var(--brand); }
.page-link.disabled { opacity: 0.4; pointer-events: none; }
.page-link:not(.active):not(.disabled):hover { border-color: var(--brand); color: var(--brand); }

.stat-group { padding: 20px; }
.stat-item .label { font-size: 13px; color: var(--text-secondary); font-weight: 600; margin-bottom: 8px; }
.stat-item .value { font-size: 28px; font-weight: 800; }
</style>
