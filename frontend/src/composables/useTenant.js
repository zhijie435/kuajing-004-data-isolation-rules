import { ref, computed, readonly } from 'vue'

const DATA_SCOPE_ENUM = {
  ALL: 1,
  TENANT: 2,
  DEPARTMENT: 3,
  DEPARTMENT_AND_SUB: 4,
  SELF: 5,
  CUSTOM: 6,
}

const DATA_SCOPE_LABELS = {
  [DATA_SCOPE_ENUM.ALL]: '全部数据',
  [DATA_SCOPE_ENUM.TENANT]: '本租户数据',
  [DATA_SCOPE_ENUM.DEPARTMENT]: '本部门数据',
  [DATA_SCOPE_ENUM.DEPARTMENT_AND_SUB]: '本部门及以下数据',
  [DATA_SCOPE_ENUM.SELF]: '仅本人数据',
  [DATA_SCOPE_ENUM.CUSTOM]: '自定义数据',
}

const state = ref({
  tenantId: null,
  tenant: null,
  user: null,
  scope: {
    tenant_id: null,
    dept_ids: [],
    user_ids: [],
    data_scope: DATA_SCOPE_ENUM.SELF,
    data_scope_label: '仅本人数据',
    user_id: null,
    dept_id: null,
  },
  loaded: false,
  lastRefreshedAt: null,
})

export function useTenant() {
  const isSuperAdmin = computed(() => {
    return state.value.user?.role_code === 'super_admin' ||
           state.value.user?.role_code === 'system_admin'
  })

  const dataScope = computed(() => state.value.scope.data_scope)
  const dataScopeLabel = computed(() => state.value.scope.data_scope_label)

  const canViewAll = computed(() => dataScope.value === DATA_SCOPE_ENUM.ALL)
  const canViewTenant = computed(() =>
    dataScope.value === DATA_SCOPE_ENUM.ALL ||
    dataScope.value === DATA_SCOPE_ENUM.TENANT
  )
  const canViewDepartment = computed(() =>
    [DATA_SCOPE_ENUM.ALL, DATA_SCOPE_ENUM.TENANT, DATA_SCOPE_ENUM.DEPARTMENT, DATA_SCOPE_ENUM.DEPARTMENT_AND_SUB].includes(dataScope.value)
  )
  const canViewSelfOnly = computed(() => dataScope.value === DATA_SCOPE_ENUM.SELF)

  const visibleDeptIds = computed(() => state.value.scope.dept_ids || [])
  const visibleUserIds = computed(() => state.value.scope.user_ids || [])

  async function initTenantContext() {
    try {
      const response = await fetch('/api/tenant/current', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'X-Tenant-Id': localStorage.getItem('tenant_id') || '',
        },
      })
      const result = await response.json()

      if (result.code === 200) {
        state.value = {
          tenantId: result.data.tenant?.id,
          tenant: result.data.tenant,
          user: result.data.user,
          scope: result.data.scope,
          loaded: true,
          lastRefreshedAt: new Date().toISOString(),
        }
      }
    } catch (error) {
      console.error('Failed to init tenant context:', error)
    }
  }

  async function refreshContext() {
    try {
      const response = await fetch('/api/tenant/refresh-context', {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'X-Tenant-Id': localStorage.getItem('tenant_id') || '',
          'Content-Type': 'application/json',
        },
      })
      const result = await response.json()

      if (result.code === 200) {
        state.value = {
          tenantId: result.data.tenant?.id,
          tenant: result.data.tenant,
          user: result.data.user,
          scope: result.data.scope,
          loaded: true,
          lastRefreshedAt: result.data.refreshed_at,
        }
      }

      return result
    } catch (error) {
      console.error('Failed to refresh tenant context:', error)
      throw error
    }
  }

  function isDataVisible(item, options = {}) {
    const {
      userIdField = 'created_by',
      deptIdField = 'dept_id',
      tenantIdField = 'tenant_id',
    } = options

    if (isSuperAdmin.value) return true

    const scope = state.value.scope

    if (scope.data_scope === DATA_SCOPE_ENUM.ALL) return true

    if (scope.data_scope === DATA_SCOPE_ENUM.TENANT) {
      return item[tenantIdField] === scope.tenant_id
    }

    if (scope.data_scope === DATA_SCOPE_ENUM.DEPARTMENT) {
      return item[deptIdField] === scope.dept_id
    }

    if (scope.data_scope === DATA_SCOPE_ENUM.DEPARTMENT_AND_SUB) {
      return scope.dept_ids.includes(item[deptIdField])
    }

    if (scope.data_scope === DATA_SCOPE_ENUM.CUSTOM) {
      return scope.dept_ids.includes(item[deptIdField]) ||
             item[userIdField] === scope.user_id
    }

    if (scope.data_scope === DATA_SCOPE_ENUM.SELF) {
      return item[userIdField] === scope.user_id
    }

    return false
  }

  function filterByDataScope(list, options = {}) {
    if (!Array.isArray(list)) return []
    if (isSuperAdmin.value) return list
    return list.filter(item => isDataVisible(item, options))
  }

  function buildQueryParams(baseParams = {}) {
    const scope = state.value.scope
    return {
      ...baseParams,
      _scope: {
        data_scope: scope.data_scope,
        tenant_id: scope.tenant_id,
        dept_ids: scope.dept_ids,
        user_ids: scope.user_ids,
      },
    }
  }

  function reset() {
    state.value = {
      tenantId: null,
      tenant: null,
      user: null,
      scope: {
        tenant_id: null,
        dept_ids: [],
        user_ids: [],
        data_scope: DATA_SCOPE_ENUM.SELF,
        data_scope_label: '仅本人数据',
        user_id: null,
        dept_id: null,
      },
      loaded: false,
      lastRefreshedAt: null,
    }
  }

  return {
    state: readonly(state),
    isSuperAdmin,
    dataScope,
    dataScopeLabel,
    canViewAll,
    canViewTenant,
    canViewDepartment,
    canViewSelfOnly,
    visibleDeptIds,
    visibleUserIds,
    initTenantContext,
    refreshContext,
    isDataVisible,
    filterByDataScope,
    buildQueryParams,
    reset,
    DATA_SCOPE_ENUM,
    DATA_SCOPE_LABELS,
  }
}
