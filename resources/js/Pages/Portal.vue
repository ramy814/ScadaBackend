<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineOptions({ layout: null })

defineProps({
    systems: { type: Array, required: true },
    userName: { type: String, required: true },
    canManagePermissions: { type: Boolean, default: false },
})
</script>

<template>
    <Head title="بوابة الأنظمة" />

    <div class="portal-shell min-h-screen relative overflow-hidden selection:bg-blue-500/30 font-cairo" dir="rtl">
        <!-- Ambient Background Glows -->
        <div class="absolute top-[-20%] right-[-10%] w-[700px] h-[700px] rounded-full bg-blue-600/10 blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-[-20%] left-[-10%] w-[600px] h-[600px] rounded-full bg-indigo-600/10 blur-[120px] pointer-events-none"></div>

        <!-- Header -->
        <header class="portal-header sticky top-0 z-50 backdrop-blur-xl border-b transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                <div class="flex items-center gap-4 group cursor-pointer">
                    <div class="relative">
                        <div class="absolute inset-0 bg-white/20 rounded-full blur-md group-hover:bg-emerald-400/30 transition-all duration-500"></div>
                        <img src="/images/mogaza-logo.png" alt="شعار البلدية" class="relative h-14 w-auto bg-white/5 rounded-full p-1 ring-1 ring-white/10 shadow-xl" />
                    </div>
                    <div>
                        <h1 class="portal-gradient-title text-xl font-extrabold text-transparent bg-clip-text tracking-tight">بلدية غزة</h1>
                        <p class="text-xs text-blue-400/80 font-medium tracking-wide">بوابة الأنظمة الموحدة</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-5">
                    <div class="text-sm text-slate-300 hidden md:block">
                        مرحباً، <span class="font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-400">{{ userName }}</span>
                    </div>
                    
                    <div class="h-6 w-px bg-white/10 hidden md:block"></div>

                    <Link v-if="canManagePermissions" href="/permissions" class="relative inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-white transition-all duration-300 bg-white/5 border border-white/10 rounded-full hover:bg-white/10 hover:scale-105 hover:shadow-[0_0_20px_rgba(255,255,255,0.1)] focus:outline-none focus:ring-2 focus:ring-blue-500/50">
                        إدارة الصلاحيات
                    </Link>

                    <a href="/auth/sso/logout" class="group flex items-center justify-center w-10 h-10 rounded-full bg-white/5 border border-white/10 hover:bg-red-500/10 hover:border-red-500/30 transition-all duration-300">
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-red-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 min-h-[calc(100vh-80px)] flex flex-col justify-center">
            <div class="text-center mb-16 opacity-0 animate-[fadeInDown_0.8s_ease-out_forwards]">
                <h2 class="portal-gradient-title text-4xl md:text-5xl font-extrabold text-transparent bg-clip-text mb-6 tracking-tight">الأنظمة المتاحة</h2>
                <p class="text-slate-400 text-lg md:text-xl max-w-2xl mx-auto leading-relaxed font-light">
                    اختر النظام الذي ترغب بالدخول إليه من القائمة أدناه بناءً على الصلاحيات الممنوحة لك.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <template v-for="(system, index) in systems" :key="system.key">
                    
                    <!-- Accessible System -->
                    <Link v-if="system.hasAccess && system.available" 
                          :href="system.route"
                          class="portal-card group relative flex flex-col p-8 rounded-3xl border backdrop-blur-md overflow-hidden transition-all duration-500 hover:-translate-y-2"
                          :style="{ animationDelay: `${index * 100}ms` }"
                          style="animation: fadeInUp 0.8s ease-out forwards; opacity: 0;">
                        <!-- Hover Gradient Background -->
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 to-indigo-500/10 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        
                        <div class="relative z-10 flex items-start justify-between mb-8">
                            <div class="relative flex items-center justify-center w-16 h-16 rounded-2xl bg-white/5 border border-white/10 group-hover:scale-110 group-hover:rotate-3 transition-all duration-500 shadow-inner">
                                <div class="absolute inset-0 bg-gradient-to-br from-blue-400/20 to-indigo-400/20 rounded-2xl blur-md opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                                <span class="relative z-10 filter drop-shadow-md flex items-center justify-center w-8 h-8" v-html="system.icon"></span>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold text-emerald-400 ring-1 ring-inset ring-emerald-500/20 shadow-[0_0_10px_rgba(16,185,129,0.1)]">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                متاح
                            </span>
                        </div>
                        
                        <div class="relative z-10 mt-auto">
                            <h3 class="text-2xl font-bold text-white mb-3 group-hover:text-blue-300 transition-colors duration-300">{{ system.title }}</h3>
                            <p class="text-slate-400 text-sm leading-relaxed">{{ system.description }}</p>
                        </div>
                    </Link>

                    <!-- Locked System (No Permission) -->
                    <div v-else-if="system.available && !system.hasAccess"
                          class="portal-card portal-card-locked group relative flex flex-col p-8 rounded-3xl border backdrop-blur-sm overflow-hidden transition-all duration-500"
                          :style="{ animationDelay: `${index * 100}ms` }"
                          style="animation: fadeInUp 0.8s ease-out forwards; opacity: 0;">
                        
                        <!-- Lock overlay -->
                        <div class="portal-lock-overlay absolute inset-0 z-20 flex items-center justify-center backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                            <div class="flex items-center gap-3 bg-white/5 border border-white/10 text-white px-5 py-2.5 rounded-full text-sm font-semibold shadow-2xl transform translate-y-4 group-hover:translate-y-0 transition-all duration-300">
                                <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                ليس لديك صلاحية
                            </div>
                        </div>

                        <div class="relative z-10 flex items-start justify-between mb-8 opacity-40 grayscale">
                            <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-white/5 border border-white/10">
                                <span class="filter drop-shadow-md flex items-center justify-center w-8 h-8" v-html="system.icon"></span>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-slate-500/10 px-3 py-1.5 text-xs font-semibold text-slate-400 ring-1 ring-inset ring-slate-500/20">
                                مقفل
                            </span>
                        </div>
                        
                        <div class="relative z-10 mt-auto opacity-50 grayscale">
                            <h3 class="text-2xl font-bold text-slate-300 mb-3">{{ system.title }}</h3>
                            <p class="text-slate-500 text-sm leading-relaxed">{{ system.description }}</p>
                        </div>
                    </div>

                    <!-- Unavailable System (Coming Soon) -->
                    <div v-else
                          class="portal-card portal-card-locked relative flex flex-col p-8 rounded-3xl border border-dashed backdrop-blur-sm overflow-hidden transition-all duration-500"
                          :style="{ animationDelay: `${index * 100}ms` }"
                          style="animation: fadeInUp 0.8s ease-out forwards; opacity: 0;">
                        
                        <div class="relative z-10 flex items-start justify-between mb-8 opacity-40">
                            <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-white/5 border border-white/10 border-dashed">
                                <span class="filter drop-shadow-md flex items-center justify-center w-8 h-8" v-html="system.icon"></span>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-orange-500/10 px-3 py-1.5 text-xs font-semibold text-orange-400 ring-1 ring-inset ring-orange-500/20">
                                قريباً
                            </span>
                        </div>
                        
                        <div class="relative z-10 mt-auto opacity-50">
                            <h3 class="text-2xl font-bold text-slate-400 mb-3">{{ system.title }}</h3>
                            <p class="text-slate-500 text-sm leading-relaxed">{{ system.description }}</p>
                        </div>
                    </div>

                </template>
            </div>
        </main>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap');

.font-cairo {
    font-family: 'Cairo', sans-serif !important;
}

.portal-shell {
    background: var(--theme-bg);
    color: var(--theme-text);
}

.portal-header {
    background: color-mix(in srgb, var(--theme-bg) 70%, transparent);
    border-color: var(--theme-border);
}

.portal-gradient-title {
    background-image: linear-gradient(to left, var(--theme-text), var(--theme-text-muted));
}

.portal-card {
    background: var(--theme-surface);
    border-color: var(--theme-border);
    box-shadow: var(--theme-shadow-md);
}

.portal-card:hover {
    background: var(--theme-surface-strong);
    border-color: rgba(59, 130, 246, 0.2);
    box-shadow: var(--theme-shadow-lg);
}

.portal-card-locked { opacity: 0.78; }
.portal-lock-overlay { background: color-mix(in srgb, var(--theme-bg) 82%, transparent); }
.portal-shell .text-white,
.portal-shell .text-slate-200,
.portal-shell .text-slate-300 { color: var(--theme-text); }
.portal-shell .text-slate-400 { color: var(--theme-text-muted); }
.portal-shell .text-slate-500 { color: var(--theme-text-subtle); }

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
