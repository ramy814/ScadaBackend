<template>
  <AppLayout>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
      <div>
        <h2 style="font-size:22px;font-weight:800;margin:0 0 4px;">تفاصيل الرحلة #{{ trip.id }}</h2>
        <p style="color:var(--text-secondary);margin:0;font-size:14px;">تتبع المسار ومراجعة التوقفات والنقاط المستلمة</p>
      </div>
      <Link :href="$page.props.urls.dashboard" class="btn-back">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        العودة للداشبورد
      </Link>
    </div>

    <!-- Stats -->
    <div class="stats-grid-5">
      <div class="stat-card"><div class="label">النقاط</div><div class="value" :style="pointsColor">{{ trip.points_received }} / {{ trip.points_expected }}</div></div>
      <div class="stat-card"><div class="label">المسافة المسجّلة</div><div class="value">{{ recordedDistanceKm }} <span class="unit">كم</span></div></div>
      <div v-if="hasFilteredDistance" class="stat-card filtered-distance-card"><div class="label">المسافة المنقّاة</div><div class="value">{{ filteredDistanceKm }} <span class="unit">كم</span></div></div>
      <div class="stat-card"><div class="label">{{ qualityVersion >= 2 ? 'المدة المعتمدة' : 'مدة الرحلة' }}</div><div class="value">{{ tripDurationMinutes ?? '-' }} <span class="unit">دقيقة</span></div></div>
      <div class="stat-card"><div class="label">وقت الانتظار</div><div class="value" :style="totalWait > 0 ? 'color:var(--warn)' : ''">{{ totalWait > 0 ? totalWait + ' دقيقة' : 'لا يوجد' }}</div></div>
      <div class="stat-card">
        <div class="label">{{ fuelEstimateIsProvisional ? 'الوقود الافتراضي التقديري' : qualityVersion >= 2 ? 'الوقود التقديري المنقّى' : 'الوقود الافتراضي' }}</div>
        <div class="value">{{ estimatedFuelLiters ?? '-' }} <span class="unit">لتر</span></div>
        <div v-if="fuelEstimateIsProvisional" class="stat-hint">محسوب من المسافة المنقّاة للمراجعة</div>
      </div>
    </div>

    <div v-if="fuelEstimateIsProvisional" class="alert-box provisional-fuel-note">
      <strong>احتساب الوقود الافتراضي:</strong>
      {{ filteredDistanceKm }} كم × {{ fuelRatePerKm }} لتر/كم = {{ estimatedFuelLiters }} لتر.
      هذه قيمة تقديرية للمراجعة بسبب عدم وجود قيمة وقود مخزنة، ولم يتم تعديل سجل الرحلة.
    </div>

    <div v-if="qualityVersion < 2 && hasFilteredDistance" class="alert-box filtering-note">
      حُسبت المسافة المنقّاة لهذه الرحلة القديمة لأن بيانات دقة GPS متوفرة بشكل كافٍ. بقيت النقاط والمسافة الأصلية والوقود المخزن دون تعديل.
    </div>
    <div v-else-if="!hasFilteredDistance" class="alert-box filtering-note">
      تُعرض المسافة المسجّلة فقط لأن بيانات دقة GPS غير كافية لاستخراج مسافة منقّاة موثوقة.
    </div>

    <div v-if="qualityMessages.length || rejectedPoints || collapsedPoints" class="alert-box" style="border-right:4px solid var(--warn);margin-bottom:24px;">
      <strong style="display:block;color:var(--warn);margin-bottom:6px;">معالجة جودة البيانات</strong>
      <div v-if="qualityMessages.length">{{ qualityMessages.join('، ') }}</div>
      <div style="font-size:12px;color:var(--text-secondary);margin-top:6px;">
        القيم الخام للتدقيق: {{ rawDistanceKm }} كم، {{ reportedTripDurationMinutes ?? '-' }} دقيقة.
        {{ qualityProcessingText }}
      </div>
      <div v-if="rejectionSummary.length" style="font-size:12px;color:var(--text-secondary);margin-top:4px;">
        أسباب الاستبعاد الفعلي: {{ rejectionSummary.join('، ') }}.
      </div>
    </div>

    <div v-if="gapReviewCount" class="gap-estimate-panel">
      <div class="gap-estimate-header">
        <div>
          <strong>تقدير محافظ لفجوات التتبع</strong>
          <span>{{ estimatedGapCount }} فجوة قابلة للتقدير<span v-if="unestimatedGapCount">، وتعذّر تقدير {{ unestimatedGapCount }}</span></span>
        </div>
        <span class="badge warn">للمراجعة الإشرافية فقط</span>
      </div>
      <div v-if="estimatedGapCount" class="gap-estimate-grid">
        <div><span>المسافة المقدّرة داخل الفجوات</span><strong>{{ estimatedGapDistanceKm }} كم</strong></div>
        <div><span>المسافة بعد إضافة الفجوات القابلة للتقدير</span><strong>{{ estimatedDistanceWithGapsKm }} كم</strong></div>
        <div><span>السولار بعد إضافة الفجوات القابلة للتقدير</span><strong>{{ estimatedFuelWithGapsLiters }} لتر</strong></div>
      </div>
      <p>
        التقدير هو خط مستقيم بين طرفي كل فجوة موثوقين، ويُرفض إذا تطلّب سرعة غير منطقية.
        يمثل حدًا أدنى تقريبيًا وليس المسار الفعلي؛ لا يغيّر المسافة أو السولار الرسميين، ويقرر المشرف مدى الاسترشاد به عند المراجعة.
      </p>
      <p v-if="!estimatedGapCount">لا توجد فجوة داخلية بطرفين موثوقين تسمح باستخراج قيمة تقديرية.</p>
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
    <div class="panel trip-map-panel">
      <div class="panel-header map-panel-header">
        <div>
          <div>مسار الرحلة</div>
          <div class="map-panel-subtitle">{{ hasFilteredDistance ? 'عرض مقاطع GPS المقبولة دون وصل فجوات التتبع' : 'عرض نقاط GPS الأصلية بالترتيب الزمني' }}</div>
        </div>
        <div class="map-header-actions">
          <span class="badge neutral">{{ pointsCount }} نقطة مسجلة</span>
        </div>
      </div>
      <div class="map-legend" aria-label="دليل مسار الرحلة">
        <div class="map-legend-item">
          <span class="map-legend-marker start" aria-hidden="true"></span>
          <div><span>بداية الرحلة</span><strong>{{ trip.origin_text || 'أول نقطة مسجلة' }}</strong></div>
        </div>
        <div class="map-legend-item">
          <span class="map-legend-marker end" aria-hidden="true"></span>
          <div><span>نهاية الرحلة</span><strong>{{ trip.destination_text || 'آخر نقطة مسجلة' }}</strong></div>
        </div>
        <div class="map-legend-item route">
          <span class="map-legend-route" aria-hidden="true"></span>
          <div>
            <span>المسار الفعلي</span>
            <strong>{{ hasFilteredDistance ? 'حسب نقاط GPS المقبولة' : 'حسب نقاط GPS المسجلة' }}</strong>
          </div>
        </div>
        <div v-if="gapConnectors.length" class="map-legend-item route">
          <span class="map-legend-route gap" aria-hidden="true"></span>
          <div>
            <span>فجوة في التتبع</span>
            <strong>للعرض فقط وغير محتسبة</strong>
          </div>
        </div>
      </div>
      <div v-if="hasFilteredDistance" class="map-integrity-note">
        الأزرق هو المسار المحتسب، والخط البرتقالي المتقطع يوضح فجوات GPS ولا يدخل في المسافة أو الوقود. تبقى جميع العينات الخام محفوظة للتدقيق.
      </div>
      <div class="map-stage">
        <div ref="mapEl" class="trip-map" aria-label="خريطة مسار الرحلة"></div>
        <div class="map-floating-panel">
          <div class="map-direction-key" aria-label="دليل اتجاه الحركة">
            <span><i class="outbound" aria-hidden="true"></i>{{ routeHasReturn ? 'اتجاه الذهاب' : 'اتجاه الحركة' }}</span>
            <span v-if="routeHasReturn"><i class="return" aria-hidden="true"></i>اتجاه العودة</span>
            <strong>الأسهم توضح اتجاه السير</strong>
          </div>
        </div>
        <div v-if="!mappedSegments.length" class="map-empty-state">
          {{ hasFilteredDistance ? 'لا توجد نقاط GPS مقبولة كافية لعرض مسار هذه الرحلة.' : 'لا توجد نقاط GPS مسجلة كافية لعرض مسار هذه الرحلة.' }}
        </div>
        <button v-if="mappedSegments.length" type="button" class="map-fit-button" @click="fitRouteBounds">
          عرض المسار كاملاً
        </button>
      </div>
    </div>

    <!-- Stops Table -->
    <div class="panel">
      <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div>التوقفات المسجلة</div>
        <div class="stops-summary">
          <span :class="['badge', trip.stops?.length > 0 ? 'warn' : 'neutral']">{{ trip.stops?.length ?? 0 }} توقف</span>
          <span class="stops-total">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <circle cx="12" cy="12" r="9" />
              <path d="M12 7v5l3 2" />
            </svg>
            إجمالي الوقت: <strong>{{ totalWait }}</strong> دقيقة
          </span>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>وقت بدء التوقف</th><th>وقت الاستئناف</th><th>المدة</th><th>الموقع</th><th>المصدر</th><th>السبب</th><th>ملاحظات</th></tr></thead>
          <tbody>
            <tr v-if="!trip.stops?.length">
              <td colspan="8" style="text-align:center;padding:32px;">
                <span v-if="trip.wait_minutes > 0" style="color:var(--warn);font-weight:600;">تم تسجيل {{ trip.wait_minutes }} دقيقة انتظار دون توقفات محددة.</span>
                <span v-else style="color:var(--text-secondary);">لم يسجل السائق أي توقف.</span>
              </td>
            </tr>
            <tr v-for="(stop, i) in trip.stops" :key="stop.id">
              <td style="font-weight:700;color:var(--text-secondary);">{{ i + 1 }}</td>
              <td class="mono">{{ formatDate(stop.started_at) }}</td>
              <td class="mono">{{ formatDate(stop.ended_at) }}</td>
              <td><span v-if="stopDuration(stop) !== null" class="badge warn">{{ stopDuration(stop) }} دقيقة</span><span v-else style="color:var(--text-secondary);">-</span></td>
              <td class="mono">{{ stopCoordinates(stop) }}</td>
              <td><span :class="['badge', stop.source === 'automatic' ? 'ok' : 'neutral']">{{ stop.source === 'automatic' ? 'تلقائي' : 'يدوي/قديم' }}</span></td>
              <td><span v-if="stop.reason" class="badge neutral">{{ stop.reason }}</span><span v-else style="color:var(--text-secondary);">-</span></td>
              <td style="color:var(--text-secondary);">{{ stop.notes ?? '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div>أحداث سلامة التتبع</div>
        <span :class="['badge', trip.tracking_events?.length > 0 ? 'warn' : 'ok']">{{ trip.tracking_events?.length ?? 0 }} حدث</span>
      </div>
      <div class="alert-box" style="margin:16px 24px;">
        هذه أحداث تقنية موثقة من الجهاز أو فجوات اتصال، وتحتاج مراجعة المشرف؛ لا تثبت وحدها أن السائق تعمّد إيقاف التتبع.
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>الوقت</th><th>الحدث</th><th>التفاصيل</th><th>الموقع المتاح</th></tr></thead>
          <tbody>
            <tr v-if="!trip.tracking_events?.length"><td colspan="5" style="text-align:center;padding:32px;color:var(--text-secondary);">لم تسجل أحداث تعطيل أو انقطاع صريحة.</td></tr>
            <tr v-for="(event, i) in trip.tracking_events" :key="event.id">
              <td>{{ i + 1 }}</td>
              <td class="mono">{{ formatDate(event.detected_at) }}</td>
              <td><span :class="['badge', trackingEventClass(event.event_type)]">{{ trackingEventLabel(event.event_type) }}</span></td>
              <td>{{ trackingEventDetails(event) }}</td>
              <td class="mono">{{ stopCoordinates(event) }}</td>
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
import { ref, computed, onBeforeUnmount, onMounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();

const props = defineProps({
  trip: { type: Object, required: true },
  qualityVersion: { type: Number, default: 0 },
  hasFilteredDistance: { type: Boolean, default: false },
  pointsCount: { type: Number, default: 0 },
  mappedSegments: { type: Array, default: () => [] },
  gapConnectors: { type: Array, default: () => [] },
  estimatedGapDistanceKm: { type: Number, default: 0 },
  estimatedDistanceWithGapsKm: { type: Number, default: 0 },
  estimatedFuelWithGapsLiters: { type: Number, default: 0 },
  estimatedGapCount: { type: Number, default: 0 },
  unestimatedGapCount: { type: Number, default: 0 },
  recordedStart: { type: Object, default: null },
  recordedEnd: { type: Object, default: null },
  distanceKm: Number,
  filteredDistanceKm: Number,
  recordedDistanceKm: Number,
  rawDistanceKm: Number,
  stopsMinutes: Number,
  tripDurationMinutes: Number,
  reportedTripDurationMinutes: Number,
  qualityFlags: { type: Array, default: () => [] },
  rejectedPoints: { type: Number, default: 0 },
  collapsedPoints: { type: Number, default: 0 },
  rejectionCounts: { type: Object, default: () => ({}) },
  estimatedFuelLiters: Number,
  fuelEstimateIsProvisional: { type: Boolean, default: false },
  fuelRatePerKm: { type: Number, default: 0.12 },
});

const mapEl = ref(null);
const approveNote = ref('');
const rejectNote = ref('');
const clarifyNote = ref('');
const routeHasReturn = ref(false);
let mapInstance = null;
let routeLayer = null;
let routeBounds = null;
let liveRefreshTimer = null;

const totalWait = computed(() =>
  Math.round(Math.max(props.stopsMinutes ?? 0, props.trip.wait_minutes ?? 0))
);
const gapReviewCount = computed(() =>
  props.estimatedGapCount + props.unestimatedGapCount
);

const qualityLabels = {
  poor_accuracy: 'نقاط GPS منخفضة الدقة',
  impossible_speed: 'سرعات GPS غير منطقية',
  point_outside_trip_time: 'نقاط خارج زمن الرحلة',
  out_of_order_time: 'توقيت نقاط غير مرتب',
  tracking_gap: 'انقطاع طويل في التتبع',
  tracking_coverage_gap: 'فجوة تتبع شديدة تمنع اعتماد الوقود',
  gps_noise: 'اهتزاز GPS أثناء الوقوف',
  gps_jump: 'قفزات GPS مستبعدة',
  invalid_duration: 'مدة غير صحيحة',
  duration_over_12h: 'المدة المبلّغ عنها تجاوزت 12 ساعة',
  invalid_point: 'نقاط غير صالحة',
  mocked_location: 'موقع وهمي أو محاكى مستبعد',
  stale_or_future_timestamp: 'وقت GPS قديم أو مستقبلي',
  client_rejected: 'عينات رفضها فحص الهاتف',
  no_gps_points: 'لم تُسجل أي نقاط GPS',
  no_valid_points: 'لم توجد نقاط GPS موثوقة للحساب',
  distance_over_policy: 'المسافة تجاوزت 200 كم وتحتاج مراجعة قبل اعتماد الوقود',
  incomplete_upload: 'رفع نقاط الرحلة غير مكتمل؛ لن يُعتمد الوقود قبل اكتماله',
};
const qualityMessages = computed(() =>
  props.qualityFlags.map(flag => qualityLabels[flag] ?? flag)
);

const rejectionLabels = {
  invalid_point: 'إحداثيات غير صالحة',
  mocked_location: 'موقع وهمي',
  stale_or_future_timestamp: 'وقت GPS غير صالح',
  poor_accuracy: 'دقة أضعف من 50م',
  impossible_speed: 'سرعة غير منطقية',
  point_outside_trip_time: 'خارج زمن الرحلة',
  out_of_order_time: 'وقت راجع فعلياً',
  gps_jump: 'قفزة مكانية غير منطقية',
  client_rejected: 'مرفوضة من فحص الهاتف',
};
const rejectionSummary = computed(() =>
  Object.entries(props.rejectionCounts)
    .filter(([, count]) => Number(count) > 0)
    .map(([reason, count]) => `${rejectionLabels[reason] ?? reason}: ${count}`)
);
const qualityProcessingText = computed(() => {
  const parts = [];
  if (props.rejectedPoints > 0) {
    parts.push(`استُبعدت ${props.rejectedPoints} نقطة غير موثوقة من الحساب`);
  }
  if (props.collapsedPoints > 0) {
    parts.push(`اختُزلت ${props.collapsedPoints} عينة مكررة أو ساكنة دون اعتبارها تالفة`);
  }
  if (!parts.length) return 'لم تُستبعد أو تُختزل أي نقطة.';
  return `${parts.join('، ')}، ولم تُحذف أي عينة من السجل.`;
});

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
function stopCoordinates(item) {
  if (item.latitude === null || item.latitude === undefined || item.longitude === null || item.longitude === undefined) return '-';
  return `${Number(item.latitude).toFixed(5)}, ${Number(item.longitude).toFixed(5)}`;
}
function trackingEventLabel(type) {
  return ({
    gps_disabled: 'تم تعطيل GPS',
    gps_enabled: 'أعيد تفعيل GPS',
    airplane_mode_on: 'تم تفعيل وضع الطيران',
    airplane_mode_off: 'تم إيقاف وضع الطيران',
    device_shutdown: 'تم إطفاء الجهاز',
    device_boot: 'أعيد تشغيل الجهاز',
    battery_low: 'البطارية منخفضة',
    location_permission_missing: 'تم إيقاف إذن الموقع',
    location_permission_restored: 'أعيد إذن الموقع',
    tracking_error: 'خطأ في خدمة التتبع',
    tracking_gap: 'انقطاع طويل في GPS',
    clock_anomaly: 'خلل في وقت الجهاز',
  })[type] ?? type;
}
function trackingEventClass(type) {
  if (['gps_enabled', 'airplane_mode_off', 'device_boot', 'location_permission_restored'].includes(type)) return 'ok';
  if (type === 'battery_low' || type === 'tracking_error') return 'warn';
  return 'danger';
}
function trackingEventDetails(event) {
  if (!event.details_json) return '-';
  let details;
  try {
    details = typeof event.details_json === 'string'
      ? JSON.parse(event.details_json)
      : event.details_json;
  } catch {
    return '-';
  }
  if (event.event_type === 'tracking_gap' && details.reason === 'provider_silent') {
    const seconds = Number(details.silence_seconds);
    const duration = Number.isFinite(seconds) ? ` بعد ${seconds} ثانية` : '';
    return `توقف مزود GPS عن إرسال النقاط${duration}؛ تم التحويل إلى GPS المباشر وإعادة المحاولة.`;
  }
  if (event.event_type === 'airplane_mode_on' && details.detected_during === 'trip_start') {
    return 'كان وضع الطيران مفعّلاً عند إنشاء الرحلة؛ استمر الحفظ المحلي وتأجل الرفع.';
  }
  if (event.event_type === 'airplane_mode_on' && details.detected_during === 'active_trip_restore') {
    return 'كان وضع الطيران مفعّلاً عند استعادة الرحلة النشطة.';
  }
  return '-';
}
function sourceLabel(s) {
  return s === 'free_text' ? 'نص حر' : s === 'employee_list' ? 'من قائمة الموظفين' : s === 'manual' ? 'يدوي' : s;
}
function sourceClass(s) {
  return s === 'employee_list' ? 'ok' : s === 'manual' ? 'warn' : 'neutral';
}

function submitApprove() {
  router.post(`${page.props.urls.trips_base}/${props.trip.id}/approve`, { note: approveNote.value, _from: 'show' });
}
function confirmReject() {
  if (!confirm('هل تريد رفض هذه الرحلة؟')) return;
  router.post(`${page.props.urls.trips_base}/${props.trip.id}/reject`, { note: rejectNote.value, _from: 'show' });
}
function submitClarify() {
  if (!clarifyNote.value) return;
  router.post(`${page.props.urls.trips_base}/${props.trip.id}/clarify`, { note: clarifyNote.value, _from: 'show' });
}

function fitRouteBounds() {
  if (!mapInstance || !routeBounds?.isValid()) return;

  if (routeBounds.getNorthEast().equals(routeBounds.getSouthWest())) {
    mapInstance.setView(routeBounds.getCenter(), 17);
    return;
  }

  mapInstance.fitBounds(routeBounds, {
    paddingTopLeft: [56, 72],
    paddingBottomRight: [56, 56],
    maxZoom: 18,
  });
}

onMounted(async () => {
  if (!props.trip.ended_at) {
    liveRefreshTimer = window.setInterval(() => {
      router.reload({ preserveScroll: true, preserveState: false });
    }, 15000);
  }
  if (!mapEl.value) return;
  const L = (await import('leaflet')).default;
  await import('leaflet/dist/leaflet.css');
  await import('leaflet-polylinedecorator');

  mapInstance = L.map(mapEl.value, {
    preferCanvas: true,
    zoomControl: false,
  });
  mapInstance.setView([31.5017, 34.4668], 12);
  L.control.zoom({ position: 'bottomleft' }).addTo(mapInstance);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap',
  }).addTo(mapInstance);

  if (!props.mappedSegments.length) return;
  const filteredSegments = props.mappedSegments
    .map(segment => segment.map(point => [point.lat, point.lng]))
    .filter(segment => segment.length);
  const routeStyle = {
    lineCap: 'round',
    lineJoin: 'round',
    smoothFactor: 1.25,
  };
  const markerStyle = {
    radius: 10,
    color: '#ffffff',
    weight: 4,
    opacity: 1,
    fillOpacity: 1,
  };

  function splitRouteDirections(segments) {
    const points = segments.flat();
    if (points.length < 4) return { parts: segments.map(segment => ({ points: segment, leg: 'outbound' })), hasReturn: false };

    const start = L.latLng(points[0]);
    let furthestIndex = 0;
    let furthestDistance = 0;
    points.forEach((point, index) => {
      const distance = start.distanceTo(L.latLng(point));
      if (distance > furthestDistance) {
        furthestDistance = distance;
        furthestIndex = index;
      }
    });

    const endDistance = start.distanceTo(L.latLng(points[points.length - 1]));
    const hasReturn = furthestDistance >= 100
      && endDistance <= furthestDistance * 0.65
      && furthestIndex >= points.length * 0.15
      && furthestIndex <= points.length * 0.85;

    if (!hasReturn) return { parts: segments.map(segment => ({ points: segment, leg: 'outbound' })), hasReturn: false };

    const parts = [];
    let globalIndex = 0;
    segments.forEach(segment => {
      const segmentStart = globalIndex;
      const segmentEnd = globalIndex + segment.length - 1;

      if (segmentEnd <= furthestIndex) {
        parts.push({ points: segment, leg: 'outbound' });
      } else if (segmentStart >= furthestIndex) {
        parts.push({ points: segment, leg: 'return' });
      } else {
        const splitIndex = furthestIndex - segmentStart;
        parts.push({ points: segment.slice(0, splitIndex + 1), leg: 'outbound' });
        parts.push({ points: segment.slice(splitIndex), leg: 'return' });
      }

      globalIndex += segment.length;
    });

    return { parts: parts.filter(part => part.points.length >= 2), hasReturn: true };
  }

  function buildRouteLayer(segments) {
    const group = L.layerGroup();
    const bounds = L.latLngBounds([]);
    const directionalRoute = splitRouteDirections(segments);

    directionalRoute.parts.forEach(({ points: segment, leg }) => {
      if (!segment.length) return;
      bounds.extend(segment);

      if (segment.length < 2) return;
      const routeColor = leg === 'return' ? '#ea580c' : '#2563eb';

      // The white casing keeps each trace distinct from roads and map labels.
      L.polyline(segment, {
        ...routeStyle,
        color: '#ffffff',
        weight: 11,
        opacity: 0.96,
        interactive: false,
      }).addTo(group);
      L.polyline(segment, {
        ...routeStyle,
        color: routeColor,
        weight: 6,
        opacity: 1,
      }).addTo(group);
      L.polylineDecorator(segment, {
        patterns: [{
          offset: 45,
          repeat: 90,
          symbol: L.Symbol.arrowHead({
            pixelSize: 9,
            polygon: true,
            pathOptions: {
              color: '#ffffff',
              fillColor: '#ffffff',
              fillOpacity: 1,
              opacity: 1,
              weight: 1,
              interactive: false,
            },
          }),
        }],
      }).addTo(group);
    });

    props.gapConnectors.forEach((connector) => {
      if (!connector?.from || !connector?.to) return;
      const gapPoints = [
        [connector.from.lat, connector.from.lng],
        [connector.to.lat, connector.to.lng],
      ];
      bounds.extend(gapPoints);
      const minutes = Math.max(1, Math.round((connector.seconds ?? 0) / 60));
      const estimateText = connector.estimate_usable
        ? `تقدير مباشر ${Number(connector.estimated_distance_km ?? 0).toFixed(2)} كم`
        : 'تعذّر تقديرها بشكل موثوق';
      L.polyline(gapPoints, {
        ...routeStyle,
        color: '#d97706',
        weight: 4,
        opacity: 0.9,
        dashArray: '10 12',
      })
        .bindTooltip(`فجوة GPS قرابة ${minutes} دقيقة — ${estimateText} — غير محتسبة رسميًا`)
        .addTo(group);
    });

    const start = props.recordedStart
      ? [props.recordedStart.lat, props.recordedStart.lng]
      : segments[0]?.[0];
    const lastSegment = segments[segments.length - 1];
    const end = props.recordedEnd
      ? [props.recordedEnd.lat, props.recordedEnd.lng]
      : lastSegment?.[lastSegment.length - 1];

    if (start && end) {
      bounds.extend([start, end]);
      L.circleMarker(start, { ...markerStyle, fillColor: '#059669' })
        .bindTooltip('بداية الرحلة', {
          permanent: true,
          direction: 'bottom',
          offset: [0, 14],
          className: 'trip-map-tooltip trip-map-tooltip-start',
        })
        .bindPopup('أول نقطة GPS مسجلة')
        .addTo(group);
      L.circleMarker(end, { ...markerStyle, fillColor: '#dc2626' })
        .bindTooltip('نهاية الرحلة', {
          permanent: true,
          direction: 'top',
          offset: [0, -14],
          className: 'trip-map-tooltip trip-map-tooltip-end',
        })
        .bindPopup('آخر نقطة GPS مسجلة')
        .addTo(group);
    }

    (props.trip.stops ?? []).forEach((stop) => {
      if (stop.latitude === null || stop.latitude === undefined || stop.longitude === null || stop.longitude === undefined) return;
      const point = [Number(stop.latitude), Number(stop.longitude)];
      bounds.extend(point);
      L.circleMarker(point, {
        radius: 8,
        color: '#ffffff',
        weight: 3,
        fillColor: '#f59e0b',
        fillOpacity: 1,
      })
        .bindTooltip(`نقطة انتظار — ${stopDuration(stop) ?? 0} دقيقة`)
        .addTo(group);
    });

    return { group, bounds, hasReturn: directionalRoute.hasReturn };
  }

  const filteredRoute = buildRouteLayer(filteredSegments);
  routeLayer = filteredRoute.group;
  routeBounds = filteredRoute.bounds;
  routeHasReturn.value = filteredRoute.hasReturn;
  routeLayer.addTo(mapInstance);
  fitRouteBounds();
});

onBeforeUnmount(() => {
  if (liveRefreshTimer) window.clearInterval(liveRefreshTimer);
  mapInstance?.remove();
  mapInstance = null;
  routeLayer = null;
  routeBounds = null;
  routeHasReturn.value = false;
});
</script>

<style scoped>
.stats-grid-5 { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:16px; margin-bottom:24px; }
.stat-card { background:var(--surface); border-radius:var(--radius-lg); padding:20px; box-shadow:var(--shadow-md); }
.stat-card .label { font-size:13px; color:var(--text-secondary); font-weight:600; }
.stat-card .value { font-size:26px; font-weight:800; margin-top:8px; }
.filtered-distance-card { border:1px solid color-mix(in srgb,var(--ok) 32%,transparent); }
.filtered-distance-card .value { color:var(--ok); }
.filtering-note { margin-top:-8px; margin-bottom:24px; border-right:4px solid var(--brand); }
.provisional-fuel-note { margin-top:-8px; margin-bottom:24px; border-right:4px solid var(--warn); }
.gap-estimate-panel { margin-bottom:24px; border:1px solid color-mix(in srgb,var(--warn) 34%,transparent); border-radius:var(--radius-lg); padding:18px; background:color-mix(in srgb,var(--warn) 7%,var(--surface)); box-shadow:var(--shadow-md); }
.gap-estimate-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:14px; }
.gap-estimate-header > div { display:flex; flex-direction:column; gap:4px; }
.gap-estimate-header strong { font-size:15px; }
.gap-estimate-header span:not(.badge) { color:var(--text-secondary); font-size:12px; }
.gap-estimate-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
.gap-estimate-grid > div { display:flex; flex-direction:column; gap:5px; border:1px solid var(--stroke); border-radius:var(--radius-md); padding:12px; background:var(--surface); }
.gap-estimate-grid span { color:var(--text-secondary); font-size:11px; font-weight:700; }
.gap-estimate-grid strong { color:var(--warn); font-size:18px; }
.gap-estimate-panel p { margin:12px 0 0; color:var(--text-secondary); font-size:12px; line-height:1.7; }
.stat-hint { margin-top:6px; color:var(--warn); font-size:11px; font-weight:700; }
.unit { font-size:14px; color:var(--text-secondary); }

