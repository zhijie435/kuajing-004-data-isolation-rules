import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { useTenant } from '../composables/useTenant'
import { courseService } from '../api'

export function useTenantList(fetchFn, options = {}) {
  const {
    autoRefresh = true,
    refreshAfterMutation = true,
    pageSize = 15,
    scopeParams = {},
  } = options

  const {
    state: tenantState,
    buildQueryParams,
    refreshContext,
    DATA_SCOPE_ENUM,
  } = useTenant()

  const list = ref([])
  const loading = ref(false)
  const pagination = ref({
    current_page: 1,
    per_page: pageSize,
    total: 0,
    last_page: 1,
  })
  const scopeInfo = ref(null)

  async function fetchList(params = {}) {
    loading.value = true
    try {
      const query = buildQueryParams({
        page: pagination.value.current_page,
        per_page: pagination.value.per_page,
        ...scopeParams,
        ...params,
      })

      const response = await fetchFn(query)
      const result = response.data

      if (result.code === 200) {
        const data = result.data

        if (data.data && Array.isArray(data.data)) {
          list.value = data.data
          pagination.value = {
            current_page: data.current_page,
            per_page: data.per_page,
            total: data.total,
            last_page: data.last_page,
          }
        } else if (Array.isArray(data)) {
          list.value = data
        }

        if (result.scope) {
          scopeInfo.value = result.scope
        }
      }

      return result
    } catch (error) {
      console.error('Failed to fetch list:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  async function mutateAndRefresh(mutationFn) {
    const result = await mutationFn()

    if (refreshAfterMutation) {
      await refreshContext()
      await fetchList()
    }

    return result
  }

  function changePage(page) {
    pagination.value.current_page = page
    return fetchList()
  }

  function resetAndFetch() {
    pagination.value.current_page = 1
    return fetchList()
  }

  return {
    list,
    loading,
    pagination,
    scopeInfo,
    fetchList,
    mutateAndRefresh,
    changePage,
    resetAndFetch,
  }
}

export function useTenantDetail(fetchFn, options = {}) {
  const {
    refreshAfterMutation = true,
  } = options

  const { refreshContext } = useTenant()

  const detail = ref(null)
  const loading = ref(false)

  async function fetchDetail(id) {
    loading.value = true
    try {
      const response = await fetchFn(id)
      const result = response.data

      if (result.code === 200) {
        detail.value = result.data
      }

      return result
    } catch (error) {
      console.error('Failed to fetch detail:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  async function mutateAndRefresh(mutationFn) {
    const result = await mutationFn()

    if (refreshAfterMutation) {
      await refreshContext()
      if (detail.value?.id) {
        await fetchDetail(detail.value.id)
      }
    }

    return result
  }

  return {
    detail,
    loading,
    fetchDetail,
    mutateAndRefresh,
  }
}
