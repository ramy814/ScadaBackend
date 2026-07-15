<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'
import DropdownSelect from '../../../WellsSystem/Components/Shared/DropdownSelect.vue'
import WellMediaFields from '../../../WellsSystem/Components/WellMediaFields.vue'

const props = defineProps({
    well: { type: Object, required: true },
    quarters: { type: Array, default: () => [] },
    generators: { type: Array, default: () => [] },
    readonly: { type: Boolean, default: false },
    hasTransactions: { type: Boolean, default: false },
})

function generatorOid(generator) {
    return Number(generator?.GENERATOR_OID ?? generator?.generator_oid ?? generator?.OID ?? generator?.oid)
}

function generatorName(generator) {
    const name = generator?.NAME ?? generator?.name ?? ''
    const number = generator?.GENERATOR_NUM ?? generator?.generator_num ?? ''

    return `${name} (${number})`
}

function generatorDetails(oid) {
    const generator = props.generators.find(g => generatorOid(g) === Number(oid))

    if (!generator) {
        return {
            name: `مولد #${oid}`,
            number: 'غير متوفر',
            currentReading: null,
            gallons: null,
        }
    }

    return {
        name: generator.NAME ?? generator.name ?? `مولد #${oid}`,
        number: generator.GENERATOR_NUM ?? generator.generator_num ?? 'غير متوفر',
        currentReading: generator.CURRENT_READING ?? generator.current_reading ?? null,
        gallons: generator.GALLONS ?? generator.gallons ?? null,
    }
}

function formatGeneratorValue(value, fallback = 'غير متوفر') {
    if (value === null || value === undefined || value === '') {
        return fallback
    }

    const number = Number(value)

    return Number.isFinite(number) ? number.toLocaleString('ar') : value
}

function initialGeneratorIds() {
    return (props.well.GENERATORS ?? props.well.generators ?? [])
        .map(generatorOid)
        .filter(Number.isFinite)
}

const displayWellNumber = computed(() => props.well.SOURCE_WELL_CODE || props.well.WELL_NUM || '')

function selectedOrEmpty(value, options) {
    return options.includes(value) ? value : ''
}

function chlorinationSystem(value) {
    if (value === 'يوجد' || value === 'لا يوجد') return value
    return value && !String(value).includes('لا يوجد') ? 'يوجد' : 'لا يوجد'
}

const form = reactive({
    // البيانات العامة
    WELL_NUM: props.well.WELL_NUM || '',
    NAME: props.well.NAME || '',
    QUARTER_OID: props.well.QUARTER_OID || '',
    DAMAGE_STATUS: props.well.DAMAGE_STATUS || '',
    OPERATIONAL_STATUS: props.well.OPERATIONAL_STATUS || '',
    INSTALLATION_DATE: props.well.INSTALLATION_DATE || '',
    LAST_MAINTENANCE_DATE: props.well.LAST_MAINTENANCE_DATE || '',
    // بيانات البئر والمضخة
    WELL_DEPTH: props.well.WELL_DEPTH || '',
    PUMP_TYPE: props.well.PUMP_TYPE === 'centrifugal' ? 'vertical' : (props.well.PUMP_TYPE || 'submersible'),
    PUMP_POWER_HP: props.well.PUMP_POWER_HP || '',
    CAPACITY: props.well.CAPACITY || '',
    SALINITY: props.well.SALINITY || '',
    // الطاقة والموقع
    POWER_SOURCE: props.well.POWER_SOURCE || 'electricity',
    LATITUDE: props.well.LATITUDE || '31.5',
    LONGITUDE: props.well.LONGITUDE || '34.47',
    IS_ACTIVE: props.well.IS_ACTIVE ?? true,
    generators: initialGeneratorIds(),
    // بيانات خزان الوقود
    TANK_SHAPE: props.well.TANK_SHAPE || '',
    TANK_MATERIAL: props.well.TANK_MATERIAL === 'بلاستيك مقوى'
        ? 'HDPE'
        : selectedOrEmpty(props.well.TANK_MATERIAL, ['HDPE', 'معدن', 'فيبرجلاس']),
    TANK_DIAMETER: props.well.TANK_DIAMETER || '',
    TANK_HEIGHT: props.well.TANK_HEIGHT || '',
    // نظام الكلورة
    CHLORINATION_SYSTEM: chlorinationSystem(props.well.CHLORINATION_SYSTEM),
    CHLORINATION_STATUS: selectedOrEmpty(props.well.CHLORINATION_STATUS, ['يعمل', 'لا يعمل']),
    CHLORINE_TANK_CAPACITY: props.well.CHLORINE_TANK_CAPACITY || 0,
    CHLORINE_TANK_TYPE: selectedOrEmpty(props.well.CHLORINE_TANK_TYPE, ['اسطواني عمودي', 'مكعب']),
    CHLORINE_TANK_MATERIAL: selectedOrEmpty(props.well.CHLORINE_TANK_MATERIAL, ['HDPE أزرق', 'بلاستيك شفاف']),
    CHLORINE_PUMP_TYPE: props.well.CHLORINE_PUMP_TYPE || '',
    // خزان التبريد
    COOLING_TANK_CAPACITY: [220, 500].includes(Number(props.well.COOLING_TANK_CAPACITY))
        ? Number(props.well.COOLING_TANK_CAPACITY)
        : '',
    COOLING_TANK_TYPE: selectedOrEmpty(props.well.COOLING_TANK_TYPE, ['HDPE أزرق', 'HDPE أسود']),
    COOLING_SYSTEM: selectedOrEmpty(props.well.COOLING_SYSTEM, ['اوتوماتيك', 'يدوي', 'غير متوفر', 'غاطس', 'اوتوماتيك يحتاج صيانة']),
    COOLING_LINE_TYPE: selectedOrEmpty(props.well.COOLING_LINE_TYPE, ['حديد سكب', 'HDPE']),
})

