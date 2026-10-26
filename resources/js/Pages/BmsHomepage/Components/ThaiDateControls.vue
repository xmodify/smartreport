<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Calendar, RefreshCw, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue', 'refresh']);

const thaiMonthNames = [
  'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
  'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม',
];

const thaiMonthShortNames = [
  'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
  'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.',
];

// Local (not UTC) YYYY-MM-DD so "today" is right around midnight in Thailand
const toDateString = (date) => {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};

const todayString = () => toDateString(new Date());

const formattedThaiDateShort = computed(() => {
  const parts = props.modelValue ? props.modelValue.split('-') : [];
  if (parts.length !== 3) return 'เลือกวันที่';

  return `${parseInt(parts[2], 10)} ${thaiMonthShortNames[parseInt(parts[1], 10) - 1]} ${parseInt(parts[0], 10) + 543}`;
});

const isToday = computed(() => props.modelValue === todayString());

const isCalendarOpen = ref(false);
const calendarRef = ref(null);
const viewYear = ref(new Date().getFullYear());
const viewMonth = ref(new Date().getMonth());

const currentYear = new Date().getFullYear();
const yearOptions = computed(() => {
  const years = [];
  for (let y = currentYear - 5; y <= currentYear + 5; y++) {
    years.push(y);
  }
  return years;
});

const syncCalendarView = () => {
  const parts = props.modelValue ? props.modelValue.split('-') : [];
  if (parts.length === 3) {
    viewYear.value = parseInt(parts[0], 10);
    viewMonth.value = parseInt(parts[1], 10) - 1;
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

// Grid of whole weeks (Sunday first) for the viewed month
const calendarDays = computed(() => {
  const year = viewYear.value;
  const month = viewMonth.value;
  const first = new Date(year, month, 1);
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const today = todayString();
  const cells = [];

  for (let i = first.getDay(); i > 0; i--) {
    cells.push(new Date(year, month, 1 - i));
  }
  for (let d = 1; d <= daysInMonth; d++) {
    cells.push(new Date(year, month, d));
  }
  while (cells.length % 7 !== 0) {
    cells.push(new Date(year, month + 1, cells.length - first.getDay() - daysInMonth + 1));
  }

  return cells.map((date) => {
    const dateStr = toDateString(date);

    return {
      day: date.getDate(),
      dateStr,
      isCurrentMonth: date.getMonth() === month,
      isSelected: dateStr === props.modelValue,
      isToday: dateStr === today,
    };
  });
});

const select = (dateStr) => {
  isCalendarOpen.value = false;
  emit('update:modelValue', dateStr);
};

const stepDay = (days) => {
  const parts = props.modelValue ? props.modelValue.split('-').map(Number) : [];
  if (parts.length !== 3) return;
  const d = new Date(parts[0], parts[1] - 1, parts[2]);
  d.setDate(d.getDate() + days);
  select(toDateString(d));
};

const handleClickOutside = (e) => {
  if (calendarRef.value && !calendarRef.value.contains(e.target)) {
    isCalendarOpen.value = false;
  }
};

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
  <div class="flex items-center gap-1 shrink-0 font-prompt">
    <button
      type="button"
      @click="select(todayString())"
      class="px-2 py-0.5 rounded text-[11px] font-semibold transition-all cursor-pointer border shadow-2xs"
      :class="isToday
        ? 'bg-[#17538d] text-white border-[#17538d]'
        : 'bg-white text-[#17538d] border-[#adc5de] hover:bg-[#eaf1f8]'"
      title="ดูข้อมูลวันปัจจุบัน"
    >
      วันนี้
    </button>

    <button
      type="button"
      @click="stepDay(-1)"
      class="p-1 rounded bg-white hover:bg-[#eaf1f8] border border-[#adc5de] text-[#17538d] transition-colors cursor-pointer"
      title="วันก่อนหน้า"
    >
      <ChevronLeft class="w-3 h-3" />
    </button>

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

      <div
        v-if="isCalendarOpen"
        class="absolute right-0 top-full mt-1 z-50 bg-white border border-[#adc5de] rounded-lg shadow-xl p-3 w-64 text-xs select-none"
      >
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
            <select v-model="viewMonth" class="bg-transparent font-bold text-[#17538d] cursor-pointer focus:outline-none text-xs">
              <option v-for="(mName, idx) in thaiMonthNames" :key="idx" :value="idx">{{ mName }}</option>
            </select>
            <select v-model="viewYear" class="bg-transparent font-bold text-[#17538d] cursor-pointer focus:outline-none text-xs font-mono">
              <option v-for="y in yearOptions" :key="y" :value="y">{{ y + 543 }}</option>
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

        <div class="grid grid-cols-7 gap-1 text-center font-semibold text-[11px] mb-1 font-sarabun">
          <span class="text-rose-600">อา</span>
          <span class="text-slate-600">จ</span>
          <span class="text-slate-600">อ</span>
          <span class="text-slate-600">พ</span>
          <span class="text-slate-600">พฤ</span>
          <span class="text-slate-600">ศ</span>
          <span class="text-indigo-600">ส</span>
        </div>

        <div class="grid grid-cols-7 gap-1 text-center font-sarabun text-xs">
          <button
            v-for="dayObj in calendarDays"
            :key="dayObj.dateStr"
            type="button"
            @click.stop="select(dayObj.dateStr)"
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

        <div class="flex items-center justify-between mt-2.5 pt-1.5 border-t border-[#e2ecf5] text-[11px]">
          <button
            type="button"
            @click.stop="select(todayString())"
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

    <button
      type="button"
      @click="emit('refresh')"
      :disabled="loading"
      class="p-1 rounded bg-white hover:bg-[#eaf1f8] border border-[#adc5de] text-[#17538d] transition-colors cursor-pointer ml-0.5"
      title="รีเฟรชข้อมูล"
    >
      <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': loading }" />
    </button>
  </div>
</template>
