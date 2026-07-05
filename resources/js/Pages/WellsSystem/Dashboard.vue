<script setup>
import AppLayout from '../../WellsSystem/Layouts/AppLayout.vue'
import StatsCard from '../../WellsSystem/Components/Shared/StatsCard.vue'

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
        })
    }
})

const formatNumber = (value) => Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 2 })
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
        </div>
    </AppLayout>
</template>