const errors = ref({})
const newMedia = ref([])
const mediaError = computed(() => Object.entries(errors.value)
    .find(([key]) => key === 'media' || key.startsWith('media.'))?.[1] || '')
let map = null
let marker = null

const selectedGeneratorOid = ref('')

const generatorOptions = computed(() => {
    return props.generators.map(gen => ({
        OID: generatorOid(gen),
        LABEL: generatorName(gen)
    }))
})

function addGenerator(generatorOid) {
    const oid = Number(generatorOid)

    if (Number.isFinite(oid) && !form.generators.includes(oid)) {
        form.generators.push(oid)
    }
}

function removeGenerator(index) {
    form.generators.splice(index, 1)
}

function getGeneratorName(oid) {
    const gen = props.generators.find(g => generatorOid(g) === Number(oid))

    return gen ? generatorName(gen) : `مولد #${oid}`
}

function submit() {
    router.post(`/wells/wells/${props.well.OID}`, {
        ...form,
        _method: 'put',
        generators: [...form.generators],
        media: newMedia.value,
    }, {
        forceFormData: true,
        onError: (err) => { errors.value = err },
    })
}

function deleteMedia(mediaOid) {
    router.delete(`/wells/wells/${props.well.OID}/media/${mediaOid}`, {
        preserveScroll: true,
    })
}

onMounted(() => {
    if (typeof L !== 'undefined') {
        initMap()
    } else {
        const link = document.createElement('link')
        link.rel = 'stylesheet'
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
        document.head.appendChild(link)

        const script = document.createElement('script')
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
        script.onload = () => initMap()
        document.head.appendChild(script)
    }
})

function initMap() {
    const lat = parseFloat(form.LATITUDE) || 31.5
    const lng = parseFloat(form.LONGITUDE) || 34.47

    map = L.map('location-map').setView([lat, lng], 14)
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map)

    marker = L.marker([lat, lng], { draggable: !props.readonly }).addTo(map)

    if (!props.readonly) {
        marker.on('dragend', () => {
            const pos = marker.getLatLng()
            form.LATITUDE = pos.lat.toFixed(8)
            form.LONGITUDE = pos.lng.toFixed(8)
        })

        map.on('click', (e) => {
            marker.setLatLng(e.latlng)
            form.LATITUDE = e.latlng.lat.toFixed(8)
            form.LONGITUDE = e.latlng.lng.toFixed(8)
        })
    }
}
</script>

