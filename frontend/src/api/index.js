import tenantApi from './tenantApi'

export const tenantService = {
  getCurrent() {
    return tenantApi.get('/tenant/current')
  },

  refreshContext() {
    return tenantApi.post('/tenant/refresh-context')
  },

  getDataScope() {
    return tenantApi.get('/tenant/data-scope')
  },

  switchTenant(tenantId) {
    return tenantApi.post('/tenant/switch', { tenant_id: tenantId })
  },

  getTenants(params) {
    return tenantApi.get('/tenant', { params })
  },
}

export const courseService = {
  getList(params) {
    return tenantApi.get('/courses', { params })
  },

  getAll() {
    return tenantApi.get('/courses-all')
  },

  getDetail(id) {
    return tenantApi.get(`/courses/${id}`)
  },

  create(data) {
    return tenantApi.post('/courses', data)
  },

  update(id, data) {
    return tenantApi.put(`/courses/${id}`, data)
  },

  delete(id) {
    return tenantApi.delete(`/courses/${id}`)
  },
}

export const deptService = {
  getList(params) {
    return tenantApi.get('/depts', { params })
  },

  getTree() {
    return tenantApi.get('/depts-tree')
  },

  create(data) {
    return tenantApi.post('/depts', data)
  },

  update(id, data) {
    return tenantApi.put(`/depts/${id}`, data)
  },

  delete(id) {
    return tenantApi.delete(`/depts/${id}`)
  },
}

export const roleService = {
  getList(params) {
    return tenantApi.get('/roles', { params })
  },

  getDetail(id) {
    return tenantApi.get(`/roles/${id}`)
  },

  create(data) {
    return tenantApi.post('/roles', data)
  },

  update(id, data) {
    return tenantApi.put(`/roles/${id}`, data)
  },

  delete(id) {
    return tenantApi.delete(`/roles/${id}`)
  },
}
