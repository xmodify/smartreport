<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { appPath } from '../../helpers';
import { 
  ShieldCheck, 
  LogOut, 
  ExternalLink, 
  Layers, 
  PlusCircle, 
  Sparkles, 
  Activity, 
  Server, 
  Database,
  ArrowUpRight,
  FolderKanban
} from 'lucide-vue-next';

const props = defineProps({
  adminUser: {
    type: Object,
    default: () => ({ name: 'Admin', email: 'admin@bms.local' }),
  },
  systems: {
    type: Array,
    default: () => [],
  },
  stats: {
    type: Object,
    default: () => ({ active_sessions: 0, total_posters: 0 }),
  },
});

const logoutForm = useForm({});

const handleLogout = () => {
  logoutForm.post(appPath('/logout'));
};
</script>

<template>
  <Head title="System Admin Dashboard - SmartReport" />

  <div class="min-h-screen bg-[#f1f5f9] flex flex-col font-prompt selection:bg-[#1b62a5] selection:text-white">
    
    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-20 shadow-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
        
        <!-- Brand & Title -->
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-[#17538d] to-[#2577c4] flex items-center justify-center text-white shadow-xs">
            <ShieldCheck class="w-4 h-4 text-white" />
          </div>
          <div>
            <div class="text-sm font-bold text-slate-800 leading-none">SmartReport System Administration</div>
            <div class="text-[10px] text-slate-400 font-sarabun">แดชบอร์ดจัดการระบบส่วนกลาง</div>
          </div>
        </div>

        <!-- Right User Actions -->
        <div class="flex items-center gap-3">
          
          <!-- Quick link to SmartReport Homepage -->
          <Link
            :href="appPath('/bms-homepage')"
            class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-50 hover:bg-blue-100 text-[#17538d] border border-blue-200 transition-colors"
          >
            <ExternalLink class="w-3.5 h-3.5" />
            <span>เปิดหน้า SmartReport Homepage</span>
          </Link>

          <!-- Admin User Badge -->
          <div class="flex items-center gap-2 pl-3 border-l border-slate-200">
            <div class="w-7 h-7 rounded-full bg-[#17538d] text-white text-xs font-bold flex items-center justify-center">
              {{ (adminUser?.name || 'A').charAt(0) }}
            </div>
            <div class="hidden md:block text-left">
              <div class="text-xs font-semibold text-slate-700 leading-none">{{ adminUser?.name }}</div>
              <div class="text-[10px] text-slate-400 font-mono">{{ adminUser?.email }}</div>
            </div>
          </div>

          <!-- Logout Button -->
          <button
            type="button"
            @click="handleLogout"
            class="p-2 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-100 transition-colors"
            title="ออกจากระบบ"
          >
            <LogOut class="w-4 h-4" />
          </button>

        </div>

      </div>
    </header>

    <!-- Main Admin Workspace -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
      
      <!-- Welcome Banner -->
      <div class="bg-gradient-to-r from-[#17538d] via-[#1c5f9f] to-[#2577c4] rounded-2xl p-6 text-white shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-white/15 text-blue-100 text-[11px] font-medium mb-2 border border-white/20">
            <Sparkles class="w-3 h-3 text-amber-300" />
            <span>SmartReport Modular Suite • พร้อมสำหรับการต่อยอด</span>
          </div>
          <h1 class="text-xl sm:text-2xl font-black tracking-tight">
            ยินดีต้อนรับสู่ระบบผู้ดูแลกลาง (System Admin)
          </h1>
          <p class="text-xs sm:text-sm text-blue-100 font-sarabun mt-1">
            พื้นที่สำหรับดูแลโครงสร้างระบบ จัดการสิทธิ์ และเชื่อมต่อโมดูลใหม่ๆ ของ HOSxP
          </p>
        </div>

        <div class="flex items-center gap-2">
          <Link
            :href="appPath('/bms-homepage')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white hover:bg-blue-50 text-[#17538d] text-xs font-bold transition-all shadow-sm"
          >
            <span>เปิดหน้าแรก SmartReport Homepage</span>
            <ArrowUpRight class="w-4 h-4" />
          </Link>
        </div>
      </div>

      <!-- Quick Platform Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
            <Activity class="w-5 h-5" />
          </div>
          <div>
            <div class="text-[11px] font-medium text-slate-500">สถานะแพลตฟอร์ม</div>
            <div class="text-base font-bold text-slate-800 flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
              <span>พร้อมทำงาน (Online)</span>
            </div>
          </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-blue-50 text-[#17538d] flex items-center justify-center shrink-0">
            <Layers class="w-5 h-5" />
          </div>
          <div>
            <div class="text-[11px] font-medium text-slate-500">ระบบที่ติดตั้งในโปรเจกต์</div>
            <div class="text-base font-bold text-slate-800">1 ระบบ (SmartReport Homepage)</div>
          </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
            <Database class="w-5 h-5" />
          </div>
          <div>
            <div class="text-[11px] font-medium text-slate-500">BMS Session ที่บันทึก</div>
            <div class="text-base font-bold text-slate-800">{{ stats?.active_sessions || 0 }} เซสชัน</div>
          </div>
        </div>

      </div>

      <!-- Systems / Modules Section -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-sm font-bold text-slate-800 flex items-center gap-1.5">
              <FolderKanban class="w-4 h-4 text-[#17538d]" />
              <span>ระบบย่อยที่แยกการทำงาน (Modules)</span>
            </h2>
            <p class="text-xs text-slate-400 font-sarabun">แต่ละระบบแยกหน้าและคอนโทรลเลอร์อิสระออกจากกัน</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          
          <!-- System 1: BMS Homepage (Active) -->
          <div class="bg-white rounded-xl border border-blue-200 shadow-xs p-5 flex flex-col justify-between hover:border-[#17538d] transition-all relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-24 h-24 bg-blue-50 rounded-full -mr-8 -mt-8 transition-transform group-hover:scale-110 pointer-events-none"></div>

            <div class="relative">
              <div class="flex items-center justify-between mb-3">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-[#17538d]">
                  ระบบที่ 1 (Active)
                </span>
                <span class="text-[10px] text-slate-400 font-mono">v1.0.0</span>
              </div>

              <h3 class="text-base font-bold text-slate-800 group-hover:text-[#17538d] transition-colors">
                SmartReport Homepage
              </h3>
              <p class="text-xs text-slate-500 font-sarabun mt-1">
                ระบบหน้าแรก HOSxP แสดงข้อมูลสถิติโรงพยาบาลและภาพโปสเตอร์ประชาสัมพันธ์ พร้อมระบบจัดการโปสเตอร์สำหรับผู้ดูแล HOSxP
              </p>

              <div class="mt-4 pt-3 border-t border-slate-100 text-[11px] font-mono text-slate-400 flex items-center gap-1">
                <span>URL:</span>
                <span class="text-[#17538d] font-semibold">/bms-homepage (หรือ /smartreport/bms-homepage)</span>
              </div>
            </div>

            <div class="mt-4 pt-2">
              <Link
                :href="appPath('/bms-homepage')"
                class="w-full inline-flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg bg-[#17538d] hover:bg-[#124778] text-white text-xs font-semibold shadow-xs transition-colors"
              >
                <span>เปิดดูหน้าระบบ</span>
                <ArrowUpRight class="w-3.5 h-3.5" />
              </Link>
            </div>
          </div>

          <!-- Placeholder Module 2 -->
          <div class="bg-slate-50/60 rounded-xl border-2 border-dashed border-slate-300 p-5 flex flex-col items-center justify-center text-center min-h-[200px] hover:border-slate-400 transition-colors">
            <div class="w-10 h-10 rounded-full bg-slate-200/80 text-slate-500 flex items-center justify-center mb-2">
              <PlusCircle class="w-5 h-5" />
            </div>
            <div class="text-xs font-bold text-slate-700">พร้อมต่อยอดระบบที่ 2</div>
            <p class="text-[11px] text-slate-400 font-sarabun mt-0.5 max-w-[200px]">
              สามารถเพิ่ม Controller ในโฟลเดอร์โมดูลใหม่ และสร้างหน้าใน Pages ได้อย่างอิสระ
            </p>
          </div>

          <!-- Placeholder Module 3 -->
          <div class="bg-slate-50/60 rounded-xl border-2 border-dashed border-slate-300 p-5 flex flex-col items-center justify-center text-center min-h-[200px] hover:border-slate-400 transition-colors">
            <div class="w-10 h-10 rounded-full bg-slate-200/80 text-slate-500 flex items-center justify-center mb-2">
              <PlusCircle class="w-5 h-5" />
            </div>
            <div class="text-xs font-bold text-slate-700">พร้อมต่อยอดระบบที่ 3</div>
            <p class="text-[11px] text-slate-400 font-sarabun mt-0.5 max-w-[200px]">
              ระบบถูกวางโครงสร้างไว้ให้ขยายได้หลายระบบโดยไม่ชนกัน
            </p>
          </div>

        </div>
      </div>

      <!-- Clean Blank Canvas Section (ตามที่ผู้ใช้สั่ง: "ออกแบบหน้าใหม่เปล่า ๆ ยังไม่มีอะไร") -->
      <div class="bg-white rounded-xl border border-slate-200 p-8 text-center space-y-2">
        <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center">
          <Server class="w-6 h-6" />
        </div>
        <h3 class="text-sm font-bold text-slate-700">พื้นที่หน้า Admin ระบบ (พร้อมสำหรับฟังก์ชันใหม่)</h3>
        <p class="text-xs text-slate-400 font-sarabun max-w-md mx-auto">
          หน้านี้ถูกสร้างขึ้นแยกจากหน้าแรกของแต่ละระบบ เพื่อให้คุณสามารถเพิ่มเติมฟังก์ชันบริหารจัดการส่วนกลางได้ตามต้องการ
        </p>
      </div>

    </main>

  </div>
</template>
