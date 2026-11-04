<script setup>
import { onBeforeUnmount, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../WellsSystem/Layouts/AppLayout.vue'
import StatsCard from '../../WellsSystem/Components/Shared/StatsCard.vue'
import MediaThumbnails from '../../WellsSystem/Components/Shared/MediaThumbnails.vue'

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            totalWells: 0,
            totalTeams: 0,
            totalInspectors: 0,
            totalReadings: 0,
            pendingReadings: 0,
            todayReadings: 0,
            totalDieselInventories: 0,
            todayDieselInventories: 0,
            dieselFuelBalance: 0,
            recentReadings: [],
        })
    }
})

const formatNumber = (value) => Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 2 })
const formatDate = (dateStr) => {
    if (!dateStr) return '-'
    const date = new Date(dateStr)
    return `${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}`
}

let refreshTimer
onMounted(() => {
    refreshTimer = window.setInterval(() => {
        router.reload({ only: ['stats'], preserveScroll: true })
    }, 10000)
})
onBeforeUnmount(() => window.clearInterval(refreshTimer))
</script>

<template>
    <AppLayout title="لوحة التحكم">
        <div class="content-wrapper">
            <!-- Stats Grid -->
            <div class="stats-grid">
                <StatsCard
                    icon="water-drop"
                    :value="stats.totalWells"
                    label="إجمالي الآبار"
                    iconClass="accent"
                />
                <StatsCard
                    icon="users"
                    :value="stats.totalTeams"
                    label="الفرق الميدانية"
                    iconClass="blue"
                />
                <StatsCard
                    icon="search"
                    :value="stats.totalInspectors"
                    label="المفتشين"
                    iconClass="success"
                />
                <StatsCard
                    icon="bar-chart"
                    :value="stats.totalReadings"
                    label="إجمالي القراءات"
                    iconClass="warning"
                />
                <StatsCard
                    icon="clock"
                    :value="stats.pendingReadings"
                    label="بانتظار المزامنة"
                    iconClass="danger"
                />
                <StatsCard
                    icon="calendar"
                    :value="stats.todayReadings"
                    label="قراءات اليوم"
                    iconClass="accent"
                />
                <StatsCard
                    icon="fuel"
                    :value="stats.totalDieselInventories"
                    label="جرد السولار"
                    iconClass="warning"
                />
                <StatsCard
                    icon="calendar"
                    :value="stats.todayDieselInventories"
                    label="جرد اليوم"
                    iconClass="success"
                />
                <StatsCard
                    icon="fuel"
                    :value="formatNumber(stats.dieselFuelBalance)"
                    label="رصيد السولار (لتر)"
                    iconClass="blue"
                />
            </div>

            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h3 class="card-title">آخر القراءات المدخلة</h3>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="data-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>البئر</th>
                                    <th>المفتش</th>
                                    <th>عداد المولد</th>
                                    <th>عداد المضخة</th>
                                    <th>تاريخ القراءة</th>
                                    <th>تاريخ إدخال السجل</th>
                                    <th>الصور</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="reading in stats.recentReadings" :key="reading.oid">
                                    <td class="font-bold">{{ reading.well_name }}</td>
                                    <td>{{ reading.inspector_name }}</td>
                                    <td>{{ reading.generator_hours_reading ?? '-' }}</td>
                                    <td>{{ reading.pump_hours_reading ?? '-' }}</td>
                                    <td>{{ formatDate(reading.reading_date) }}</td>
                                    <td>{{ formatDate(reading.created_at) }}</td>
                                    <td><MediaThumbnails :media="reading.media || []" /></td>
                                </tr>
                                <tr v-if="!stats.recentReadings?.length">
                                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--color-text-muted);">
                                        لا توجد قراءات حتى الآن
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