<template>
    <AppLayout :title="readonly ? 'تفاصيل البئر' : 'تعديل البئر'">
        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h1 class="page-title">{{ readonly ? 'تفاصيل البئر' : 'تعديل البئر' }}</h1>
                    <p class="page-subtitle">{{ well.NAME }}</p>
                </div>
                <button class="btn btn-secondary" @click="router.visit('/wells/wells')">→ العودة للقائمة</button>
            </div>

            <form @submit.prevent="submit">

                <!-- ===== البيانات العامة ===== -->
                <div class="card" style="position: relative; z-index: 40; overflow: visible; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">ℹ️ البيانات العامة</h3>
                    </div>
                    <div class="card-body" style="overflow: visible;">
                        <!-- الصف الأول: رقم البئر، اسم البئر، المنطقة -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">رقم البئر</label>
                                <input :value="displayWellNumber" type="text" class="form-input" readonly disabled placeholder="أدخل رقم البئر">
                            </div>
                            <div class="form-group">
                                <label class="form-label">اسم البئر *</label>
                                <input v-model="form.NAME" type="text" class="form-input" :readonly="readonly" :disabled="hasTransactions" required>
                                <span v-if="errors.NAME" class="form-error">{{ errors.NAME }}</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label">المنطقة</label>
                                <DropdownSelect
                                    v-model="form.QUARTER_OID"
                                    :options="quarters"
                                    valueKey="OID"
                                    labelKey="NAME"
                                    placeholder="اختر المنطقة"
                                    :disabled="readonly || hasTransactions"
                                />
                            </div>
                        </div>

                        <!-- الصف الثاني: حالة الضرر، الحالة التشغيلية، تاريخ التركيب -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">حالة الضرر</label>
                                <select v-model="form.DAMAGE_STATUS" class="form-select" :disabled="readonly">
                                    <option value="">اختر حالة الضرر</option>
                                    <option value="سليمة">سليمة</option>
                                    <option value="متضررة">متضررة</option>
                                    <option value="أعيد تأهيلها">أعيد تأهيلها</option>
                                    <option value="مدمرة">مدمرة</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">الحالة التشغيلية</label>
                                <select v-model="form.OPERATIONAL_STATUS" class="form-select" :disabled="readonly">
                                    <option value="">اختر الحالة التشغيلية</option>
                                    <option value="يعمل">يعمل</option>
                                    <option value="متوقف">متوقف</option>
                                    <option value="تحت الصيانة">تحت الصيانة</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">تاريخ التركيب</label>
                                <input v-model="form.INSTALLATION_DATE" type="date" class="form-input" :readonly="readonly">
                            </div>
                        </div>

                        <!-- الصف الثالث: آخر صيانة وتفعيل البئر -->
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">آخر صيانة</label>
                                <input v-model="form.LAST_MAINTENANCE_DATE" type="date" class="form-input" :readonly="readonly">
                            </div>
                            <div class="form-group">
                                <label class="form-label">حالة التفعيل (IS_ACTIVE)</label>
                                <select v-model="form.IS_ACTIVE" class="form-select" :disabled="readonly">
                                    <option :value="true">فعال</option>
                                    <option :value="false">معطل (متوقف تماماً)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== بيانات البئر والمضخة ===== -->
                <div class="card" style="position: relative; z-index: 35; overflow: visible; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">🌊 بيانات البئر والمضخة</h3>
                    </div>
                    <div class="card-body" style="overflow: visible;">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">العمق (م)</label>
                                <input v-model="form.WELL_DEPTH" type="number" step="0.01" class="form-input" :readonly="readonly" placeholder="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">نوع المضخة *</label>
                                <select v-model="form.PUMP_TYPE" class="form-select" :disabled="readonly" required>
                                    <option value="submersible">غاطس (Submersible)</option>
                                    <option value="vertical">رأسي (Vertical)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">الإنتاجية (م³/س) *</label>
                                <input v-model="form.CAPACITY" type="number" step="0.01" class="form-input" :readonly="readonly" required>
                                <span v-if="errors.CAPACITY" class="form-error">{{ errors.CAPACITY }}</span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">قدرة المضخة (حصان)</label>
                                <input v-model="form.PUMP_POWER_HP" type="number" step="0.01" min="0" class="form-input" :readonly="readonly" placeholder="0">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">الملوحة</label>
                                <input v-model="form.SALINITY" type="number" step="0.01" class="form-input" :readonly="readonly" placeholder="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">مصدر الطاقة *</label>
                                <select v-model="form.POWER_SOURCE" class="form-select" :disabled="readonly" required>
                                    <option value="electricity">كهرباء</option>
                                    <option value="solar">سولار</option>
                                    <option value="generator">مولد</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== المولدات ===== -->
                <div class="card" style="position: relative; z-index: 30; margin-bottom: 20px; overflow: visible;">
                    <div class="card-header">
                        <h3 class="card-title">⚡ المولدات</h3>
                    </div>
                    <div class="card-body" style="overflow: visible;">
                        <div v-if="!readonly" class="form-group">
                            <label class="form-label">إضافة مولد</label>
                            <div style="display: flex; gap: 12px; align-items: flex-start;">
                                <div style="flex: 1;">
                                    <DropdownSelect
                                        v-model="selectedGeneratorOid"
                                        :options="generatorOptions"
                                        valueKey="OID"
                                        labelKey="LABEL"
                                        placeholder="اختر مولد"
                                        :disabled="readonly"
                                    />
                                </div>
                                <button type="button" class="btn btn-primary" @click="() => { addGenerator(selectedGeneratorOid); selectedGeneratorOid = ''; }">إضافة</button>
                            </div>
                        </div>
                        <div v-if="form.generators.length" class="generators-list">
                            <div v-for="(genOid, index) in form.generators" :key="genOid" class="generator-item">
                                <div class="generator-card-header">
                                    <div>
                                        <span class="generator-card-label">اسم المولد</span>
                                        <strong class="generator-card-title">{{ generatorDetails(genOid).name }}</strong>
                                    </div>
                                    <button v-if="!readonly" type="button" class="btn btn-sm btn-danger" @click="removeGenerator(index)">✕</button>
                                </div>

                                <div class="generator-card-grid">
                                    <div class="generator-card-metric">
                                        <span>رقم المولد</span>
                                        <strong>{{ generatorDetails(genOid).number }}</strong>
                                    </div>
                                    <div class="generator-card-metric">
                                        <span>قراءة العداد</span>
                                        <strong>{{ formatGeneratorValue(generatorDetails(genOid).currentReading) }}</strong>
                                    </div>
                                    <div class="generator-card-metric">
                                        <span>كمية السولار</span>
                                        <strong>{{ formatGeneratorValue(generatorDetails(genOid).gallons) }} جالون</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="form-hint">لا توجد مولدات مرتبطة</p>
                    </div>
                </div>

                <!-- ===== بيانات خزان الوقود ===== -->
                <div class="card" style="position: relative; z-index: 25; overflow: visible; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">⛽ بيانات خزان الوقود</h3>
                    </div>
                    <div class="card-body" style="overflow: visible;">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">شكل الخزان</label>
                                <select v-model="form.TANK_SHAPE" class="form-select" :disabled="readonly">
                                    <option value="">اختر شكل الخزان</option>
                                    <option value="اسطواني عمودي">اسطواني عمودي</option>
                                    <option value="اسطواني أفقي">اسطواني أفقي</option>
                                    <option value="مكعب">مكعب</option>
                                    <option value="Daily Tank">Daily Tank</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">مادة الخزان</label>
                                <select v-model="form.TANK_MATERIAL" class="form-select" :disabled="readonly">
                                    <option value="">اختر مادة الخزان</option>
                                    <option value="HDPE">HDPE</option>
                                    <option value="معدن">معدن</option>
                                    <option value="فيبرجلاس">فيبرجلاس</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">القطر (م)</label>
                                <input v-model="form.TANK_DIAMETER" type="number" step="0.01" class="form-input" :readonly="readonly" placeholder="0.0">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">الارتفاع (م)</label>
                                <input v-model="form.TANK_HEIGHT" type="number" step="0.01" class="form-input" :readonly="readonly" placeholder="0.0">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== نظام الكلورة والمواد الكيميائية ===== -->
                <div class="card" style="position: relative; z-index: 20; overflow: visible; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">🧪 نظام الكلورة والمواد الكيميائية</h3>
                    </div>
                    <div class="card-body" style="overflow: visible;">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">نظام الكلورة</label>
                                <select v-model="form.CHLORINATION_SYSTEM" class="form-select" :disabled="readonly">
                                    <option value="لا يوجد">لا يوجد</option>
                                    <option value="يوجد">يوجد</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">حالة نظام الكلورة</label>
                                <select v-model="form.CHLORINATION_STATUS" class="form-select" :disabled="readonly || form.CHLORINATION_SYSTEM !== 'يوجد'">
                                    <option value="">اختر الحالة</option>
                                    <option value="يعمل">يعمل</option>
                                    <option value="لا يعمل">لا يعمل</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">سعة خزان الكلور (لتر)</label>
                                <input v-model="form.CHLORINE_TANK_CAPACITY" type="number" step="0.01" class="form-input" :readonly="readonly" placeholder="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">نوع خزان الكلور</label>
                                <select v-model="form.CHLORINE_TANK_TYPE" class="form-select" :disabled="readonly">
                                    <option value="">اختر النوع</option>
                                    <option value="اسطواني عمودي">اسطواني عمودي</option>
                                    <option value="مكعب">مكعب</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">مادة خزان الكلور</label>
                                <select v-model="form.CHLORINE_TANK_MATERIAL" class="form-select" :disabled="readonly">
                                    <option value="">اختر المادة</option>
                                    <option value="HDPE أزرق">HDPE أزرق</option>
                                    <option value="بلاستيك شفاف">بلاستيك شفاف</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">نوع مضخة الكلور</label>
                                <input v-model="form.CHLORINE_PUMP_TYPE" type="text" class="form-input" :readonly="readonly">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== خزان التبريد ===== -->
                <div class="card" style="position: relative; z-index: 15; overflow: visible; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">🌡️ خزان التبريد</h3>
                    </div>
                    <div class="card-body" style="overflow: visible;">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">سعة خزان التبريد (لتر)</label>
                                <select v-model="form.COOLING_TANK_CAPACITY" class="form-select" :disabled="readonly">
                                    <option value="">اختر السعة</option>
                                    <option :value="220">220 لتر</option>
                                    <option :value="500">500 لتر</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">نوع خزان التبريد</label>
                                <select v-model="form.COOLING_TANK_TYPE" class="form-select" :disabled="readonly">
                                    <option value="">اختر النوع</option>
                                    <option value="HDPE أزرق">HDPE أزرق</option>
                                    <option value="HDPE أسود">HDPE أسود</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">نظام التبريد</label>
                                <select v-model="form.COOLING_SYSTEM" class="form-select" :disabled="readonly">
                                    <option value="">اختر النظام</option>
                                    <option value="اوتوماتيك">اوتوماتيك</option>
                                    <option value="يدوي">يدوي</option>
                                    <option value="غير متوفر">غير متوفر</option>
                                    <option value="غاطس">غاطس</option>
                                    <option value="اوتوماتيك يحتاج صيانة">اوتوماتيك يحتاج صيانة</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">خط التبريد</label>
                                <select v-model="form.COOLING_LINE_TYPE" class="form-select" :disabled="readonly">
                                    <option value="">اختر النوع</option>
                                    <option value="حديد سكب">حديد سكب</option>
                                    <option value="HDPE">HDPE</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" style="position: relative; z-index: 12; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">📷 صور ومرفقات البئر</h3>
                    </div>
                    <div class="card-body">
                        <WellMediaFields
                            v-model="newMedia"
                            :media="well.MEDIA || []"
                            :readonly="readonly"
                            @delete="deleteMedia"
                        />
                        <span v-if="mediaError" class="form-error">{{ mediaError }}</span>
                    </div>
                </div>

                <!-- ===== الموقع على الخريطة ===== -->
                <div class="card" style="position: relative; z-index: 10; margin-bottom: 20px;">
                    <div class="card-header">
                        <h3 class="card-title">📍 الموقع على الخريطة</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row" style="margin-bottom: 16px;">
                            <div class="form-group">
                                <label class="form-label">خط العرض</label>
                                <input v-model="form.LATITUDE" type="text" class="form-input" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">خط الطول</label>
                                <input v-model="form.LONGITUDE" type="text" class="form-input" readonly>
                            </div>
                        </div>
                        <div id="location-map" style="height: 400px; border-radius: 12px;"></div>
                        <p v-if="!readonly" class="form-hint" style="margin-top: 8px;">انقر على الخريطة أو اسحب المؤشر لتحديد موقع البئر</p>
                    </div>
                </div>

                <div v-if="!readonly" style="margin-top: 24px; display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary btn-lg">💾 حفظ التعديلات</button>
                    <button type="button" class="btn btn-secondary btn-lg" @click="router.visit('/wells/wells')">إلغاء</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
