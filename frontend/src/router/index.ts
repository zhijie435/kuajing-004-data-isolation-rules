import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { useTenantStore } from '@/stores/tenant'
import { ElMessage from 'element-plus'

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { requiresAuth: false, title: '登录' }
  },
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    redirect: '/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/Dashboard.vue'),
        meta: { title: '数据看板' }
      },
      {
        path: 'courses',
        name: 'Courses',
        component: () => import('@/views/Courses.vue'),
        meta: { title: '课程管理', allowedRoles: ['super_admin', 'tenant_admin', 'dept_head', 'team_leader', 'teacher'] }
      },
      {
        path: 'scope-demo',
        name: 'ScopeDemo',
        component: () => import('@/views/ScopeDemo.vue'),
        meta: { title: '数据范围演示' }
      }
    ]
  },
  {
    path: '/403',
    name: 'Forbidden',
    component: () => import('@/views/Error403.vue'),
    meta: { requiresAuth: false, title: '无权访问' }
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/views/Error404.vue'),
    meta: { requiresAuth: false }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach(async (to, from, next) => {
  const userStore = useUserStore()
  const tenantStore = useTenantStore()

  document.title = to.meta.title ? `${to.meta.title} - 在线课程教务系统` : '在线课程教务系统'

  if (to.meta.requiresAuth === false) {
    next()
    return
  }

  if (!userStore.isLoggedIn) {
    ElMessage.warning('请先登录')
    next({ path: '/login', query: { redirect: to.fullPath } })
    return
  }

  if (!tenantStore.initialized) {
    try {
      await tenantStore.fetchTenants()
      tenantStore.restoreFromStorage()
    } catch (e) {
      // Ignore
    }
  }

  if (to.meta.allowedRoles && Array.isArray(to.meta.allowedRoles)) {
    const userRole = userStore.role
    if (!userRole || !to.meta.allowedRoles.includes(userRole)) {
      ElMessage.error('无权访问该页面')
      next('/403')
      return
    }
  }

  next()
})

export default router
