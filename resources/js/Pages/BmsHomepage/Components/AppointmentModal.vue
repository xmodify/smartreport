<script setup>
import { ref, watch, computed } from 'vue';
import { appPath } from '../../../helpers';
import { CalendarDays, CalendarX2, Calendar, Filter, Stethoscope, X, RefreshCw } from 'lucide-vue-next';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  initialData: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['close']);

const data = ref(props.initialData);
const isLoading = ref(false);
const loadError = ref(null);
const dateInput = ref(null);
const selectedDoctors = ref({});

const clinics = computed(() => data.value?.clinics || []);

// Card accent colors (blue, green, purple, amber, rose, cyan, indigo, teal, pink)
const palette = [
  { color: '#2563eb', light: '#eff6ff' },
  { color: '#10b981', light: '#ecfdf5' },
  { color: '#8b5cf6', light: '#f5f3ff' },
  { color: '#f59e0b', light: '#fffbeb' },
  { color: '#f43f5e', light: '#fff1f2' },
  { color: '#06b6d4', light: '#ecfeff' },
  { color: '#6366f1', light: '#f5f7ff' },
  { color: '#14b8a6', light: '#f0fdfa' },
  { color: '#ec4899', light: '#fdf2f8' },
];

const colorAt = (index) => palette[index % palette.length];

const displayCount = (clinic) => {
  const selected = selectedDoctors.value[clinic.name];
  if (!selected || selected === 'all') return clinic.total;
  return clinic.doctors.find((doctor) => doctor.name === selected)?.count ?? 0;
};

const thaiDate = computed(() => data.value?.thai_date || '');

const loadData = async (date) => {
  isLoading.value = true;
  loadError.value = null;
  try {
    const res = await fetch(appPath(`/bms-homepage/appointments?date=${encodeURIComponent(date)}`), {
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    data.value = await res.json();
    selectedDoctors.value = {};
    if (!data.value.success) loadError.value = data.value.error || 'ไม่สามารถโหลดข้อมูลได้';
  } catch (err) {
    console.error('Failed to load appointments:', err);
    loadError.value = 'ไม่สามารถโหลดข้อมูลนัดหมายได้ กรุณาลองใหม่อีกครั้ง';
  } finally {
    isLoading.value = false;
  }
};

const onDateChange = (event) => {
  if (event.target.value) loadData(event.target.value);
};

const openDatePicker = () => {
  dateInput.value?.showPicker?.();
};

watch(() => props.isOpen, (open) => {
  if (open) {
    data.value = props.initialData;
    selectedDoctors.value = {};
    loadError.value = null;
  }
});
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-start justify-center bg-slate-900/50 p-2 sm:p-6 overflow-y-auto"
    @click.self="emit('close')"
  >
    <div class="w-full max-w-5xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden my-auto">
      <!-- Header -->
      <div class="bg-gradient-to-br from-[#1e3a8a] to-[#3b82f6] text-white px-5 py-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-extrabold flex items-center gap-2">
            <CalendarDays class="w-5 h-5 text-blue-300" />
            Dashboard - ตารางนัดหมายผู้ป่วย
          </h2>
          <p class="text-xs text-blue-100 font-sarabun">
            แสดงยอดผู้ป่วยนัดหมายตามคลินิกและรายแพทย์ ประจำวันที่ {{ thaiDate }}
          </p>
        </div>

        <div class="flex items-center gap-2">
          <label class="text-xs font-semibold whitespace-nowrap">เลือกวันที่นัด:</label>
          <div class="relative">
            <button
              type="button"
              class="flex items-center gap-2 bg-white text-slate-800 text-xs font-semibold rounded-lg px-3 py-1.5 shadow-sm cursor-pointer min-w-36"
              @click="openDatePicker"
            >
              <Calendar class="w-3.5 h-3.5 text-blue-600" />
              <span>{{ thaiDate }}</span>
            </button>
            <input
              ref="dateInput"
              type="date"
              :value="data?.selected_date"
              class="absolute inset-0 opacity-0 pointer-events-none"
              tabindex="-1"
              @change="onDateChange"
            />
          </div>
          <button
            type="button"
            class="p-1.5 rounded-lg bg-white/20 hover:bg-white/30 cursor-pointer"
            title="รีเฟรช"
            @click="loadData(data?.selected_date)"
          >
            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isLoading }" />
          </button>
          <button
            type="button"
            class="p-1.5 rounded-lg bg-white/20 hover:bg-white/30 cursor-pointer"
            title="ปิด"
            @click="emit('close')"
          >
            <X class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Error -->
      <div v-if="loadError" class="m-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
        {{ loadError }}
      </div>

      <!-- Empty -->
      <div v-else-if="clinics.length === 0" class="py-16 text-center text-slate-500 font-sarabun">
        <CalendarX2 class="w-14 h-14 mx-auto mb-3 text-slate-300" />
        <h3 class="text-base font-bold">ไม่มีรายการนัดหมายผู้ป่วย</h3>
        <p class="text-xs text-slate-400">ไม่พบข้อมูลการนัดหมายในระบบสำหรับวันที่เลือก</p>
      </div>

      <!-- Clinic cards -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4 sm:p-5 bg-slate-50 max-h-[70vh] overflow-y-auto">
        <div
          v-for="(clinic, index) in clinics"
          :key="clinic.name"
          class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs flex flex-col"
          :style="{ borderLeft: `6px solid ${colorAt(index).color}` }"
        >
          <div class="flex items-start justify-between gap-2">
            <h3 class="text-sm font-bold text-slate-800 leading-snug" :title="clinic.name">{{ clinic.name }}</h3>
            <div
              class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
              :style="{ backgroundColor: colorAt(index).light, color: colorAt(index).color }"
            >
              <Stethoscope class="w-4 h-4" />
            </div>
          </div>

          <div class="text-center my-4">
            <span class="text-4xl font-black" :style="{ color: colorAt(index).color }">{{ displayCount(clinic) }}</span>
            <span class="text-xs font-semibold text-slate-500 ml-1">ราย</span>
          </div>

          <div class="mt-auto border-t border-dashed border-slate-200 pt-3 space-y-2">
            <label class="text-[11px] font-bold text-slate-500 flex items-center gap-1">
              <Filter class="w-3 h-3" />เลือกแพทย์ผู้ตรวจ:
            </label>
            <select
              v-model="selectedDoctors[clinic.name]"
              class="w-full text-xs font-semibold text-slate-700 bg-slate-50 border border-slate-300 rounded-lg px-2 py-1.5 outline-none cursor-pointer"
            >
              <option value="all">แพทย์ทุกคน (ทั้งหมด)</option>
              <option v-for="doctor in clinic.doctors" :key="doctor.name" :value="doctor.name">{{ doctor.name }}</option>
            </select>

            <div class="max-h-20 overflow-y-auto pr-1 text-[11px] text-slate-500">
              <div
                v-for="doctor in clinic.doctors"
                :key="doctor.name"
                class="flex justify-between py-0.5 border-b border-slate-100 last:border-b-0"
              >
                <span>{{ doctor.name }}</span>
                <span class="font-bold" :style="{ color: colorAt(index).color }">{{ doctor.count }} ราย</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
