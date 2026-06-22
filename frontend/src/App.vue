<template>
  <TenantProvider>
    <div class="app-layout">
      <header class="app-layout__header">
        <h1>在线课程教务系统</h1>
        <div class="app-layout__user-info">
          <DataScopeBadge />
          <span v-if="state.user" class="app-layout__username">
            {{ state.user.nickname || state.user.username }}
          </span>
        </div>
      </header>
      <main class="app-layout__main">
        <router-view />
      </main>
    </div>
  </TenantProvider>
</template>

<script setup>
import TenantProvider from './components/TenantProvider.vue'
import DataScopeBadge from './components/DataScopeBadge.vue'
import { useTenant } from './composables/useTenant'

const { state } = useTenant()
</script>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #333; }

.app-layout__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 24px;
  background: #fff;
  border-bottom: 1px solid #f0f0f0;
}

.app-layout__header h1 {
  font-size: 18px;
  font-weight: 600;
}

.app-layout__user-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.app-layout__username {
  font-size: 14px;
  color: #666;
}

.app-layout__main {
  padding: 24px;
  max-width: 1200px;
  margin: 0 auto;
}
</style>
