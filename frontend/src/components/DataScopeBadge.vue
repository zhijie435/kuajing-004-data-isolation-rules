<template>
  <div class="data-scope-badge">
    <div class="data-scope-badge__icon" :class="iconClass">
      {{ iconText }}
    </div>
    <div class="data-scope-badge__info">
      <span class="data-scope-badge__label">{{ label }}</span>
      <span v-if="showDetail" class="data-scope-badge__detail">
        {{ detailText }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTenant } from '../composables/useTenant'

const props = defineProps({
  showDetail: {
    type: Boolean,
    default: true,
  },
})

const { dataScope, dataScopeLabel, canViewAll, canViewSelfOnly, visibleDeptIds, visibleUserIds, DATA_SCOPE_ENUM } = useTenant()

const iconClass = computed(() => {
  if (canViewAll.value) return 'data-scope-badge__icon--all'
  if (canViewSelfOnly.value) return 'data-scope-badge__icon--self'
  return 'data-scope-badge__icon--partial'
})

const iconText = computed(() => {
  if (canViewAll.value) return '🌐'
  if (canViewSelfOnly.value) return '🔒'
  return '🏢'
})

const label = computed(() => dataScopeLabel.value)

const detailText = computed(() => {
  const scope = dataScope.value
  if (scope === DATA_SCOPE_ENUM.ALL) return '可查看所有租户数据'
  if (scope === DATA_SCOPE_ENUM.TENANT) return '可查看本租户全部数据'
  if (scope === DATA_SCOPE_ENUM.DEPARTMENT) return '仅可查看本部门数据'
  if (scope === DATA_SCOPE_ENUM.DEPARTMENT_AND_SUB) return `可查看本部门及子部门数据（${visibleDeptIds.value.length}个部门）`
  if (scope === DATA_SCOPE_ENUM.CUSTOM) return `可查看自定义部门数据（${visibleDeptIds.value.length}个部门）`
  if (scope === DATA_SCOPE_ENUM.SELF) return '仅可查看本人数据'
  return ''
})
</script>

<style scoped>
.data-scope-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 4px 12px;
  border-radius: 20px;
  background: #f5f5f5;
  font-size: 13px;
}

.data-scope-badge__icon {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
}

.data-scope-badge__icon--all { background: #e6f7ff; }
.data-scope-badge__icon--partial { background: #fff7e6; }
.data-scope-badge__icon--self { background: #fff1f0; }

.data-scope-badge__info {
  display: flex;
  flex-direction: column;
}

.data-scope-badge__label {
  font-weight: 500;
  color: #333;
}

.data-scope-badge__detail {
  font-size: 11px;
  color: #999;
}
</style>
