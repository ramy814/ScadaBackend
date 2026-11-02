<template>
  <AppLayout>
    <div style="margin-bottom:24px;">
      <h2 style="font-size:22px; font-weight:800; margin:0 0 4px;">المطالبات المالية للمركبات</h2>
      <p style="color:var(--text-secondary); margin:0; font-size:14px;">
        مبلغ كل مركبة = مجموع مدد رحلاتها المعتمدة (من البداية إلى النهاية) × سعر الساعة المثبّت لحظة اعتماد الرحلة.
      </p>
    </div>

    <!-- Filters -->
    <div class="table-card" style="margin-bottom:24px;">
      <div style="padding:20px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
        <label class="field">
          <span>الشهر</span>
          <input class="form-input narrow" type="month" v-model="month" @change="onMonthChange" />
        </label>
        <label class="field">
          <span>من تاريخ</span>
          <input class="form-input narrow" type="date" v-model="dateFrom" @change="onRangeChange" />
        </label>
        <label class="field">
          <span>إلى تاريخ</span>
          <input class="form-input narrow" type="date" v-model="dateTo" @change="onRangeChange" />
        </label>
        <label class="field">
          <span>المركبة</span>
          <input class="form-input" type="search" v-model="vehicleSearch" placeholder="رقم أو اسم المركبة" @input="scheduleFilter" />
        </label>
        <label class="check">
          <input type="checkbox" v-model="includeUnpriced" @change="applyFilters" />
          عرض المركبات بدون سعر ساعة
        </label>

        <div style="display:flex; gap:8px; margin-inline-start:auto;">
          <button class="btn-export csv" @click="download(page.props.urls.vehicleClaimsCsv)">تنزيل CSV</button>
          <button class="btn-export pdf" @click="download(page.props.urls.vehicleClaimsPdf)">تنزيل PDF</button>
        </div>
      </div>
    </div>

    <!-- Totals -->
    <div class="stats-grid">
      <div class="stat-group primary"><div class="stat-item"><div class="label">عدد المركبات</div><div class="value">{{ totals.vehicles }}</div></div></div>
      <div class="stat-group primary"><div class="stat-item"><div class="label">الرحلات المعتمدة</div><div class="value">{{ totals.trips }}</div></div></div>
      <div class="stat-group success"><div class="stat-item"><div class="label">ساعات العمل</div><div class="value" dir="ltr">{{ totals.work_time }}</div></div></div>
      <div class="stat-group success"><div class="stat-item"><div class="label">إجمالي المطالبة (شيكل)</div><div class="value">{{ money(totals.amount) }}</div></div></div>
    </div>

    <div v-if="totals.current_price_trips || totals.unpriced_trips || totals.hidden_unpriced_vehicles" class="notice">
      <div v-if="totals.current_price_trips">
        {{ totals.current_price_trips }} رحلة اعتُمدت قبل تثبيت السعر، فحُسبت بسعر الساعة الحالي للمركبة.
      </div>
      <div v-if="totals.unpriced_trips">
        {{ totals.unpriced_trips }} رحلة لمركبات بلا سعر ساعة في G_STORE.VEHICLE لم تدخل في المبلغ.
      </div>
      <div v-if="totals.hidden_unpriced_vehicles">
        {{ totals.hidden_unpriced_vehicles }} مركبة لها رحلات معتمدة دون سعر ساعة مخفية من الجدول.
      </div>
    </div>

    <!-- Claims table -->
    <div class="table-card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>رقم المركبة</th>
              <th>اسم المركبة</th>
              <th>السائق</th>
              <th>عدد الرحلات</th>
              <th>ساعات العمل</th>
              <th>سعر الساعة</th>
              <th>المبلغ (شيكل)</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="rows.length === 0">
              <td colspan="9" style="text-align:center; padding:32px; color:var(--text-secondary);">
                لا توجد رحلات معتمدة ضمن الفترة المحددة.
              </td>
            </tr>
            <template v-for="(row, index) in rows" :key="row.oracle_vehicle_id">
              <tr>
                <td style="font-weight:700; color:var(--text-secondary);">{{ index + 1 }}</td>
                <td style="font-weight:700;">{{ row.plate_number ?? '-' }}</td>
                <td>{{ row.vehicle_name ?? '-' }}</td>
                <td>{{ row.driver_name ?? '-' }}</td>
                <td>{{ row.trips_count }}</td>
                <td dir="ltr" style="text-align:right;">{{ row.work_time }}</td>
                <td>
                  {{ row.price_label }}
                  <span v-if="row.current_price_trips" class="badge warn" title="رحلات حُسبت بالسعر الحالي">سعر حالي</span>
                  <span v-if="row.unpriced_trips" class="badge danger" title="رحلات بلا سعر">بلا سعر {{ row.unpriced_trips }}</span>
                </td>
                <td style="font-weight:800;">{{ money(row.amount) }}</td>
                <td>
                  <button class="btn-days" @click="toggle(row.oracle_vehicle_id)">
                    {{ expanded[row.oracle_vehicle_id] ? 'إخفاء الأيام' : 'تفصيل يومي' }}
                  </button>
                </td>
              </tr>
              <tr v-if="expanded[row.oracle_vehicle_id]" class="days-row">
                <td colspan="9">
                  <table class="days-table">
                    <thead>
                      <tr><th>التاريخ</th><th>عدد الرحلات</th><th>ساعات العمل</th><th>المبلغ (شيكل)</th></tr>
                    </thead>
                    <tbody>
                      <tr v-for="day in row.days" :key="day.date">
                        <td>{{ day.date }}</td>
                        <td>{{ day.trips_count }}</td>
                        <td dir="ltr" style="text-align:right;">{{ day.work_time }}</td>
                        <td>{{ money(day.amount) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </template>
          </tbody>
          <tfoot v-if="rows.length">
            <tr class="total-row">
              <td colspan="4">الإجمالي</td>
              <td>{{ totals.trips }}</td>
              <td dir="ltr" style="text-align:right;">{{ totals.work_time }}</td>
              <td></td>
              <td>{{ money(totals.amount) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();

const props = defineProps({
  filters: Object,
  rows: Array,
  totals: Object,
  from: String,
  to: String,
});

const month = ref(props.filters?.month ?? '');
const dateFrom = ref(props.filters?.month ? '' : (props.filters?.date_from ?? ''));
const dateTo = ref(props.filters?.month ? '' : (props.filters?.date_to ?? ''));
const vehicleSearch = ref(props.filters?.vehicle_search ?? '');
const includeUnpriced = ref(Boolean(props.filters?.include_unpriced));
const expanded = reactive({});
let filterTimer = null;

function params() {
  const query = {};
  if (dateFrom.value && dateTo.value) {
    query.date_from = dateFrom.value;
    query.date_to = dateTo.value;
  } else if (month.value) {
    query.month = month.value;
  }
  if (vehicleSearch.value) query.vehicle_search = vehicleSearch.value;
  if (includeUnpriced.value) query.include_unpriced = 1;
  return query;
}

function applyFilters() {
  router.get(page.props.urls.vehicleClaims, params(), { preserveState: true, preserveScroll: true, replace: true });
}

function scheduleFilter() {
  clearTimeout(filterTimer);
  filterTimer = setTimeout(applyFilters, 350);
}

function onMonthChange() {
  dateFrom.value = '';
  dateTo.value = '';
  applyFilters();
}

function onRangeChange() {
  if (dateFrom.value && dateTo.value) {
    month.value = '';
    applyFilters();
  }
}

function download(baseUrl) {
  const url = new URL(baseUrl, window.location.origin);
  Object.entries(params()).forEach(([key, value]) => url.searchParams.set(key, value));
  window.open(url.toString(), '_blank');
}

function toggle(id) {
  expanded[id] = !expanded[id];
}

function money(value) {
  return Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style scoped>
.field { display: flex; flex-direction: column; gap: 6px; font-size: 12px; font-weight: 700; color: var(--text-secondary); }
.check { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: var(--text-primary); padding-bottom: 10px; }
.form-input {
  border: 1px solid var(--stroke);
  border-radius: 8px;
  padding: 9px 12px;
  font-size: 13px;
  font-family: 'Tajawal', sans-serif;
  background: var(--input-surface);
  color: var(--text-primary);
  transition: var(--transition);
  min-width: 210px;
}
.form-input.narrow { min-width: 150px; }
.form-input:focus { outline: none; border-color: var(--brand); }

.btn-export {
  border: none; cursor: pointer;
  border-radius: var(--radius-md);
  padding: 10px 16px; font-family: 'Tajawal', sans-serif;
  font-weight: 700; font-size: 13px; transition: var(--transition);
}
.btn-export.csv { background: #d1fae5; color: #065f46; }
.btn-export.csv:hover { background: #10b981; color: #fff; }
.btn-export.pdf { background: #fef3c7; color: #92400e; }
.btn-export.pdf:hover { background: #b45309; color: #fff; }

.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 16px; }
@media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
.stat-group { padding: 20px; }
.stat-item .label { font-size: 13px; color: var(--text-secondary); font-weight: 600; margin-bottom: 8px; }
.stat-item .value { font-size: 26px; font-weight: 800; }

.notice {
  margin-bottom: 16px; padding: 12px 16px; border-radius: 10px;
  background: #fef3c7; color: #92400e; font-size: 13px; font-weight: 600; line-height: 1.8;
}

.btn-days {
  border: 1px solid rgba(37, 99, 235, 0.18); border-radius: 8px; cursor: pointer;
  padding: 6px 10px; font-family: 'Tajawal', sans-serif; font-size: 12px; font-weight: 800;
  color: #1d4ed8; background: #dbeafe; transition: var(--transition);
}
.btn-days:hover { background: var(--brand); color: #fff; }

.days-row > td { background: var(--surface-strong); padding: 8px 24px 16px; }
.days-table { width: 100%; max-width: 640px; }
.days-table th, .days-table td { font-size: 12px; padding: 6px 10px; }
.total-row td { font-weight: 800; border-top: 2px solid var(--stroke); }
</style>
