<template>
    <svg 
        xmlns="http://www.w3.org/2000/svg" 
        :width="size" 
        :height="size" 
        viewBox="0 0 24 24" 
        fill="none" 
        stroke="currentColor" 
        stroke-width="2" 
        stroke-linecap="round" 
        stroke-linejoin="round"
        :class="className"
    >
        <path v-if="name === 'dashboard'" d="M3 3h7v9H3z M14 3h7v5h-7z M14 12h7v9h-7z M3 16h7v5H3z"/>
        <path v-else-if="name === 'water-drop'" d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>
        <path v-else-if="name === 'map'" d="M1 6v16l7-4 8 4 7-4V2l-7 4-8-4-7 4z M8 2v16 M16 6v16"/>
        <path v-else-if="name === 'users'" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2 M9 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M23 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75"/>
        <path v-else-if="name === 'search'" d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z M21 21l-4.35-4.35"/>
        <path v-else-if="name === 'clipboard'" d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2 M15 2H9c-1.1 0-2 .9-2 2v2h10V4c0-1.1-.9-2-2-2z"/>
        <path v-else-if="name === 'clock'" d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z M12 6v6l4 2"/>
        <path v-else-if="name === 'calendar'" d="M19 4H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2z M16 2v4 M8 2v4 M3 10h18"/>
        <path v-else-if="name === 'bar-chart'" d="M18 20V10 M12 20V4 M6 20v-6"/>
        <path v-else-if="name === 'fuel'" d="M3 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18 M3 12h12 M15 7h2l3 3v9a2 2 0 0 1-2 2h-1 M7 7h4"/>
        <path v-else-if="name === 'activity'" d="M22 12h-4l-3 9L9 3l-3 9H2"/>
        <path v-else-if="name === 'check-circle'" d="M22 11.08V12a10 10 0 1 1-5.93-9.14 M22 4L12 14.01l-3-3"/>
        <path v-else-if="name === 'alert-circle'" d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z M12 8v4 M12 16h.01"/>
        <path v-else-if="name === 'bell'" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9 M13.73 21a2 2 0 0 1-3.46 0"/>
        <path v-else-if="name === 'notification'" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9 M13.73 21a2 2 0 0 1-3.46 0"/>
    </svg>
</template>

<script setup>
defineProps({
    name: {
        type: String,
        required: true
    },
    size: {
        type: [Number, String],
        default: 24
    },
    className: {
        type: String,
        default: ''
    }
})
</script>
