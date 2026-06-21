<template>
  <el-dropdown trigger="click" @command="handleSwitch" @visible-change="onVisibleChange">
    <div class="scope-selector">
      <el-tag :type="scopeTagType" effect="light" class="scope-tag">
        <el-icon><View /></el-icon>
        <span>{{ scopeStore.currentScopeLabel }}</span>
        <el-icon class="arrow"><ArrowDown /></el-icon>
      </el-tag>
    </div>
    <template #dropdown>
      <el-dropdown-menu>
        <el-dropdown-item
          v-for="opt in scopeStore.availableScopes"
          :key="opt.value"
          :command="opt.value"
          :disabled="opt.value === scopeStore.currentScope"
          :class="{ 'is-active': opt.value === scopeStore.currentScope }"
        >
          <el-icon v-if="opt.value === scopeStore.currentScope"><Check /></el-icon>
          <span>{{ opt.label }}</span>
          <span
            v-if="opt.value === scopeStore.currentScope"
            class="current-marker"
          >(当前)</span>
        </el-dropdown-item>
      </el-dropdown-menu>
    </template>
  </el-dropdown>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useDataScopeStore } from '@/stores/dataScope'
import { DataScopeLevel } from '@/types'
import {
  View,
  ArrowDown,
  Check
} from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'

const props = defineProps<{
  showLabel?: boolean
}>()

const emit = defineEmits<{
  change: [scope: DataScopeLevel]
}>()

const scopeStore = useDataScopeStore()

const scopeTagType = computed(() => {
  switch (scopeStore.currentScope) {
    case DataScopeLevel.ALL:
      return 'danger'
    case DataScopeLevel.TENANT:
      return 'warning'
    case DataScopeLevel.DEPARTMENT:
      return 'primary'
    case DataScopeLevel.TEAM:
      return 'success'
    default:
      return 'info'
  }
})

async function handleSwitch(scopeValue: number) {
  try {
    await scopeStore.switchScope(scopeValue as DataScopeLevel)
    ElMessage.success(`已切换为：' + scopeStore.currentScopeLabel)
    emit('change', scopeValue as DataScopeLevel)
  } catch (e) {
    ElMessage.error('切换失败')
  }
}

function onVisibleChange(visible: boolean) {
  if (visible && scopeStore.availableScopes.length === 0) {
    scopeStore.fetchAvailableScopes()
  }
}

watch(
  () => scopeStore.currentScope,
  (newVal) => {
    emit('change', newVal)
  }
)
</script>

<style scoped>
.scope-selector {
  cursor: pointer;
}

.scope-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  height: auto;
  font-size: 13px;
}

.scope-tag:hover {
  opacity: 0.9;
}

.scope-tag .arrow {
  transition: transform 0.2s;
}

:deep(.el-dropdown-menu__item.is-active) {
  color: var(--el-color-primary);
  background: var(--el-color-primary-light-9);
}

.current-marker {
  margin-left: 8px;
  color: var(--el-text-color-secondary);
  font-size: 12px;
}
</style>
