<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { appPath } from '../../helpers';
import PosterManagerModal from './Components/PosterManagerModal.vue';
import AdminLoginModal from './Components/AdminLoginModal.vue';
import ClinicWorkloadCard from './Components/ClinicWorkloadCard.vue';
import { 
  Building2, 
  ShieldCheck, 
  Maximize2, 
  ChevronLeft, 
  ChevronRight, 
  Play, 
  Pause, 
  Activity, 
  Users, 
  Bed, 
  Clock, 
  FileSpreadsheet, 
  Sparkles, 
  ExternalLink,
  Database,
  ArrowUpRight,
  X,
  RefreshCw,
  FileText,
  SlidersHorizontal,
  Settings,
  Home,
  KeyRound,
  Link2,
  AlertTriangle
} from 'lucide-vue-next';

const props = defineProps({
  posters: {
    type: Array,
    default: () => [],
  },
  hospitalCode: {
    type: String,
    default: null,
  },
  hospitalName: {
    type: String,
    default: null,
  },
  dbError: {
    type: String,
    default: null,
  },
  wardCensus: {
    type: Array,
    default: () => [],
  },
  hosxpData: {
    type: Object,
    default: () => ({}),
  },
  clinicStats: {
    type: Object,
    default: () => ({}),
  },
  queueHeatmap: {
    type: Object,
    default: () => ({}),
  },
  realtimeData: {
    type: Object,
    default: () => ({}),
  },
  canManageAdmin: {
    type: Boolean,
    default: false,
  },
  adminName: {
    type: String,
    default: null,
  },
  adminPosters: {
    type: Array,
    default: () => [],
  },
  adminStats: {
    type: Object,
    default: () => ({ total: 0, active: 0, inactive: 0, max: 8, remaining: 8 }),
  },
  dbConnected: {
    type: Boolean,
    default: false,
  },
});

// Ward census card colors by position: green, yellow, red
const wardCardStyles = [
  { card: 'bg-[#edf9f1] border-[#a6e5b8]', title: 'text-[#1f7a3f]', count: 'text-[#166534]' },
  { card: 'bg-[#fffbeb] border-[#fde68a]', title: 'text-[#a16207]', count: 'text-[#b45309]' },
  { card: 'bg-[#fff5f5] border-[#fecaca]', title: 'text-[#b91c1c]', count: 'text-[#dc2626]' },
];

// Carousel State
const currentIndex = ref(0);
const isAutoPlay = ref(true);
let autoPlayTimer = null;
const isFullscreen = ref(false);

// HOSxP Admin Poster Manager Modal State
const isPosterManagerOpen = ref(false);

// HOSxP Administrator Login Modal State
const isAdminLoginOpen = ref(false);

const adminLogout = () => {
  router.post(appPath('/bms-homepage/admin/logout'), {}, { preserveScroll: true });
};

const currentPoster = computed(() => {
  if (!props.posters || props.posters.length === 0) return null;
  return props.posters[currentIndex.value] || props.posters[0];
});

const nextPoster = () => {
  if (props.posters.length === 0) return;
  currentIndex.value = (currentIndex.value + 1) % props.posters.length;
};

const prevPoster = () => {
  if (props.posters.length === 0) return;
  currentIndex.value = (currentIndex.value - 1 + props.posters.length) % props.posters.length;
};

const goToPoster = (index) => {
  currentIndex.value = index;
};

const toggleAutoPlay = () => {
  isAutoPlay.value = !isAutoPlay.value;
  if (isAutoPlay.value) {
    startAutoPlay();
  } else {
    stopAutoPlay();
  }
};

const startAutoPlay = () => {
  stopAutoPlay();
  autoPlayTimer = setInterval(() => {
    nextPoster();
  }, 6000);
};

const stopAutoPlay = () => {
  if (autoPlayTimer) {
    clearInterval(autoPlayTimer);
    autoPlayTimer = null;
  }
};

onMounted(() => {
  if (isAutoPlay.value && props.posters.length > 1) {
    startAutoPlay();
  }
});

onUnmounted(() => {
  stopAutoPlay();
});
</script>

