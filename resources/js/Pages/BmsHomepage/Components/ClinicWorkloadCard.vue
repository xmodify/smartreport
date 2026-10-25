<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { appPath } from '../../../helpers';
import { toastError } from '../../../sweetalert';
import { 
  Calendar, 
  RefreshCw, 
  ChevronLeft, 
  ChevronRight,
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
const selectedDate = ref(props.initialData?.selected_date || new Date().toISOString().slice(0, 10));

// Clinic Data State
const clinicData = ref(props.initialData || {});
const isLoading = ref(false);

const departments = computed(() => clinicData.value?.departments || []);
const totalPatients = computed(() => clinicData.value?.total_patients || 0);
const isLive = computed(() => Boolean(clinicData.value?.is_live));

// Thai Months Names
const thaiMonthNames = [
  'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
  'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
];

const thaiMonthShortNames = [
  'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
  'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
];

// Display formatted Thai Date (e.g. 22 ต.ค. 2569) for the button
const formattedThaiDateShort = computed(() => {
  if (!selectedDate.value) return 'เลือกวันที่';
  try {
    const parts = selectedDate.value.split('-');
    if (parts.length === 3) {
      const d = parseInt(parts[2], 10);
      const mIdx = parseInt(parts[1], 10) - 1;
      const thYear = parseInt(parts[0], 10) + 543;
      return `${d} ${thaiMonthShortNames[mIdx]} ${thYear}`;
    }
  } catch (e) {
    // fallback
  }
  return selectedDate.value;
});

// Thai Full Formatted Date for Sub-info Bar (e.g. 22 ตุลาคม 2569)
const thaiFormattedDate = computed(() => {
  if (clinicData.value?.thai_date) {
    return clinicData.value.thai_date;
  }
  if (!selectedDate.value) return 'วันนี้';
  try {
    const parts = selectedDate.value.split('-');
    if (parts.length === 3) {
      const d = parseInt(parts[2], 10);
      const mIdx = parseInt(parts[1], 10) - 1;
      const thYear = parseInt(parts[0], 10) + 543;
      return `${d} ${thaiMonthNames[mIdx]} ${thYear}`;
    }
  } catch (e) {
    // fallback
  }
  return selectedDate.value;
});

// Check if selected date is today
const isToday = computed(() => {
  const todayStr = new Date().toISOString().slice(0, 10);
  return selectedDate.value === todayStr;
});

// Custom Thai Calendar Popup State
const isCalendarOpen = ref(false);
const calendarRef = ref(null);
const viewYear = ref(new Date().getFullYear());
const viewMonth = ref(new Date().getMonth());

// Generate available years for selector (e.g. 2563 - 2573 / 2020 - 2030)
const currentYear = new Date().getFullYear();
const yearOptions = computed(() => {
  const years = [];
  for (let y = currentYear - 5; y <= currentYear + 5; y++) {
    years.push(y);
  }
  return years;
});

// Sync calendar view when selectedDate changes or popup opens
const syncCalendarView = () => {
  if (selectedDate.value) {
    const parts = selectedDate.value.split('-');
    if (parts.length === 3) {
      viewYear.value = parseInt(parts[0], 10);
      viewMonth.value = parseInt(parts[1], 10) - 1;
    }
  }
};

const toggleCalendar = () => {
  isCalendarOpen.value = !isCalendarOpen.value;
  if (isCalendarOpen.value) {
    syncCalendarView();
  }
};

const prevMonth = () => {
  if (viewMonth.value === 0) {
    viewMonth.value = 11;
    viewYear.value--;
  } else {
    viewMonth.value--;
  }
};

const nextMonth = () => {
  if (viewMonth.value === 11) {
    viewMonth.value = 0;
    viewYear.value++;
  } else {
    viewMonth.value++;
  }
};

// Compute 35 or 42 grid cells for the current month view
const calendarDays = computed(() => {
  const year = viewYear.value;
  const month = viewMonth.value;

  const firstDayOfWeek = new Date(year, month, 1).getDay(); // 0 = Sun
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const prevMonthDays = new Date(year, month, 0).getDate();

  const days = [];
  const todayStr = new Date().toISOString().slice(0, 10);

  // Prev month padding
  for (let i = firstDayOfWeek - 1; i >= 0; i--) {
    const d = prevMonthDays - i;
    const prevM = month === 0 ? 12 : month;
    const prevY = month === 0 ? year - 1 : year;
    const dateStr = `${prevY}-${String(prevM).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    days.push({
      day: d,
      dateStr,
      isCurrentMonth: false,
      isSelected: dateStr === selectedDate.value,
      isToday: dateStr === todayStr,
    });
  }

  // Current month days
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    days.push({
      day: d,
      dateStr,
      isCurrentMonth: true,
      isSelected: dateStr === selectedDate.value,
      isToday: dateStr === todayStr,
    });
  }

  // Next month padding to fill complete weeks
  const remaining = (Math.ceil(days.length / 7) * 7) - days.length;
  for (let d = 1; d <= remaining; d++) {
    const nextM = month === 11 ? 1 : month + 2;
    const nextY = month === 11 ? year + 1 : year;
    const dateStr = `${nextY}-${String(nextM).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    days.push({
      day: d,
      dateStr,
      isCurrentMonth: false,
      isSelected: dateStr === selectedDate.value,
      isToday: dateStr === todayStr,
    });
  }

  return days;
});

// Click outside handler
const handleClickOutside = (e) => {
  if (calendarRef.value && !calendarRef.value.contains(e.target)) {
    isCalendarOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});

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

// Select Day from Thai Calendar
const selectDay = (dayObj) => {
  selectedDate.value = dayObj.dateStr;
  isCalendarOpen.value = false;
  loadData(dayObj.dateStr);
};

// Set to Today
const setToday = () => {
  const todayStr = new Date().toISOString().slice(0, 10);
  selectedDate.value = todayStr;
  isCalendarOpen.value = false;
  loadData(todayStr);
};

// Set Today from inside Popup
const setTodayFromPopup = () => {
  setToday();
};

// Step Day (-1 or +1)
const stepDay = (days) => {
  if (!selectedDate.value) return;
  const parts = selectedDate.value.split('-').map(Number);
  const d = new Date(parts[0], parts[1] - 1, parts[2]);
  d.setDate(d.getDate() + days);
  const nextY = d.getFullYear();
  const nextM = String(d.getMonth() + 1).padStart(2, '0');
  const nextD = String(d.getDate()).padStart(2, '0');
  const nextDateStr = `${nextY}-${nextM}-${nextD}`;
  selectedDate.value = nextDateStr;
  loadData(nextDateStr);
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
          title="ไม่สามารถเชื่อมต่อ BMS-Session-Gateway"
        >
          <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
          ไม่สามารถเชื่อมต่อ BMS-Session-Gateway
        </span>
      </div>

      <!-- Right: Thai Calendar Date Picker Controls (มุมขวาบน ปฏิทินไทย) -->
      <div class="flex items-center gap-1 shrink-0">
        
        <!-- Button วันนี้ -->
        <button
          type="button"
          @click="setToday"
          class="px-2 py-0.5 rounded text-[11px] font-semibold transition-all cursor-pointer border shadow-2xs"
          :class="isToday 
            ? 'bg-[#17538d] text-white border-[#17538d]' 
            : 'bg-white text-[#17538d] border-[#adc5de] hover:bg-[#eaf1f8]'"
          title="ดูข้อมูลวันปัจจุบัน"
        >
          วันนี้
        </button>

        <!-- Prev / Next Day Buttons -->
        <button
          type="button"
          @click="stepDay(-1)"
          class="p-1 rounded bg-white hover:bg-[#eaf1f8] border border-[#adc5de] text-[#17538d] transition-colors cursor-pointer"
          title="วันก่อนหน้า"
        >
          <ChevronLeft class="w-3 h-3" />
        </button>

        <!-- Custom Thai Calendar Popover Trigger (ปฏิทินไทย) -->
        <div class="relative" ref="calendarRef">
          <button
            type="button"
            @click="toggleCalendar"
            class="flex items-center gap-1.5 bg-white border border-[#adc5de] hover:border-[#17538d] rounded px-2 py-0.5 shadow-2xs text-[#1e293b] hover:bg-[#f0f6fb] transition-all cursor-pointer"
            title="คลิกเพื่อเปิดปฏิทินไทย"
          >
            <Calendar class="w-3.5 h-3.5 text-[#17538d] shrink-0" />
            <span class="text-[11px] font-sarabun font-semibold text-[#17538d]">
              {{ formattedThaiDateShort }}
            </span>
          </button>

          <!-- 100% Thai Calendar Dropdown Popup -->
          <div 
            v-if="isCalendarOpen"
            class="absolute right-0 top-full mt-1 z-50 bg-white border border-[#adc5de] rounded-lg shadow-xl p-3 w-64 font-prompt text-xs select-none animate-in fade-in zoom-in-95 duration-100"
          >
            <!-- Calendar Month & Buddhist Year Navigation -->
            <div class="flex items-center justify-between mb-2 pb-1.5 border-b border-[#e2ecf5]">
              <button
                type="button"
                @click.stop="prevMonth"
                class="p-1 rounded hover:bg-[#eaf1f8] text-[#17538d] transition-colors cursor-pointer"
                title="เดือนก่อนหน้า"
              >
                <ChevronLeft class="w-3.5 h-3.5" />
              </button>

              <div class="flex items-center gap-1 font-bold text-[#17538d] text-xs">
                <select 
                  v-model="viewMonth" 
                  class="bg-transparent font-bold text-[#17538d] cursor-pointer focus:outline-none text-xs"
                >
                  <option v-for="(mName, idx) in thaiMonthNames" :key="idx" :value="idx">
                    {{ mName }}
                  </option>
                </select>
                <select 
                  v-model="viewYear" 
                  class="bg-transparent font-bold text-[#17538d] cursor-pointer focus:outline-none text-xs font-mono"
                >
                  <option v-for="y in yearOptions" :key="y" :value="y">
                    {{ y + 543 }}
                  </option>
                </select>
              </div>

              <button
                type="button"
                @click.stop="nextMonth"
                class="p-1 rounded hover:bg-[#eaf1f8] text-[#17538d] transition-colors cursor-pointer"
                title="เดือนถัดไป"
              >
                <ChevronRight class="w-3.5 h-3.5" />
              </button>
            </div>

            <!-- Thai Weekday Names (อา จ อ พ พฤ ศ ส) -->
            <div class="grid grid-cols-7 gap-1 text-center font-semibold text-[11px] mb-1 font-sarabun">
              <span class="text-rose-600">อา</span>
              <span class="text-slate-600">จ</span>
              <span class="text-slate-600">อ</span>
              <span class="text-slate-600">พ</span>
              <span class="text-slate-600">พฤ</span>
              <span class="text-slate-600">ศ</span>
              <span class="text-indigo-600">ส</span>
            </div>

            <!-- Calendar Days Grid -->
            <div class="grid grid-cols-7 gap-1 text-center font-sarabun text-xs">
              <button
                v-for="(dayObj, idx) in calendarDays"
                :key="idx"
                type="button"
                @click.stop="selectDay(dayObj)"
                class="h-7 w-7 mx-auto flex items-center justify-center rounded-md text-[11px] font-medium transition-all cursor-pointer"
                :class="[
                  dayObj.isSelected 
                    ? 'bg-[#17538d] text-white font-bold shadow-xs' 
                    : (dayObj.isToday 
                        ? 'border border-[#17538d] text-[#17538d] font-bold hover:bg-[#eaf1f8]' 
                        : (dayObj.isCurrentMonth 
                            ? 'text-[#1e293b] hover:bg-[#f0f6fb]' 
                            : 'text-slate-300 hover:bg-slate-50')),
                ]"
              >
                {{ dayObj.day }}
              </button>
            </div>

            <!-- Popup Footer: ปุ่มวันนี้ & ปุ่มปิด -->
            <div class="flex items-center justify-between mt-2.5 pt-1.5 border-t border-[#e2ecf5] text-[11px]">
              <button
                type="button"
                @click.stop="setTodayFromPopup"
                class="px-2 py-0.5 rounded text-[#17538d] hover:bg-[#eaf1f8] font-semibold cursor-pointer"
              >
                วันนี้
              </button>
              <button
                type="button"
                @click.stop="isCalendarOpen = false"
                class="px-2 py-0.5 rounded text-slate-500 hover:bg-slate-100 cursor-pointer"
              >
                ปิด
              </button>
            </div>
          </div>
        </div>

        <button
          type="button"
          @click="stepDay(1)"
          class="p-1 rounded bg-white hover:bg-[#eaf1f8] border border-[#adc5de] text-[#17538d] transition-colors cursor-pointer"
          title="วันถัดไป"
        >
          <ChevronRight class="w-3 h-3" />
        </button>

        <!-- Refresh Button -->
        <button
          type="button"
          @click="loadData()"
          :disabled="isLoading"
          class="p-1 rounded bg-white hover:bg-[#eaf1f8] border border-[#adc5de] text-[#17538d] transition-colors cursor-pointer ml-0.5"
          title="รีเฟรชข้อมูล"
        >
          <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin text-[#17538d]': isLoading }" />
        </button>

      </div>

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
    <div v-if="departments.length > 0" class="space-y-2 font-sarabun text-xs">
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
        <p class="text-xs font-bold text-rose-600">ไม่สามารถเชื่อมต่อ BMS-Session-Gateway</p>
        <p class="text-[10px] text-slate-500">กรุณาเปิดระบบผ่านโปรแกรม HOSxP หรือเชื่อมต่อ BMS Session ด้วยตนเอง</p>
      </div>
      <div v-else class="space-y-1 text-slate-400">
        <Info class="w-6 h-6 mx-auto mb-1 text-slate-300" />
        <p class="text-xs font-medium text-slate-500">ไม่มีข้อมูลผู้รับบริการในวันที่ {{ thaiFormattedDate }}</p>
        <p class="text-[10px] text-slate-400 mt-0.5">กรุณาเลือกวันอื่นที่มีประวัติผู้รับบริการ หรือกดปุ่ม "วันนี้"</p>
      </div>
    </div>

  </div>
</template>
