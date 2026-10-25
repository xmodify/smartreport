<script setup>
import { ref, computed } from 'vue';
import { appPath } from '../../../helpers';
import { toastSuccess, toastError } from '../../../sweetalert';
import { 
  Activity, 
  Calendar, 
  RefreshCw, 
  Clock, 
  Flame, 
  Users, 
  CheckCircle2, 
  AlertCircle 
} from 'lucide-vue-next';

const props = defineProps({
  initialHeatmap: {
    type: Object,
    default: () => ({}),
  },
  initialRealtime: {
    type: Object,
    default: () => ({}),
  },
  hospitalCode: {
    type: String,
    default: '',
  },
});

// Active Tab: 'realtime' | 'summary'
const activeTab = ref('realtime');

// Selected Date (defaults to today)
const selectedDate = ref(props.initialHeatmap?.selected_date || new Date().toISOString().slice(0, 10));

// Data State
const realtimeData = ref(props.initialRealtime || {});
const heatmapData = ref(props.initialHeatmap || {});
const isLoading = ref(false);

const hours = computed(() => heatmapData.value?.hours || [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19]);
const departments = computed(() => heatmapData.value?.departments || []);
const totalQueues = computed(() => heatmapData.value?.total_queues || 0);

// Thai Formatted Date (วัน/เดือนภาษาไทย/ปี พ.ศ.)
const thaiFormattedDate = computed(() => {
  if (!selectedDate.value) return 'วันนี้';
  try {
    const parts = selectedDate.value.split('-');
    if (parts.length === 3) {
      const thMonths = [
        'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
      ];
      const day = parseInt(parts[2], 10);
      const monthIndex = parseInt(parts[1], 10) - 1;
      const thYear = parseInt(parts[0], 10) + 543;
      return `${day} ${thMonths[monthIndex]} ${thYear}`;
    }
  } catch (e) {
    // fallback
  }
  return selectedDate.value;
});

// Heatmap Cell Color Class (Light Theme matching other cards)
const getCellClass = (q) => {
  if (!q || q === 0) {
    return 'text-slate-300 bg-transparent';
  }
  if (q >= 100) {
    return 'bg-[#fee2e2] text-[#dc2626] font-bold border border-rose-300 shadow-2xs';
  }
  if (q >= 25) {
    return 'bg-[#fef3c7] text-[#b45309] font-bold border border-amber-200';
  }
  return 'bg-[#edf4fb] text-[#1b62a5] font-semibold';
};

// Fetch Data from Server
const loadData = async (date = selectedDate.value) => {
  isLoading.value = true;
  try {
    const url = appPath(`/bms-homepage/queue-heatmap?date=${encodeURIComponent(date)}`);
    const res = await fetch(url);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    if (json.realtime) realtimeData.value = json.realtime;
    if (json.heatmap) heatmapData.value = json.heatmap;
    selectedDate.value = date;
  } catch (err) {
    console.error('Failed to load queue data:', err);
    toastError('ไม่สามารถโหลดข้อมูลคิวได้ กรุณาลองใหม่อีกครั้ง');
  } finally {
    isLoading.value = false;
  }
};

// Set to Today
const setToday = () => {
  const todayStr = new Date().toISOString().slice(0, 10);
  selectedDate.value = todayStr;
  loadData(todayStr);
};

// On Date Picker Change
const onDateChange = () => {
  if (selectedDate.value) {
    loadData(selectedDate.value);
  }
};
</script>

