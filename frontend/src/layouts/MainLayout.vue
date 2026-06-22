<template>
  <el-container class="main-layout">
    <el-aside width="220px" class="aside">
      <div class="logo">
        <el-icon :size="28"><Reading /></el-icon>
        <span class="logo-text">教务管理系统</span>
      </div>
      <el-menu
        :default-active="route.path"
        router
        class="menu"
        background-color="transparent"
        text-color="#c0c4cc"
        active-text-color="#409eff"
      >
        <el-menu-item index="/dashboard">
          <el-icon><DataBoard /></el-icon>
          <span>数据看板</span>
        </el-menu-item>
        <el-menu-item index="/courses">
          <el-icon><Collection /></el-icon>
          <span>课程管理</span>
        </el-menu-item>
        <el-menu-item index="/scope-demo">
          <el-icon><Filter /></el-icon>
          <span>数据范围演示</span>
        </el-menu-item>
      </el-menu>
    </el-aside>

    <el-container>
      <el-header class="header">
        <div class="header-left">
          <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>{{ route.meta.title }}</el-breadcrumb-item>
          </el-breadcrumb>
        </div>
        <div class="header-right">
          <TenantSelector @change="onTenantChange" />
          <DataScopeSelector @change="onScopeChange" />
          <el-dropdown>
            <div class="user-info">
              <el-avatar :size="32" class="avatar">
                {{ userStore.userInfo?.real_name?.charAt(0) || 'U' }}
              </el-avatar>
              <div class="user-text">
                <div class="user-name">{{ userStore.userInfo?.real_name }}</div>
                <div class="user-role">
                  <el-tag size="small" type="primary" effect="plain">
                    {{ userStore.userInfo?.role_label }}
                  </el-tag>
                </div>
              </div>
              <el-icon><ArrowDown /></el-icon>
            </div>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item>
                  <el-icon><User /></el-icon>
                  <span>个人中心</span>
                </el-dropdown-item>
                <el-dropdown-item divided>
                  <el-icon><SwitchButton /></el-icon>
                  <span @click="handleLogout">退出登录</span>
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <el-main class="main">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </el-main>
    </el-container>
  </el-container>
</template>

<script setup lang="ts">
import { onMounted, provide, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { useTenantStore } from '@/stores/tenant'
import { useDataScopeStore } from '@/stores/dataScope'
import TenantSelector from '@/components/TenantSelector.vue'
import DataScopeSelector from '@/components/DataScopeSelector.vue'
import {
  Reading,
  DataBoard,
  Collection,
  Filter,
  ArrowDown,
  User,
  SwitchButton
} from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const tenantStore = useTenantStore()
const scopeStore = useDataScopeStore()

const refreshFlag = ref(0)
provide('refreshTrigger', refreshFlag)

function triggerRefresh() {
  refreshFlag.value++
}
provide('triggerRefresh', triggerRefresh)

function onTenantChange(_tenantId: number | null) {
  triggerRefresh()
}

function onScopeChange(_scope: any) {
  triggerRefresh()
}

async function handleLogout() {
  try {
    await ElMessageBox.confirm('确认退出登录？', '提示', {
      confirmButtonText: '确认',
      cancelButtonText: '取消',
      type: 'warning'
    })
    userStore.logout()
    ElMessage.success('已退出登录')
    router.push('/login')
  } catch {
    // cancelled
  }
}

onMounted(async () => {
  try {
    await scopeStore.fetchScopeSummary()
  } catch (e) {
    // Ignore
  }
})
</script>

<style scoped>
.main-layout {
  min-height: 100vh;
}

.aside {
  background: linear-gradient(180deg, #1f2d3d 0%, #001529 100%);
  color: #fff;
  display: flex;
  flex-direction: column;
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 20px;
  color: #fff;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.logo-text {
  font-size: 16px;
  font-weight: 600;
  color: #fff;
}

.menu {
  flex: 1;
  border-right: none;
  padding: 12px 0;
}

:deep(.el-menu-item) {
  height: 46px;
  line-height: 46px;
  margin: 4px 12px;
  border-radius: 6px;
}

:deep(.el-menu-item.is-active) {
  background: rgba(64, 158, 255, 0.15);
  color: #409eff;
}

.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  background: #fff;
  border-bottom: 1px solid var(--el-border-color-lighter);
  box-shadow: 0 1px 4px rgba(0, 21, 41, 0.06);
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.2s;
}

.user-info:hover {
  background: var(--el-fill-color-light);
}

.avatar {
  background: var(--el-color-primary);
  color: #fff;
}

.user-text {
  line-height: 1.3;
}

.user-name {
  font-size: 13px;
  font-weight: 500;
}

.user-role {
  margin-top: 2px;
}

.main {
  background: var(--el-bg-color-page);
  padding: 20px;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
