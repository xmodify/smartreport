<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { appPath } from '../helpers';
import FlashMessage from './FlashMessage.vue';
import { 
  Building2, 
  LayoutDashboard, 
  ExternalLink, 
  LogOut, 
  ShieldCheck
} from 'lucide-vue-next';

defineProps({
  title: {
    type: String,
    default: 'แผงควบคุมระบบ',
  },
});

const page = usePage();
const auth = computed(() => page.props.auth);
</script>

<template>
  <div class="min-h-screen bg-[#dbe7f3] text-[#1e293b] flex flex-col font-prompt">
    <!-- Admin Topbar (HOSxP Blue) -->
    <header class="bg-gradient-to-r from-[#17538d] to-[#206ab2] text-white border-b border-[#0f3e6c] sticky top-0 z-40 shadow-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-14">
          
          <!-- Brand -->
          <div class="flex items-center gap-3">
            <Link :href="appPath('/')" class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center text-white font-bold border border-white/30">
                <Building2 class="w-5 h-5 text-white" />
              </div>
              <div>
                <span class="font-bold text-sm tracking-tight text-white">Homepage</span>
              </div>
            </Link>
          </div>

          <!-- Nav Links / User Menu -->
          <nav class="flex items-center gap-3">
            <!-- User Menu & Logout -->
            <div class="flex items-center gap-2">
              <span class="text-xs text-blue-100 hidden sm:inline">
                {{ auth?.user?.name || 'Admin' }}
              </span>
              <Link
                :href="appPath('/logout')"
                method="post"
                as="button"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-rose-700/80 hover:bg-rose-700 text-white transition-colors"
                title="ออกจากระบบ"
              >
                <LogOut class="w-3.5 h-3.5" />
                <span class="hidden sm:inline">ออกจากระบบ</span>
              </Link>
            </div>
          </nav>

        </div>
      </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <slot />
    </main>

    <!-- Flash message alerts -->
    <FlashMessage />
  </div>
</template>