<template>
  <!-- Card Container: Light Theme matching other cards on page -->
  <div class="bg-white rounded-lg border border-[#cbdbe9] shadow-xs overflow-hidden flex flex-col font-prompt">
    
    <!-- Card Header with Navigation Tabs (Only: เรียลไทม์ & สรุปตามวันที่เลือก) -->
    <div class="bg-[#dfeaf5] px-3 py-1.5 border-b border-[#cbdbe9] flex flex-wrap items-center justify-between gap-2 shrink-0">
      
      <!-- 2 Tabs: เรียลไทม์ & สรุปตามวันที่เลือก -->
      <div class="flex items-center gap-1">
        <button
          type="button"
          @click="activeTab = 'realtime'"
          class="px-3 py-1 rounded-md text-xs font-semibold transition-all cursor-pointer flex items-center gap-1.5"
          :class="activeTab === 'realtime' 
            ? 'bg-[#17538d] text-white shadow-xs' 
            : 'text-[#1b62a5] hover:bg-[#d0e2f2]'"
        >
          <Activity class="w-3.5 h-3.5 text-sky-200" />
          <span>เรียลไทม์</span>
          <span 
            v-if="realtimeData?.total_waiting" 
            class="ml-0.5 px-1.5 py-0.2 rounded-full text-[10px] font-mono"
            :class="activeTab === 'realtime' ? 'bg-white/20 text-white' : 'bg-[#17538d]/10 text-[#17538d]'"
          >
            {{ realtimeData.total_waiting }}
          </span>
        </button>

        <button
          type="button"
          @click="activeTab = 'summary'"
          class="px-3 py-1 rounded-md text-xs font-semibold transition-all cursor-pointer flex items-center gap-1.5"
          :class="activeTab === 'summary' 
            ? 'bg-[#17538d] text-white shadow-xs' 
            : 'text-[#1b62a5] hover:bg-[#d0e2f2]'"
        >
          <Flame class="w-3.5 h-3.5 text-amber-300" />
          <span>สรุปตามวันที่เลือก</span>
        </button>
      </div>

      <!-- Controls: ปุ่ม "วันนี้" กับ "เลือกวันที่ ปฏิทินไทย" -->
      <div class="flex items-center gap-1.5 shrink-0">
        <!-- ปุ่ม วันนี้ -->
        <button
          type="button"
          @click="setToday"
          class="px-2.5 py-1 rounded text-xs font-semibold transition-all cursor-pointer border shadow-2xs"
          :class="selectedDate === new Date().toISOString().slice(0, 10)
            ? 'bg-[#17538d] text-white border-[#17538d]' 
            : 'bg-white text-[#17538d] border-[#adc5de] hover:bg-[#f0f6fb]'"
          title="ดูข้อมูลวันปัจจุบัน"
        >
          วันนี้
        </button>

        <!-- ช่องเลือกวันที่ (ปฏิทินไทย) -->
        <div class="flex items-center gap-1 bg-white border border-[#adc5de] rounded px-2 py-0.5 shadow-2xs">
          <Calendar class="w-3.5 h-3.5 text-[#17538d] shrink-0" />
          <input
            type="date"
            v-model="selectedDate"
            @change="onDateChange"
            class="text-[11px] text-[#334155] font-sarabun font-semibold bg-transparent focus:outline-none cursor-pointer"
            title="เลือกวันที่ (ปฏิทิน)"
          />
          <span class="text-[10px] text-slate-400 font-sarabun border-l border-slate-200 pl-1 hidden sm:inline">
            (พ.ศ.)
          </span>
        </div>

        <!-- ปุ่มรีเฟรช -->
        <button
          type="button"
          @click="loadData()"
          :disabled="isLoading"
          class="p-1 rounded bg-white hover:bg-[#f0f6fb] border border-[#adc5de] text-[#17538d] transition-colors cursor-pointer disabled:opacity-50"
          title="รีเฟรชข้อมูลคิวเรียลไทม์"
        >
          <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoading }" />
        </button>
      </div>

    </div>

    <!-- Sub-header bar: Telemetry & Summary -->
    <div class="px-3 py-1 bg-[#f1f6fb] border-b border-[#d8e5f1] flex items-center justify-between text-xs font-sarabun text-[#475569]">
      <div class="flex items-center gap-2 truncate">
        <span v-if="activeTab === 'realtime'" class="font-bold text-[#17538d] flex items-center gap-1.5 truncate">
          <span>ผู้ป่วยกำลังรับบริการ/รอตรวจ ณ จุดบริการต่าง ๆ (ดึงจาก ovst.cur_dep)</span>
        </span>
        <span v-else class="font-bold text-[#17538d] flex items-center gap-1.5 truncate">
          <span>Heatmap จำนวนคิว แยกจุดบริการ × ชั่วโมง</span>
          <span class="font-normal text-slate-500">({{ thaiFormattedDate }})</span>
        </span>

        <span v-if="realtimeData?.is_live || heatmapData?.is_live" class="px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 border border-emerald-300 text-[10px] font-semibold shrink-0">
          ● ข้อมูลสด HOSxP
        </span>
      </div>

      <div class="text-[11px] text-slate-500 shrink-0">
        <span v-if="activeTab === 'realtime'">
          รวมกำลังรับบริการ: <strong class="text-[#17538d] font-mono">{{ realtimeData?.total_waiting ?? 0 }}</strong> คน
        </span>
        <span v-else>
          รวมคิววันนี้: <strong class="text-[#17538d] font-mono">{{ totalQueues.toLocaleString() }}</strong> คิว
        </span>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- TAB 1: เรียลไทม์ (REAL-TIME ovst.cur_dep)                  -->
    <!-- ======================================================== -->
    <div v-if="activeTab === 'realtime'" class="p-2 sm:p-2.5 flex-1 flex flex-col min-h-0 bg-white">
      
      <div class="overflow-x-auto rounded-lg border border-[#d2e2ef] bg-[#fbfdff] max-h-[340px] overflow-y-auto">
        <table class="w-full text-left text-xs font-sarabun border-collapse">
          <thead class="bg-[#eef4fa] text-[#17538d] font-bold sticky top-0 border-b border-[#cbdbe9] z-10">
            <tr>
              <th class="py-1.5 px-3 w-12 text-center font-mono text-[11px]">รหัส</th>
              <th class="py-1.5 px-3">จุดบริการ / แผนก (ovst.cur_dep)</th>
              <th class="py-1.5 px-3 text-right">จำนวนคนไข้ขณะนี้</th>
              <th class="py-1.5 px-3 w-28 text-center">ระดับความหนาแน่น</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="(dept, idx) in realtimeData?.departments || []" 
              :key="dept.depcode || idx"
              class="border-b border-[#e9eff6] hover:bg-[#eaf3fa] transition-colors"
              :class="idx % 2 === 0 ? 'bg-white' : 'bg-[#f8fbfe]'"
            >
              <!-- Depcode -->
              <td class="py-1.5 px-3 text-center font-mono text-[11px] text-slate-400">
                {{ dept.depcode || '-' }}
              </td>

              <!-- Department Name & Bar -->
              <td class="py-1.5 px-3">
                <div class="flex items-center justify-between gap-2">
                  <span class="font-medium text-[#1e293b] text-[11px]">{{ dept.name }}</span>
                </div>
                <div class="w-full bg-[#e2ecf5] h-1.5 rounded-full overflow-hidden mt-1">
                  <div 
                    class="h-full rounded-full transition-all"
                    :class="dept.count >= 30 ? 'bg-rose-500' : (dept.count >= 15 ? 'bg-amber-500' : 'bg-[#1b62a5]')"
                    :style="{ width: `${realtimeData?.total_waiting > 0 ? (dept.count / realtimeData.total_waiting) * 100 : 0}%` }"
                  ></div>
                </div>
              </td>

              <!-- Waiting Count -->
              <td class="py-1.5 px-3 text-right font-mono font-bold text-[12px] text-[#1b62a5]">
                {{ dept.count }} <span class="text-[10px] text-slate-400 font-sarabun font-normal">คน</span>
              </td>

              <!-- Density Badge -->
              <td class="py-1.5 px-3 text-center">
                <span 
                  class="px-2 py-0.5 rounded text-[10px] font-semibold inline-block"
                  :class="dept.count >= 30 
                    ? 'bg-rose-100 text-rose-800 border border-rose-300' 
                    : (dept.count >= 15 
                      ? 'bg-amber-100 text-amber-800 border border-amber-300' 
                      : 'bg-emerald-100 text-emerald-800 border border-emerald-300')"
                >
                  {{ dept.status || (dept.count >= 30 ? 'หนาแน่น' : (dept.count >= 15 ? 'ปานกลาง' : 'ปกติ')) }}
                </span>
              </td>
            </tr>

            <tr v-if="!realtimeData?.departments || realtimeData.departments.length === 0">
              <td colspan="4" class="py-8 text-center text-slate-400 text-xs">
                ไม่มีข้อมูลผู้ป่วยรอรับบริการในขณะนี้
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Realtime Footer Note -->
      <div class="mt-2 pt-1.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500 font-sarabun">
        <span>* แสดงผู้ป่วยที่ลงทะเบียนและยังรับบริการอยู่ภายในโรงพยาบาล ณ จุดบริการปัจจุบัน</span>
        <span class="font-mono text-slate-400">อัปเดต: {{ realtimeData?.query_time || 'เรียลไทม์' }} น.</span>
      </div>

    </div>

    <!-- ======================================================== -->
    <!-- TAB 2: สรุปตามวันที่เลือก (HEATMAP MATRIX)                  -->
    <!-- ======================================================== -->
    <div v-else-if="activeTab === 'summary'" class="p-2 sm:p-2.5 flex-1 flex flex-col min-h-0 bg-white">
      
      <!-- Heatmap Scrollable Table Container (Light Theme) -->
      <div class="overflow-x-auto rounded-lg border border-[#cbdbe9] bg-white max-h-[340px] overflow-y-auto">
        <table class="w-full border-collapse text-[11px]">
          <!-- Table Header (Hours 6 - 19) -->
          <thead class="bg-[#eef4fa] sticky top-0 z-20 border-b border-[#cbdbe9]">
            <tr>
              <th class="py-1.5 px-2.5 text-left text-[#17538d] font-bold sticky left-0 bg-[#eef4fa] z-30 min-w-[140px] max-w-[160px] truncate border-r border-[#cbdbe9]">
                จุดบริการ \ ชม.
              </th>
              <th 
                v-for="hr in hours" 
                :key="hr" 
                class="py-1.5 px-0.5 text-center text-[#17538d] font-mono font-bold w-7 min-w-[28px] border-l border-[#dbe6f0] text-[10px]"
              >
                {{ hr }}
              </th>
            </tr>
          </thead>

          <!-- Table Body (Departments) -->
          <tbody>
            <tr 
              v-for="(dept, idx) in departments" 
              :key="dept.name"
              class="border-b border-[#e9eff6] hover:bg-[#f1f6fb] transition-colors"
              :class="idx % 2 === 0 ? 'bg-white' : 'bg-[#fafcff]'"
            >
              <!-- Department Name (Sticky Left Column) -->
              <td 
                class="py-1 px-2.5 text-left text-[#334155] font-medium sticky left-0 z-10 truncate min-w-[140px] max-w-[160px] border-r border-[#cbdbe9]"
                :class="idx % 2 === 0 ? 'bg-white' : 'bg-[#fafcff]'"
                :title="dept.name"
              >
                <div class="truncate text-[11px] font-sarabun">{{ dept.name }}</div>
              </td>

              <!-- Hourly Density Cells -->
              <td 
                v-for="hr in hours" 
                :key="hr"
                class="py-0.5 px-0.5 text-center border-l border-[#eef4fa] font-mono"
              >
                <div 
                  class="h-6 w-full flex items-center justify-center rounded-xs transition-colors"
                  :class="getCellClass(dept.hours?.[hr])"
                  :title="dept.hours?.[hr] ? `${dept.name} (${hr}:00 น.): ${dept.hours[hr]} คิว` : `${dept.name} (${hr}:00 น.): ไม่มีคิว`"
                >
                  <span>{{ dept.hours?.[hr] ? dept.hours[hr] : '·' }}</span>
                </div>
              </td>
            </tr>

            <tr v-if="departments.length === 0">
              <td :colspan="hours.length + 1" class="py-8 text-center text-slate-400 text-xs font-sarabun">
                ไม่มีข้อมูลคิวในวันที่เลือก ({{ thaiFormattedDate }})
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Bottom Heatmap Legend & Recommendation Note -->
      <div class="mt-2 pt-1.5 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-[11px] text-slate-500 font-sarabun">
        <!-- Legend Indicator -->
        <div class="flex items-center gap-3">
          <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-xs bg-[#edf4fb] border border-[#cbdbe9] inline-block"></span>
            <span>น้อย (&lt; 25)</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-xs bg-[#fef3c7] border border-amber-300 inline-block"></span>
            <span class="text-amber-700 font-medium">ปานกลาง (25-99)</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-xs bg-[#fee2e2] border border-rose-300 inline-block"></span>
            <span class="text-rose-700 font-semibold">หนาแน่น (100+)</span>
          </div>
        </div>

        <!-- Suggestion Note -->
        <div class="text-[11px] text-slate-400 italic">
          💡 สังเกตจุดที่ "ชนกัน" ช่วงเวลาเดียวกัน เพื่อเปิดช่องบริการเพิ่มรองรับ
        </div>
      </div>

    </div>

  </div>
</template>
