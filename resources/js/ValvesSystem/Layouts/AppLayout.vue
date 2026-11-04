<script setup>
import Sidebar from '@/ValvesSystem/Components/Layout/Sidebar.vue';
import TopNav from '@/ValvesSystem/Components/Layout/TopNav.vue';
import { usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import '../../../css/valves.css';

const page = usePage();
const sidebarOpen = ref(false);
const flash = computed(() => page.props.flash);
const toggleSidebar = () => { sidebarOpen.value = !sidebarOpen.value; };
</script>

<template>
    <div class="v-app-layout">
        <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" />

        <div class="v-app-content">
            <TopNav @toggle-sidebar="toggleSidebar" />

            <div class="v-content-wrapper">
                <div v-if="flash?.success" style="background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.3); color: #4ade80; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                    ✓ {{ flash.success }}
                </div>
                <div v-if="flash?.error" style="background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); color: #f87171; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                    ✗ {{ flash.error }}
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
