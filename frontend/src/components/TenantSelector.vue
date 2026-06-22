<template>
  <el-dropdown trigger="click" @command="handleCommand" class="tenant-selector">
    <div class="tenant-btn">
      <el-icon><OfficeBuilding /></el-icon>
      <span class="tenant-text">
        <template v-if="tenantStore.isSuperAdminMode && userStore.role === 'super_admin'">
          <el-tag type="danger" effect="dark" size="small">跨租户模式</el-tag>
        </template>
        <template v-else-if="tenantStore.currentTenantName">
          {{ tenantStore.currentTenantName }}
        </template>
        <template v-else>
          选择租户
        </template>
      </span>
      <el-icon class="arrow"><ArrowDown /></el-icon>
    </div>
    <template #dropdown>
      <el-dropdown-menu v-if="userStore.role === 'super_admin'">
        <el-dropdown-item
          command="__all__"
          :class="{ 'is-active': tenantStore.isSuperAdminMode }"
        >
          <el-icon><Monitor /></el-icon>
          <span>跨租户（全部数据）</span>
        </el-dropdown-item>
        <el-dropdown-item divided></el-dropdown-item>
        <el-dropdown-item
          v-for="t in tenantStore.availableTenants"
          :key="t.id"
          :command="String(t.id)"
          :class="{ 'is-active': tenantStore.currentTenantId === t.id }"
        >
          <el-icon><Building /></el-icon>
          <span>{{ t.name }}</span>
          <el-tag size="small" style="margin-left: 8px">ID: {{ t.id }}</el-tag>
        </el-dropdown-item>
      </el-dropdown-menu>
      <el-dropdown-menu v-else>
        <el-dropdown-item disabled>
          <el-icon><Lock /></el-icon>
          <span>当前角色不可切换</span>
        </el-dropdown-item>
        <el-dropdown-item divided></el-dropdown-item>
        <el-dropdown-item>
          <el-icon><OfficeBuilding /></el-icon>
          <span>{{ userStore.userInfo?.tenant_name }}</span>
        </el-dropdown-item>
      </el-dropdown-menu>
    </template>
  </el-dropdown>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useTenantStore } from '@/stores/tenant'
import { useUserStore } from '@/stores/user'
import { useDataScopeStore } from '@/stores/dataScope'
import {
  OfficeBuilding,
  ArrowDown,
  Building,
  Monitor,
  Lock
} from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'

const emit = defineEmits<{
  change: [tenantId: number | null]
}>()

const tenantStore = useTenantStore()
const userStore = useUserStore()
const scopeStore = useDataScopeStore()

async function handleCommand(command: string | number) {
  let newTenantId: number | null = null

  if (command === '__all__') {
    if (userStore.role !== 'super_admin') {
      ElMessage.error('仅有超级管理员可使用跨租户模式')
      return
    }
    try {
      await ElMessageBox.confirm(
        '跨租户模式将显示所有租户数据，确认切换？',
        '切换租户',
        {
          confirmButtonText: '确认',
          cancelButtonText: '取消',
          type: 'warning'
        }
      )
      newTenantId = null
    } catch {
      return
    }
  } else {
    newTenantId = Number(command)
  }

  tenantStore.switchTenant(newTenantId)
  scopeStore.reset()
  emit('change', newTenantId)
  ElMessage.success(
    newTenantId === null
      ? '已切换至跨租户模式'
      : '已切换至：' + tenantStore.currentTenantName
  )
  setTimeout(() => {
    window.location.reload()
  }, 300)
}

onMounted(async () => {
  if (!tenantStore.initialized) {
    await tenantStore.fetchTenants()
  }
})
</script>

<style scoped>
.tenant-selector {
  min-width: 200px;
}

.tenant-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  background: var(--el-fill-color-light);
  border: 1px solid var(--el-border-color);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.tenant-btn:hover {
  border-color: var(--el-color-primary);
  background: var(--el-color-primary-light-9);
}

.tenant-text {
  flex: 1;
  font-size: 13px;
  color: var(--el-text-color-primary);
}

.arrow {
  font-size: 12px;
  color: var(--el-text-color-secondary);
  transition: transform 0.2s;
}

:deep(.el-dropdown-menu__item.is-active) {
  color: var(--el-color-primary);
  background: var(--el-color-primary-light-9);
}
</style>
