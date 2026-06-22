<template>
  <div class="login-page">
    <div class="login-card">
      <div class="login-header">
        <el-icon :size="44" color="#409eff"><Reading /></el-icon>
        <h1>在线课程教务系统</h1>
        <p class="subtitle">多租户 · 跨角色数据隔离</p>
      </div>

      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        size="large"
        class="login-form"
        @submit.prevent="handleLogin"
      >
        <el-form-item prop="username">
          <el-input
            v-model="form.username"
            placeholder="用户名"
            :prefix-icon="User"
            autocomplete="username"
          />
        </el-form-item>
        <el-form-item prop="password">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="密码 (123456)"
            :prefix-icon="Lock"
            show-password
            autocomplete="current-password"
            @keyup.enter="handleLogin"
          />
        </el-form-item>
        <el-button
          type="primary"
          class="login-btn"
          :loading="loading"
          @click="handleLogin"
        >
          登 录
        </el-button>
      </el-form>

      <div class="account-tips">
        <div class="tips-title">
          <el-icon><InfoFilled /></el-icon>
          <span>测试账号（密码均为 123456）</span>
        </div>
        <div class="accounts-grid">
          <div
            v-for="acc in testAccounts"
            :key="acc.username"
            class="account-card"
            @click="fillAccount(acc.username)"
          >
            <div class="acc-role" :class="acc.role">
              <el-icon><component :is="roleIcon(acc.role)" /></el-icon>
              <span>{{ acc.label }}</span>
            </div>
            <div class="acc-username">{{ acc.username }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import {
  Reading,
  User,
  Lock,
  InfoFilled,
  Crown,
  OfficeBuilding,
  Management,
  UserFilled,
  Reading as Teacher,
  School
} from '@element-plus/icons-vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'

interface TestAccount {
  username: string
  label: string
  role: string
}

const testAccounts: TestAccount[] = [
  { username: 'super_admin', label: '超级管理员', role: 'super_admin' },
  { username: 'admin_huaxia', label: '租户管理员', role: 'tenant_admin' },
  { username: 'dept_chinese', label: '部门主管', role: 'dept_head' },
  { username: 'team_leader_1', label: '团队负责人', role: 'team_leader' },
  { username: 'teacher_zhang', label: '讲师', role: 'teacher' },
  { username: 'student_xiao', label: '学员', role: 'student' }
]

function roleIcon(role: string) {
  const map: Record<string, any> = {
    super_admin: Crown,
    tenant_admin: OfficeBuilding,
    dept_head: Management,
    team_leader: UserFilled,
    teacher: Teacher,
    student: School
  }
  return map[role] || User
}

const formRef = ref<FormInstance>()
const form = reactive({
  username: 'super_admin',
  password: '123456'
})
const rules: FormRules = {
  username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }]
}
const loading = ref(false)

const userStore = useUserStore()
const router = useRouter()
const route = useRoute()

function fillAccount(username: string) {
  form.username = username
  form.password = '123456'
}

async function handleLogin() {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
    loading.value = true
    await userStore.login(form.username, form.password)
    ElMessage.success('登录成功')
    const redirect = (route.query.redirect as string) || '/dashboard'
    router.replace(redirect)
  } catch (e: any) {
    if (e?.message) {
      ElMessage.error(e.message)
    }
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
  padding: 20px;
  position: relative;
  overflow: hidden;
}

.login-page::before {
  content: '';
  position: absolute;
  width: 500px;
  height: 500px;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 50%;
  top: -150px;
  left: -150px;
}

.login-page::after {
  content: '';
  position: absolute;
  width: 600px;
  height: 600px;
  background: rgba(255, 255, 255, 0.06);
  border-radius: 50%;
  bottom: -200px;
  right: -200px;
}

.login-card {
  width: 100%;
  max-width: 480px;
  background: rgba(255, 255, 255, 0.97);
  backdrop-filter: blur(20px);
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
  padding: 40px;
  position: relative;
  z-index: 1;
}

.login-header {
  text-align: center;
  margin-bottom: 32px;
}

.login-header h1 {
  margin: 12px 0 4px;
  font-size: 24px;
  color: #1f2937;
  font-weight: 700;
}

.subtitle {
  color: #6b7280;
  font-size: 13px;
}

.login-form {
  margin-bottom: 28px;
}

.login-btn {
  width: 100%;
  height: 44px;
  font-size: 15px;
  font-weight: 500;
  letter-spacing: 2px;
}

.account-tips {
  background: #f8fafc;
  border-radius: 10px;
  padding: 16px;
  border: 1px solid var(--el-border-color-lighter);
}

.tips-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--el-text-color-secondary);
  margin-bottom: 12px;
}

.accounts-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.account-card {
  padding: 10px 12px;
  background: #fff;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}

.account-card:hover {
  border-color: var(--el-color-primary);
  background: var(--el-color-primary-light-9);
  transform: translateY(-1px);
}

.acc-role {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 4px;
}

.acc-role.super_admin { color: #dc2626; }
.acc-role.tenant_admin { color: #ea580c; }
.acc-role.dept_head { color: #2563eb; }
.acc-role.team_leader { color: #059669; }
.acc-role.teacher { color: #0891b2; }
.acc-role.student { color: #7c3aed; }

.acc-username {
  font-size: 12px;
  color: var(--el-text-color-secondary);
  font-family: 'Monaco', 'Menlo', monospace;
}
</style>
