<script setup>
import Sidebar from '@/WellsSystem/Components/Layout/Sidebar.vue';
import TopNav from '@/WellsSystem/Components/Layout/TopNav.vue';
import { usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import '../../../css/wells.css';

const page = usePage();
const sidebarOpen = ref(false);

const flash = computed(() => page.props.flash);

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};
</script>

<template>
    <div class="app-layout">
        <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" />

        <div class="app-content">
            <TopNav @toggle-sidebar="toggleSidebar" />

            <div class="content-wrapper">
                <div v-if="flash?.success" class="flash-message flash-success animate-slide-up">
                    ✓ {{ flash.success }}
                </div>
                <div v-if="flash?.error" class="flash-message flash-error animate-slide-up">
                    ✗ {{ flash.error }}
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
