import { defineStore } from 'pinia'
import type { ScopeOption, ScopeSummary, DataScopeLevel as DSL } from '@/types'
import { DataScopeLevel, DataScopeLabels } from '@/types'
import request from '@/utils/request'

interface DataScopeState {
  currentScope: DSL
  availableScopes: ScopeOption[]
  scopeSummary: ScopeSummary | null
  loading: boolean
}

export const useDataScopeStore = defineStore('dataScope', {
  state: (): DataScopeState => ({
    currentScope: DataScopeLevel.SELF,
    availableScopes: [],
    scopeSummary: null,
    loading: false
  }),

  getters: {
    currentScopeLabel(state): string {
      return state.scopeSummary?.data_scope.current_label || DataScopeLabels[state.currentScope]
    },
    canViewAllData(state): boolean {
      return state.currentScope === DataScopeLevel.ALL
    },
    canViewTenantData(state): boolean {
      return state.currentScope <= DataScopeLevel.TENANT
    },
    canViewDeptData(state): boolean {
      return state.currentScope <= DataScopeLevel.DEPARTMENT
    }
  },

  actions: {
    async fetchAvailableScopes() {
      this.loading = true
      try {
        const res: any = await request.get('/data-scope/available')
        this.availableScopes = res.data.available || []
        this.currentScope = res.data.current || DataScopeLevel.SELF
        return this.availableScopes
      } finally {
        this.loading = false
      }
    },

    async fetchScopeSummary() {
      this.loading = true
      try {
        const res: any = await request.get('/data-scope/info')
        this.scopeSummary = res.data
        this.currentScope = res.data.data_scope.current
        this.availableScopes = res.data.data_scope.available
        return this.scopeSummary
      } finally {
        this.loading = false
      }
    },

    async switchScope(scope: DSL) {
      this.loading = true
      try {
        const res: any = await request.post('/data-scope/switch', { scope })
        this.currentScope = res.data.current_scope
        return res.data
      } finally {
        this.loading = false
      }
    },

    async checkResourceAccess(resource: Record<string, any>, action: 'view' | 'modify' = 'view') {
      const res: any = await request.post('/data-scope/check-access', {
        resource,
        action
      })
      return res.data
    },

    reset() {
      this.currentScope = DataScopeLevel.SELF
      this.availableScopes = []
      this.scopeSummary = null
    }
  }
})
