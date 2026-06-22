<template>
  <div class="tenant-provider">
    <div v-if="!state.loaded" class="tenant-provider__loading">
      正在加载租户信息...
    </div>
    <div v-else-if="tenantError" class="tenant-provider__error">
      <p>{{ tenantError }}</p>
      <button class="btn btn--primary" @click="retryInit">重新加载</button>
    </div>
    <slot v-else></slot>
  </div>
</template>

<script setup>
import { ref, onMounted, provide, watch } from 'vue'
import { useTenant } from '../composables/useTenant'

const { state, initTenantContext, reset } = useTenant()

const tenantError = ref(null)

async function retryInit() {
  tenantError.value = null
  try {
    await initTenantContext()
  } catch (e) {
    tenantError.value = '加载租户信息失败，请刷新页面重试'
  }
}

onMounted(async () => {
  try {
    await initTenantContext()
  } catch (e) {
    tenantError.value = '加载租户信息失败，请刷新页面重试'
  }
})

function handleForbidden(event) {
  const { message } = event.detail || {}
  tenantError.value = message || '租户访问被拒绝'
}

function handleUnauthorized() {
  tenantError.value = '登录已过期，请重新登录'
  reset()
}

onMounted(() => {
  window.addEventListener('tenant:forbidden', handleForbidden)
  window.addEventListener('tenant:unauthorized', handleUnauthorized)
})

import { onUnmounted } from 'vue'

onUnmounted(() => {
  window.removeEventListener('tenant:forbidden', handleForbidden)
  window.removeEventListener('tenant:unauthorized', handleUnauthorized)
})
</script>

<style scoped>
.tenant-provider__loading {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 200px;
  color: #999;
}

.tenant-provider__error {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 200px;
  color: #f5222d;
  gap: 12px;
}

.btn--primary {
  padding: 8px 24px;
  background: #1890ff;
  color: #fff;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}
</style>
