import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import { vScope, vTenant, vDataFilter } from './directives/scope'

import CourseList from './components/CourseList.vue'

const routes = [
  { path: '/', redirect: '/courses' },
  { path: '/courses', name: 'CourseList', component: CourseList },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to, from, next) => {
  const token = localStorage.getItem('token')
  if (!token && to.path !== '/login') {
    next('/login')
    return
  }
  next()
})

const app = createApp(App)

app.use(router)

app.directive('scope', vScope)
app.directive('tenant', vTenant)
app.directive('data-filter', vDataFilter)

app.mount('#app')