<template>
  <Head title="SmartReport - ระบบหน้าแรก HOSxP & ประชาสัมพันธ์" />

  <!-- FULL SPLIT-SCREEN HOMEPAGE (Always accessible in HOSxP) -->
  <div class="h-screen max-h-screen bg-[#dbe7f3] text-[#1e293b] flex flex-col font-prompt selection:bg-[#1b62a5] selection:text-white p-1.5 sm:p-2 overflow-hidden">

    <!-- Split-Screen Grid: Left (HOSxP Reports) & Right (Full Poster) -->
    <main class="flex-1 w-full grid grid-cols-1 lg:grid-cols-12 gap-1.5 sm:gap-2 h-full min-h-0 items-stretch overflow-hidden">
      
      <!-- ========================================== -->
      <!-- LEFT COLUMN: พื้นที่สำหรับรายงาน HOSxP (50%) -->
      <!-- ========================================== -->
      <section class="lg:col-span-6 bg-white rounded-lg border border-[#adc5de] shadow-xs flex flex-col h-full min-h-0 overflow-hidden">
        
        <!-- Left Title Header (Matches right header bg & styling, without date) -->
        <div class="px-2.5 py-1 bg-[#dce7f3] border-b border-[#adc5de] flex items-center justify-between gap-2 shrink-0">
          <div class="flex items-center gap-1.5 truncate">
            <Building2 class="w-4 h-4 text-[#17538d] shrink-0" />
            <h1 class="text-xs font-bold text-[#17538d] tracking-tight truncate">
              {{ dbConnected && (hosxpData.hospital_name || hospitalName) ? (hosxpData.hospital_name || hospitalName) : (dbError || 'ไม่สามารถเชื่อมต่อฐานข้อมูล HOSxP') }}
            </h1>
          </div>

          <!-- Database Connection Indicator Badge -->
          <div class="shrink-0 flex items-center gap-1">
            <span 
              v-if="dbConnected" 
              class="text-[10px] font-semibold text-[#166534] bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full flex items-center gap-1"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
              เชื่อมต่อฐานข้อมูล HOSxP
            </span>
            <span 
              v-else 
              class="text-[10px] font-semibold text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-full flex items-center gap-1"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
              ไม่สามารถเชื่อมต่อฐานข้อมูล HOSxP
            </span>
          </div>
        </div>

        <!-- Left Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-3 space-y-3 min-h-0">
          
          <!-- HOSxP Database Disconnected Warning Banner -->
          <div 
            v-if="!dbConnected" 
            class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 flex items-center gap-2 text-rose-800 shadow-2xs"
          >
            <AlertTriangle class="w-4 h-4 text-rose-600 shrink-0" />
            <div class="text-xs">
              <span class="font-bold">ไม่สามารถเชื่อมต่อฐานข้อมูล HOSxP</span>
              <span class="text-[11px] text-rose-600 ml-1">• ข้อมูลสถิติและรายงานระบบ HOSxP ไม่พร้อมใช้งาน กรุณาตรวจสอบการตั้งค่า DB_*_2 ใน .env</span>
            </div>
          </div>

          <!-- Quick Stat KPI Boxes (HOSxP Delphi Style) -->
          <div class="grid grid-cols-3 gap-2">
            <div
              v-for="(ward, index) in wardCensus"
              :key="ward.ward"
              class="border p-2.5 rounded-lg text-center shadow-xs"
              :class="wardCardStyles[index % wardCardStyles.length].card"
            >
              <div class="text-[11px] font-semibold mb-0.5 truncate" :class="wardCardStyles[index % wardCardStyles.length].title" :title="ward.name">{{ ward.name }}</div>
              <div class="text-2xl font-black" :class="wardCardStyles[index % wardCardStyles.length].count">{{ ward.count }}</div>
              <div class="text-[10px] font-sarabun" :class="wardCardStyles[index % wardCardStyles.length].title">ผู้ป่วยที่ Admit อยู่ (ราย)</div>
            </div>
          </div>

          <!-- Clinic Workload Progress Bars (HOSxP Live Data with Thai Calendar Date Picker in Top-Right) -->
          <ClinicWorkloadCard
            :initial-data="clinicStats"
            :hospital-code="hospitalCode"
          />

        </div>

        <!-- Left Column Footer (HOSxP Status Bar Style) -->
        <div class="px-3 py-1 bg-[#dce7f3] border-t border-[#adc5de] flex items-center justify-between text-[11px] text-[#475569] font-sarabun shrink-0">
          <div class="flex items-center gap-1.5 truncate">
            <Database class="w-3.5 h-3.5 text-[#17538d] shrink-0" />
            <span class="truncate">โมดูล: SmartReport Homepage • เชื่อมต่อฐานข้อมูล HOSxP โดยตรง</span>
          </div>
          <span v-if="dbConnected" class="text-[#166534] font-semibold shrink-0">
            ● เชื่อมต่อฐานข้อมูล HOSxP สำเร็จ
          </span>
          <span v-else class="text-[#dc2626] font-semibold shrink-0">
            ● ไม่สามารถเชื่อมต่อฐานข้อมูล HOSxP
          </span>
        </div>

      </section>

      <!-- ========================================== -->
      <!-- RIGHT COLUMN: แสดงรูป One-Page Poster ให้เต็ม (50%) -->
      <!-- ========================================== -->
      <section class="lg:col-span-6 bg-white rounded-lg border border-[#adc5de] shadow-xs flex flex-col h-full min-h-0 overflow-hidden relative">
        
        <!-- Slim Top Bar with Title & Controls -->
        <div class="px-2.5 py-1 bg-[#dce7f3] border-b border-[#adc5de] flex items-center justify-between gap-2 shrink-0">
          <div class="flex items-center gap-1.5 truncate">
            <span class="text-xs font-bold text-[#17538d] flex items-center gap-1.5 truncate">
              <span>โปสเตอร์ข่าวประชาสัมพันธ์</span>
            </span>
          </div>

          <!-- Carousel Controls & HOSxP Admin Setting -->
          <div class="flex items-center gap-1 shrink-0">
            <!-- HOSxP Admin Setting Button (เห็นแค่ ผู้ดูแลระบบ HOSxP) -->
            <button
              v-if="canManageAdmin"
              type="button"
              @click="isPosterManagerOpen = true"
              class="p-1 px-1.5 rounded bg-[#17538d] hover:bg-[#124778] active:scale-95 text-white transition-all shadow-xs flex items-center gap-1 cursor-pointer border border-[#0f3e6c] mr-1"
              title="ตั้งค่าและจัดการโปสเตอร์ (เพิ่ม, ลบ, แก้ไข) - สิทธิ์ผู้ดูแลระบบ HOSxP"
            >
              <Settings class="w-3.5 h-3.5 text-sky-200" />
              <span class="text-[11px] font-semibold hidden sm:inline">จัดการโปสเตอร์</span>
            </button>

            <button
              v-if="canManageAdmin"
              type="button"
              @click="adminLogout"
              class="p-1 px-1.5 rounded bg-white hover:bg-[#e4eef7] border border-[#adc5de] text-[#17538d] text-[11px] font-semibold transition-colors cursor-pointer mr-1"
              :title="adminName ? `ผู้ดูแล: ${adminName} (คลิกเพื่อออกจากโหมดผู้ดูแล)` : 'ออกจากโหมดผู้ดูแล'"
            >
              ออกจากโหมดผู้ดูแล
            </button>

            <!-- Sign in as HOSxP administrator -->
            <button
              v-else
              type="button"
              @click="isAdminLoginOpen = true"
              class="p-1 rounded bg-white hover:bg-[#e4eef7] border border-[#adc5de] text-[#17538d] transition-colors cursor-pointer mr-1"
              title="เข้าสู่ระบบผู้ดูแลระบบ HOSxP เพื่อจัดการโปสเตอร์"
            >
              <Settings class="w-3 h-3" />
            </button>

            <button
              type="button"
              @click="toggleAutoPlay"
              class="p-1 rounded bg-white hover:bg-[#e4eef7] border border-[#adc5de] text-[#17538d] transition-colors cursor-pointer"
              :title="isAutoPlay ? 'หยุดเล่นสไลด์อัตโนมัติ' : 'เล่นสไลด์อัตโนมัติ'"
            >
              <Pause v-if="isAutoPlay" class="w-3 h-3 text-[#166534]" />
              <Play v-else class="w-3 h-3 text-slate-500" />
            </button>

            <button
              type="button"
              @click="prevPoster"
              class="p-1 rounded bg-white hover:bg-[#e4eef7] border border-[#adc5de] text-[#17538d] transition-colors"
              title="ก่อนหน้า"
            >
              <ChevronLeft class="w-3 h-3" />
            </button>

            <span class="px-1.5 py-0.5 text-[11px] font-mono text-[#17538d] font-bold bg-white rounded border border-[#adc5de]">
              {{ posters.length > 0 ? (currentIndex + 1) : 0 }}/{{ posters.length }}
            </span>

            <button
              type="button"
              @click="nextPoster"
              class="p-1 rounded bg-white hover:bg-[#e4eef7] border border-[#adc5de] text-[#17538d] transition-colors"
              title="ถัดไป"
            >
              <ChevronRight class="w-3 h-3" />
            </button>

            <button
              type="button"
              @click="isFullscreen = true"
              class="p-1 rounded bg-[#17538d] hover:bg-[#124778] text-white transition-colors shadow-xs"
              title="ดูภาพโปสเตอร์เต็มจอ"
            >
              <Maximize2 class="w-3 h-3" />
            </button>
          </div>
        </div>

        <!-- FULL-SIZED POSTER CANVAS (Expands to 100% of remaining height/width) -->
        <div 
          class="relative flex-1 w-full h-full min-h-0 bg-[#e8f1f8] flex items-center justify-center cursor-pointer group overflow-hidden p-0.5 sm:p-1"
          @click="isFullscreen = true"
        >
          <!-- Full-height Poster Image -->
          <img 
            v-if="currentPoster?.image_url"
            :src="currentPoster.image_url" 
            :alt="currentPoster.title"
            class="w-full h-full object-contain mx-auto select-none rounded-xs drop-shadow-sm transition-transform duration-200"
          />
          <div v-else class="text-center p-8 text-slate-400">
            <FileText class="w-12 h-12 mx-auto mb-2 text-slate-300" />
            <p class="text-xs">ยังไม่มีรูปโปสเตอร์ที่เปิดใช้งาน</p>
            <button
              v-if="canManageAdmin"
              type="button"
              @click.stop="isPosterManagerOpen = true"
              class="mt-3 px-3 py-1.5 bg-[#17538d] text-white text-xs rounded-lg font-semibold hover:bg-[#124778] transition-colors"
            >
              + อัปโหลดรูปโปสเตอร์ (Admin HOSxP)
            </button>
          </div>

          <!-- Floating Modern Dots Navigation -->
          <div 
            v-if="posters.length > 1" 
            class="absolute bottom-2 left-1/2 -translate-x-1/2 flex items-center gap-1.5 bg-slate-900/60 backdrop-blur-xs px-2.5 py-1 rounded-full border border-white/20 z-10 transition-opacity"
            @click.stop
          >
            <button
              v-for="(p, idx) in posters"
              :key="p.id"
              type="button"
              @click="goToPoster(idx)"
              class="h-2 rounded-full transition-all"
              :class="currentIndex === idx ? 'bg-white w-5' : 'bg-white/40 hover:bg-white/80 w-2'"
              :title="p.title"
            />
          </div>

          <!-- Hover Hint Badge -->
          <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity bg-slate-900/70 text-white text-[10px] font-semibold px-2 py-0.5 rounded shadow flex items-center gap-1 pointer-events-none">
            <Maximize2 class="w-3 h-3" />
            <span>คลิกดูขนาดเต็ม</span>
          </div>
        </div>

      </section>

    </main>

    <!-- FULLSCREEN POSTER MODAL -->
    <div 
      v-if="isFullscreen && currentPoster"
      class="fixed inset-0 z-50 bg-black/90 backdrop-blur-xs flex flex-col items-center justify-center p-4"
      @click.self="isFullscreen = false"
    >
      <div class="w-full max-w-4xl flex items-center justify-between pb-2 text-white border-b border-white/20 mb-3">
        <span class="text-xs sm:text-sm font-semibold font-sarabun">
          รูปโปสเตอร์ประชาสัมพันธ์ <span v-if="currentPoster.thai_date">• {{ currentPoster.thai_date }}</span>
        </span>
        <button
          type="button"
          @click="isFullscreen = false"
          class="p-1.5 rounded-full bg-white/20 hover:bg-rose-600 text-white transition-colors"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <div class="flex-1 flex items-center justify-center max-w-4xl w-full">
        <img 
          :src="currentPoster.image_url" 
          :alt="currentPoster.title" 
          class="max-w-full max-h-[88vh] h-auto object-contain rounded-lg shadow-2xl bg-white p-1"
        />
      </div>
    </div>

    <!-- HOSxP ADMIN POSTER MANAGER MODAL (Merged into BMS Homepage) -->
    <PosterManagerModal
      v-if="canManageAdmin"
      :is-open="isPosterManagerOpen"
      :posters="adminPosters.length > 0 ? adminPosters : posters"
      :hospital-code="hospitalCode"
      :hospital-name="hospitalName"
      :stats="adminStats || { total: posters.length, max: 8, remaining: 8 - posters.length }"
      @close="isPosterManagerOpen = false"
    />


    <AdminLoginModal
      :is-open="isAdminLoginOpen"
      @close="isAdminLoginOpen = false"
    />

  </div>
</template>
