<template>
  <el-dropdown trigger="click" @command="handleSwitch" @visible-change="onVisibleChange">
    <div class="scope-selector">
      <el-tag :type="switchError ? 'danger' : scopeTagType" effect="light" class="scope-tag" :class="{ 'is-loading': switchLoading }">
        <el-icon v-if="switchLoading" class="loading-icon"><RefreshRight /></el-icon>
        <el-icon v-else><View /></el-icon>
        <span>{{ switchError ? '切换失败' : scopeStore.currentScopeLabel }}</span>
        <el-icon class="arrow" :class="{ 'is-spinning': switchLoading }"><ArrowDown /></el-icon>
      </el-tag>
      <el-tooltip v-if="switchError" :content="`切换失败：${switchError}，点击可重试`" placement="bottom">
        <el-tag type="danger" size="small" effect="dark" class="error-tag" @click.stop="pendingScope !== null && handleSwitch(pendingScope)">
          <el-icon><RefreshRight /></el-icon>
          重试
        </el-tag>
      </el-tooltip>
    </div>
    <template #dropdown>
      <el-dropdown-menu>
        <el-dropdown-item
          v-for="opt in scopeStore.availableScopes"
          :key="opt.value"
          :command="opt.value"
          :disabled="opt.value === scopeStore.currentScope || switchLoading"
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
import { computed, ref, watch } from 'vue'
import { useDataScopeStore } from '@/stores/dataScope'
import { DataScopeLevel } from '@/types'
import {
  View,
  ArrowDown,
  Check,
  RefreshRight
} from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'

const props = defineProps<{
  showLabel?: boolean
}>()

const emit = defineEmits<{
  change: [scope: DataScopeLevel]
}>()

const scopeStore = useDataScopeStore()

const switchLoading = ref(false)
const switchError = ref<string | null>(null)
const pendingScope = ref<DataScopeLevel | null>(null)
const retryCount = ref(0)

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
  const targetScope = scopeValue as DataScopeLevel
  pendingScope.value = targetScope
  switchLoading.value = true
  switchError.value = null

  try {
    await scopeStore.switchScope(targetScope)
    ElMessage.success(`已切换为：${scopeStore.currentScopeLabel}`)
    retryCount.value = 0
    pendingScope.value = null
    emit('change', targetScope)
  } catch (e: any) {
    retryCount.value++
    const errorMsg = e?.message || e?.response?.data?.message || '切换数据范围失败'
    switchError.value = errorMsg
    pendingScope.value = targetScope

    const scopeLabel = scopeStore.availableScopes.find(s => s.value === scopeValue)?.label || `范围${scopeValue}`

    try {
      await ElMessageBox.confirm(
        `切换至「${scopeLabel}」失败，当前仍保持原有数据范围。\n\n错误详情：${errorMsg}`,
        '切换失败',
        {
          confirmButtonText: '重试切换',
          cancelButtonText: '取消',
          type: 'error',
          showClose: true
        }
      )
      await handleSwitch(scopeValue)
    } catch {
      pendingScope.value = null
      switchLoading.value = false
    }
    return
  }
  switchLoading.value = false
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
  display: inline-flex;
  align-items: center;
  gap: 6px;
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

.scope-tag.is-loading {
  pointer-events: none;
  opacity: 0.75;
}

.scope-tag .arrow {
  transition: transform 0.2s;
}

.scope-tag .arrow.is-spinning {
  animation: spin 1s linear infinite;
}

.loading-icon {
  animation: spin 1s linear infinite;
}

.error-tag {
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.error-tag:hover {
  opacity: 0.9;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
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
