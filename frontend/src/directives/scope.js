import { useTenant } from '../composables/useTenant'

function checkPermission(el, binding) {
  const { isSuperAdmin, dataScope, state } = useTenant()

  const value = binding.value
  if (!value) return

  if (isSuperAdmin.value) return

  if (typeof value === 'string') {
    if (value === 'super_admin') {
      el.parentNode && el.parentNode.removeChild(el)
    }
    return
  }

  if (typeof value === 'object') {
    const { scope, userIdField, deptIdField } = value

    if (scope !== undefined) {
      const currentScope = dataScope.value

      if (typeof scope === 'number') {
        if (currentScope > scope) {
          el.parentNode && el.parentNode.removeChild(el)
        }
      }

      if (Array.isArray(scope)) {
        if (!scope.includes(currentScope)) {
          el.parentNode && el.parentNode.removeChild(el)
        }
      }
    }

    if (value.ownOnly && userIdField) {
      const itemId = el.__vueDataScopeItemId
      const currentUserId = state.value.scope.user_id
      if (itemId && itemId !== currentUserId) {
        el.parentNode && el.parentNode.removeChild(el)
      }
    }

    if (value.deptIds && deptIdField) {
      const itemDeptId = el.__vueDataScopeItemDeptId
      const allowedDeptIds = state.value.scope.dept_ids
      if (itemDeptId && !allowedDeptIds.includes(itemDeptId)) {
        el.parentNode && el.parentNode.removeChild(el)
      }
    }
  }
}

export const vScope = {
  mounted(el, binding) {
    checkPermission(el, binding)
  },

  updated(el, binding) {
    checkPermission(el, binding)
  },
}

export const vTenant = {
  mounted(el, binding) {
    const { state } = useTenant()
    const requiredTenantId = binding.value

    if (!requiredTenantId) return

    const currentTenantId = state.value.tenantId
    if (currentTenantId !== requiredTenantId) {
      el.style.display = 'none'
      el.setAttribute('data-tenant-hidden', 'true')
    }
  },

  updated(el, binding) {
    const { state } = useTenant()
    const requiredTenantId = binding.value
    const currentTenantId = state.value.tenantId

    if (!requiredTenantId || currentTenantId === requiredTenantId) {
      if (el.getAttribute('data-tenant-hidden') === 'true') {
        el.style.display = ''
        el.removeAttribute('data-tenant-hidden')
      }
    } else {
      el.style.display = 'none'
      el.setAttribute('data-tenant-hidden', 'true')
    }
  },
}

export const vDataFilter = {
  mounted(el, binding) {
    const { filterByDataScope } = useTenant()
    const { value } = binding

    if (!value || !value.data) return

    const filteredData = filterByDataScope(value.data, {
      userIdField: value.userIdField || 'created_by',
      deptIdField: value.deptIdField || 'dept_id',
      tenantIdField: value.tenantIdField || 'tenant_id',
    })

    el.__vueDataFilterOriginal = value.data
    el.__vueDataFilterFiltered = filteredData

    el.dispatchEvent(new CustomEvent('data-filtered', {
      detail: { filtered: filteredData, original: value.data },
      bubbles: true,
    }))
  },

  updated(el, binding) {
    const { filterByDataScope } = useTenant()
    const { value } = binding

    if (!value || !value.data) return

    const filteredData = filterByDataScope(value.data, {
      userIdField: value.userIdField || 'created_by',
      deptIdField: value.deptIdField || 'dept_id',
      tenantIdField: value.tenantIdField || 'tenant_id',
    })

    el.__vueDataFilterFiltered = filteredData

    el.dispatchEvent(new CustomEvent('data-filtered', {
      detail: { filtered: filteredData, original: value.data },
      bubbles: true,
    }))
  },
}