.two-cols { display:grid; grid-template-columns:1.3fr 1fr; gap:24px; margin-bottom:24px; }
.panel { background:var(--surface); border-radius:var(--radius-lg); box-shadow:var(--shadow-lg); overflow:hidden; margin-bottom:24px; }
.panel-header { padding:16px 24px; border-bottom:1px solid var(--stroke); font-size:16px; font-weight:800; }
.panel-body { padding:24px; }
.stops-summary { display:flex; align-items:center; justify-content:flex-end; gap:8px; flex-wrap:wrap; }
.stops-total { display:inline-flex; align-items:center; gap:5px; border:1px solid color-mix(in srgb,var(--warn) 32%,transparent); border-radius:999px; padding:6px 10px; background:color-mix(in srgb,var(--warn) 10%,transparent); color:var(--warn); font-size:12px; font-weight:700; }
.stops-total svg { width:15px; height:15px; flex:0 0 auto; }
.stops-total strong { font-size:14px; }
.map-panel-header { display:flex; justify-content:space-between; align-items:center; gap:16px; }
.map-panel-subtitle { color:var(--text-secondary); font-size:12px; font-weight:500; margin-top:4px; }
.map-header-actions { display:flex; align-items:center; justify-content:flex-end; gap:12px; flex-wrap:wrap; }
.map-floating-panel { position:absolute; top:16px; right:16px; z-index:500; display:flex; flex-direction:column; gap:8px; padding:8px; border:1px solid rgba(15,23,42,0.14); border-radius:12px; background:rgba(255,255,255,0.96); box-shadow:0 6px 18px rgba(15,23,42,0.14); }
.map-direction-key { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:2px 4px 0; color:var(--text-secondary); font-size:10px; font-weight:700; }
.map-direction-key span { display:inline-flex; align-items:center; gap:5px; }
.map-direction-key i { display:block; width:20px; height:4px; border-radius:999px; }
.map-direction-key i.outbound { background:#2563eb; }
.map-direction-key i.return { background:#ea580c; }
.map-direction-key strong { color:var(--text-primary); font-size:10px; }
.map-legend { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; padding:14px 24px; background:var(--surface-strong); border-bottom:1px solid var(--stroke); }
.map-legend-item { display:flex; align-items:center; gap:10px; min-width:0; }
.map-legend-item > div { display:flex; flex-direction:column; min-width:0; }
.map-legend-item span:not(.map-legend-marker):not(.map-legend-route) { color:var(--text-secondary); font-size:11px; font-weight:600; }
.map-legend-item strong { font-size:13px; line-height:1.4; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.map-legend-marker { width:16px; height:16px; border:3px solid #fff; border-radius:50%; box-shadow:0 0 0 1px rgba(15,23,42,0.16), 0 2px 5px rgba(15,23,42,0.18); flex:0 0 auto; }
.map-legend-marker.start { background:#059669; }
.map-legend-marker.end { background:#dc2626; }
.map-legend-route { width:34px; height:6px; border-radius:999px; background:#2563eb; box-shadow:0 0 0 3px #fff, 0 0 0 4px rgba(15,23,42,0.12); flex:0 0 auto; }
.map-legend-route.gap { height:4px; border-radius:0; background:repeating-linear-gradient(90deg,#d97706 0 8px,transparent 8px 13px); box-shadow:none; }
.map-integrity-note { padding:9px 24px; border-bottom:1px solid var(--stroke); background:var(--surface-strong); color:var(--text-secondary); font-size:12px; font-weight:600; }
.map-stage { position:relative; }
.trip-map { height:500px; width:100%; z-index:1; background:#e8eef5; }
.map-empty-state { position:absolute; top:50%; right:50%; z-index:500; width:max-content; max-width:calc(100% - 32px); transform:translate(50%,-50%); border:1px solid rgba(15,23,42,0.12); border-radius:10px; padding:10px 16px; background:rgba(255,255,255,0.96); color:var(--text-secondary); box-shadow:0 4px 12px rgba(15,23,42,0.1); font-size:13px; font-weight:700; text-align:center; }
.map-fit-button { position:absolute; bottom:16px; right:16px; z-index:500; border:1px solid rgba(15,23,42,0.14); border-radius:10px; padding:9px 13px; background:rgba(255,255,255,0.96); color:var(--text-primary); box-shadow:0 4px 12px rgba(15,23,42,0.12); font-family:'Tajawal',sans-serif; font-size:12px; font-weight:800; cursor:pointer; transition:var(--transition); }
.map-fit-button:hover { color:var(--brand-hover); border-color:rgba(37,99,235,0.32); transform:translateY(-1px); }
.trip-map-panel :deep(.leaflet-control-zoom a) { color:var(--text-primary); }
.trip-map-panel :deep(.trip-map-tooltip) { border:0; border-radius:8px; padding:6px 10px; color:#fff; font-family:'Tajawal',sans-serif; font-size:12px; font-weight:800; box-shadow:0 4px 12px rgba(15,23,42,0.2); direction:rtl; }
.trip-map-panel :deep(.trip-map-tooltip::before) { display:none; }
.trip-map-panel :deep(.trip-map-tooltip-start) { background:#047857; }
.trip-map-panel :deep(.trip-map-tooltip-end) { background:#b91c1c; }
.kv-group { display:flex; flex-direction:column; gap:16px; }
.kv-item { display:grid; grid-template-columns:140px 1fr; gap:12px; align-items:center; }
.kv-label { color:var(--text-secondary); font-size:14px; font-weight:600; }
.kv-val { font-size:14px; font-weight:700; }
.mono { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:13px; }
.alert-box { background:var(--surface-strong); border:1px solid var(--stroke); border-radius:var(--radius-md); padding:12px 16px; font-size:13px; line-height:1.5; }

.btn-back { border:none; border-radius:var(--radius-md); padding:10px 20px; background:var(--brand); color:#fff; text-decoration:none; font-weight:700; font-size:14px; display:flex; align-items:center; gap:8px; transition:var(--transition); }
.btn-back:hover { background:var(--brand-hover); transform:translateY(-1px); }

.review-panel { background:var(--surface); border-radius:var(--radius-lg); box-shadow:var(--shadow-lg); border:1px solid rgba(59,130,246,0.15); overflow:hidden; margin-bottom:24px; }
.review-panel-header { padding:16px 24px; border-bottom:1px solid var(--stroke); background:linear-gradient(135deg,rgba(59,130,246,0.06),rgba(139,92,246,0.04)); display:flex; align-items:center; justify-content:space-between; }
.review-actions { padding:24px; display:flex; gap:12px; flex-wrap:wrap; align-items:flex-start; }
.review-form { flex:1; min-width:280px; }
.review-textarea { width:100%; border:1px solid var(--stroke); border-radius:var(--radius-md); padding:12px 14px; font-family:'Tajawal',sans-serif; font-size:14px; resize:vertical; min-height:80px; background:var(--input-surface); color:var(--text-primary); outline:none; transition:var(--transition); }
.review-textarea:focus { border-color:var(--brand); background:var(--surface-strong); box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
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
@media (max-width:700px) {
  .gap-estimate-header { flex-direction:column; }
  .gap-estimate-grid { grid-template-columns:1fr; }
  .map-panel-header { align-items:flex-start; flex-direction:column; }
  .map-header-actions { width:100%; justify-content:space-between; }
  .map-floating-panel { top:12px; right:12px; left:12px; }
  .map-integrity-note { padding:10px 18px; }
  .map-legend { grid-template-columns:1fr; padding:14px 18px; }
  .trip-map { height:420px; }
  .map-fit-button { bottom:12px; right:12px; }
}
</style>
