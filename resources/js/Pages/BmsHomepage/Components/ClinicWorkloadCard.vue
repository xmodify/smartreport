<script setup>
import { ref, computed } from 'vue';
import { appPath } from '../../../helpers';
import { toastError } from '../../../sweetalert';
import ThaiDateControls from './ThaiDateControls.vue';
import {
  Stethoscope,
  Info,
  AlertTriangle
} from 'lucide-vue-next';

const props = defineProps({
  initialData: {
    type: Object,
    default: () => ({}),
  },
  hospitalCode: {
    type: String,
    default: '',
  },
});

// Selected Date (YYYY-MM-DD)
const selectedDate = ref(props.initialData?.selected_date || '');

// Clinic Data State
const clinicData = ref(props.initialData || {});
const isLoading = ref(false);

const departments = computed(() => clinicData.value?.departments || []);
const totalPatients = computed(() => clinicData.value?.total_patients || 0);
const isLive = computed(() => Boolean(clinicData.value?.is_live));
const thaiFormattedDate = computed(() => clinicData.value?.thai_date || selectedDate.value || 'วันนี้');

// Fetch Data from Server
const loadData = async (date = selectedDate.value) => {
  isLoading.value = true;
  try {
    const url = appPath(`/bms-homepage/clinic-stats?date=${encodeURIComponent(date)}`);
    const res = await fetch(url);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    clinicData.value = json;
    selectedDate.value = json.selected_date || date;
  } catch (err) {
    console.error('Failed to load clinic workload data:', err);
    toastError('ไม่สามารถโหลดสถิติคลินิกบริการได้ กรุณาลองใหม่อีกครั้ง');
  } finally {
    isLoading.value = false;
  }
};

const onDateSelected = (date) => {
  selectedDate.value = date;
  loadData(date);
};
</script>

<template>
  <div class="bg-[#f8fbfe] rounded-lg border border-[#d2e2ef] p-3 shadow-2xs font-prompt flex flex-col">
    
    <!-- Top Header: Title, Live Badge, and Thai Date Picker in Top-Right Corner -->
    <div class="flex flex-wrap items-center justify-between gap-2 pb-2 mb-2 border-b border-[#e2ecf5]">
      
      <!-- Left: Title & Status Badge -->
      <div class="flex items-center gap-2 truncate">
        <div class="flex items-center gap-1.5 text-xs font-bold text-[#1b62a5]">
          <Stethoscope class="w-4 h-4 text-[#1b62a5] shrink-0" />
          <span>สถิติผู้รับบริการตามคลินิกบริการ</span>
        </div>
        
        <span 
          v-if="isLive"
          class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"
          title="ข้อมูลดึงสดผ่านระบบ HOSxP"
        >
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
          เรียลไทม์ HOSxP
        </span>
        <span 
          v-else
          class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200"
          title="ไม่สามารถเชื่อมต่อฐานข้อมูล HOSxP"
        >
          <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
          ไม่สามารถเชื่อมต่อฐานข้อมูล HOSxP
        </span>
      </div>

      <!-- Right: Thai Calendar Date Picker Controls (shared with the appointment modal) -->
      <ThaiDateControls
        :model-value="selectedDate"
        :loading="isLoading"
        @update:model-value="onDateSelected"
        @refresh="loadData()"
      />

    </div>

    <!-- Sub-info Bar: Selected Date Display & Total Patients Count -->
    <div class="flex items-center justify-between text-[11px] font-sarabun px-2 py-1 mb-2.5 rounded bg-[#edf4fb] border border-[#d2e2ef] text-[#2c5282]">
      <div class="flex items-center gap-1.5">
        <span class="font-medium">ประจำวันที่:</span>
        <span class="font-bold text-[#17538d]">{{ thaiFormattedDate }}</span>
      </div>

      <div class="flex items-center gap-1 font-mono">
        <span class="font-sarabun text-slate-500">ผู้ป่วยนอกรวม:</span>
        <span class="font-black text-[#17538d] text-xs">{{ Number(totalPatients).toLocaleString() }}</span>
        <span class="font-sarabun text-slate-500">ราย</span>
      </div>
    </div>

    <!-- Progress Bars List -->
    <div v-if="departments.length > 0" class="space-y-2 font-sarabun text-xs max-h-88 overflow-y-auto pr-1">
      <div 
        v-for="(dept, i) in departments" 
        :key="i"
        class="flex items-center justify-between gap-2.5 hover:bg-white/60 p-1 rounded transition-colors"
      >
        <!-- Department Name & Rank -->
        <div class="w-5/12 sm:w-4/12 truncate flex items-center gap-1.5">
          <span 
            class="w-4 h-4 rounded-full flex items-center justify-center text-[9px] font-bold shrink-0"
            :class="i === 0 ? 'bg-amber-100 text-amber-800 border border-amber-300' : (i < 3 ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600')"
          >
            {{ i + 1 }}
          </span>
          <span class="truncate text-[#334155] font-medium text-[11px]" :title="dept.name">
            {{ dept.name }}
          </span>
        </div>

        <!-- Animated Gradient Progress Bar -->
        <div class="flex-1 bg-[#e2ecf5] h-2.5 rounded-full overflow-hidden shadow-2xs">
          <div 
            class="bg-gradient-to-r from-[#2a7bc7] to-[#1b62a5] h-full rounded-full transition-all duration-500" 
            :style="{ width: Math.max(5, Math.min(100, dept.percent)) + '%' }"
          ></div>
        </div>

        <!-- Count & Share of Total -->
        <div class="w-24 text-right flex items-center justify-end gap-1.5 shrink-0">
          <span class="font-mono font-bold text-[#1b62a5] text-[11px]">
            {{ Number(dept.count).toLocaleString() }}
          </span>
          <span class="text-[10px] text-slate-400 font-normal">คน</span>
          <span v-if="dept.share" class="text-[9px] text-slate-400 font-mono hidden sm:inline">
            ({{ dept.share }}%)
          </span>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="py-6 text-center font-sarabun">
      <div v-if="!isLive" class="space-y-1">
        <AlertTriangle class="w-6 h-6 mx-auto mb-1 text-rose-500" />
        <p class="text-xs font-bold text-rose-600">ไม่สามารถเชื่อมต่อฐานข้อมูล HOSxP</p>
        <p class="text-[10px] text-slate-500">กรุณาตรวจสอบการเชื่อมต่อฐานข้อมูล HOSxP ของเซิร์ฟเวอร์</p>
      </div>
      <div v-else class="space-y-1 text-slate-400">
        <Info class="w-6 h-6 mx-auto mb-1 text-slate-300" />
        <p class="text-xs font-medium text-slate-500">ไม่มีข้อมูลผู้รับบริการในวันที่ {{ thaiFormattedDate }}</p>
        <p class="text-[10px] text-slate-400 mt-0.5">กรุณาเลือกวันอื่นที่มีประวัติผู้รับบริการ หรือกดปุ่ม "วันนี้"</p>
      </div>
    </div>

  </div>
</template>
