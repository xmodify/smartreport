<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { 
  Building2, 
  ShieldCheck, 
  LogIn, 
  LayoutDashboard, 
  LogOut,
  Megaphone,
  PlusCircle,
  Home
} from 'lucide-vue-next';

const page = usePage();
const auth = computed(() => page.props.auth);
</script>

<template>
  <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-slate-200/80 shadow-xs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-18">
        
        <!-- Logo & Title -->
        <Link :href="route ? route('home') : '/'" class="flex items-center gap-3 group">
          <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-teal-600 to-emerald-400 flex items-center justify-center text-white shadow-md shadow-teal-500/20 group-hover:scale-105 transition-transform duration-300">
            <Building2 class="w-6 h-6" />
          </div>
          <div>
            <div class="flex items-center gap-2">
              <span class="font-extrabold text-lg text-slate-900 tracking-tight">{{ page.props.hospitalName || 'SmartReport ระบบสารสนเทศโรงพยาบาล' }}</span>
              <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-teal-100 text-teal-800 border border-teal-200">BMS Portal</span>
            </div>
            <p class="text-xs text-slate-500 flex items-center gap-1.5 font-sarabun">
              <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
              ระบบข่าวประชาสัมพันธ์และประกาศทางการ
            </p>
          </div>
        </Link>

        <!-- Right Side Nav -->
        <div class="flex items-center gap-3">
          <Link
            :href="'/'"
            class="hidden sm:inline-flex items-center gap-1.5 text-sm font-medium px-3.5 py-2 rounded-lg text-slate-600 hover:text-teal-700 hover:bg-slate-100 transition-colors"
          >
            <Home class="w-4 h-4" />
            หน้าแรก
          </Link>

          <!-- If authenticated -->
          <template v-if="auth?.user">
            <Link
              :href="'/admin/announcements/create'"
              class="inline-flex items-center gap-1.5 text-sm font-semibold px-3.5 py-2 rounded-lg bg-teal-600 text-white hover:bg-teal-700 shadow-sm transition-all"
            >
              <PlusCircle class="w-4 h-4" />
              <span class="hidden sm:inline">สร้างข่าวใหม่</span>
            </Link>

            <Link
              :href="'/admin/announcements'"
              class="inline-flex items-center gap-1.5 text-sm font-medium px-3.5 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition-colors"
            >
              <LayoutDashboard class="w-4 h-4" />
              <span class="hidden md:inline">จัดการข่าว</span>
            </Link>

            <Link
              :href="'/logout'"
              method="post"
              as="button"
              class="inline-flex items-center gap-1 text-sm font-medium px-3 py-2 rounded-lg text-rose-600 hover:bg-rose-50 transition-colors"
              title="ออกจากระบบ"
            >
              <LogOut class="w-4 h-4" />
              <span class="hidden lg:inline">ออก</span>
            </Link>
          </template>

          <!-- If Guest -->
          <template v-else>
            <Link
              :href="'/login'"
              class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 shadow-sm transition-all hover:shadow-md"
            >
              <ShieldCheck class="w-4 h-4 text-emerald-400" />
              <span>เข้าสู่ระบบ Admin</span>
            </Link>
          </template>

        </div>
      </div>
    </div>
  </header>
</template>
