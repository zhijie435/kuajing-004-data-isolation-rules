<template>
  <div class="courses-page" :key="refreshKey">
    <el-card shadow="never" class="filter-card">
      <el-form :inline="true" :model="filter" class="filter-form" @submit.prevent>
        <el-form-item label="课程名称">
          <el-input
            v-model="filter.keyword"
            placeholder="搜索课程名称"
            clearable
            style="width: 200px"
            @keyup.enter="loadCourses"
          />
        </el-form-item>
        <el-form-item label="分类">
          <el-select v-model="filter.category" placeholder="全部分类" clearable style="width: 140px">
            <el-option label="编程" value="编程" />
            <el-option label="语文" value="语文" />
            <el-option label="数学" value="数学" />
            <el-option label="英语" value="英语" />
            <el-option label="市场" value="市场" />
            <el-option label="通用" value="通用" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filter.status" placeholder="全部状态" clearable style="width: 120px">
            <el-option label="已发布" value="published" />
            <el-option label="草稿" value="draft" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadCourses">
            <el-icon><Search /></el-icon> 搜索
          </el-button>
          <el-button @click="resetFilter">
            <el-icon><Refresh /></el-icon> 重置
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card shadow="never" style="margin-top: 16px">
      <template #header>
        <div class="table-header">
          <div class="table-title">
            <el-icon><Collection /></el-icon>
            <span>课程列表</span>
            <el-tag type="primary" effect="plain" size="small" style="margin-left: 12px">
              可见 {{ total }} 条
            </el-tag>
          </div>
          <div class="table-actions">
            <el-button type="success" @click="showDebug">
              <el-icon><Document /></el-icon> 查看过滤SQL
            </el-button>
            <el-button type="primary" @click="openCreateDialog">
              <el-icon><Plus /></el-icon> 新建课程
            </el-button>
          </div>
        </div>
      </template>

      <el-table :data="courses" v-loading="loading" stripe>
        <el-table-column type="index" label="#" width="50" />
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column prop="tenant_id" label="租户ID" width="90">
          <template #default="{ row }">
            <el-tag v-if="row.tenant_id" size="small" type="warning">{{ row.tenant_id }}</el-tag>
            <el-tag v-else size="small" type="info">公共</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="dept_id" label="部门ID" width="80" align="center">
          <template #default="{ row }">
            <span v-if="row.dept_id">{{ row.dept_id }}</span>
            <span v-else class="dash">-</span>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="课程名称" min-width="200">
          <template #default="{ row }">
            <div class="course-title">
              <el-icon><Collection /></el-icon>
              <span>{{ row.title }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="category" label="分类" width="90">
          <template #default="{ row }">
            <el-tag size="small">{{ row.category }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="90">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'published'" type="success" size="small" effect="light">
              <el-icon><CircleCheck /></el-icon> 已发布
            </el-tag>
            <el-tag v-else type="warning" size="small" effect="light">
              <el-icon><EditPen /></el-icon> 草稿
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="owner_id" label="负责人ID" width="90" align="center" />
        <el-table-column prop="student_count" label="学员数" width="90" align="right">
          <template #default="{ row }">
            <span class="num">{{ row.student_count?.toLocaleString() }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="160" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button
              link
              type="primary"
              :disabled="!row._permissions?.can_view"
              @click="viewCourse(row)"
            >
              查看
            </el-button>
            <el-button
              link
              type="warning"
              :disabled="!row._permissions?.can_edit"
              @click="editCourse(row)"
            >
              编辑
            </el-button>
            <el-button
              link
              type="danger"
              :disabled="!row._permissions?.can_delete"
              @click="deleteCourse(row)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="showCreate" title="新建课程" width="520px" :close-on-click-modal="false">
      <el-form :model="courseForm" label-width="90px" ref="formRef" :rules="formRules">
        <el-form-item label="课程名称" prop="title">
          <el-input v-model="courseForm.title" placeholder="请输入课程名称" />
        </el-form-item>
        <el-form-item label="分类" prop="category">
          <el-select v-model="courseForm.category" placeholder="请选择分类" style="width: 100%">
            <el-option label="编程" value="编程" />
            <el-option label="语文" value="语文" />
            <el-option label="数学" value="数学" />
            <el-option label="英语" value="英语" />
            <el-option label="市场" value="市场" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="courseForm.status">
            <el-radio value="draft">草稿</el-radio>
            <el-radio value="published">发布</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreate = false">取消</el-button>
        <el-button type="primary" @click="submitCreate">提交</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="showDetail" :title="'课程详情 - ' + detailCourse?.title" width="600px">
      <el-descriptions :column="2" border v-if="detailCourse">
        <el-descriptions-item label="课程ID">{{ detailCourse.id }}</el-descriptions-item>
        <el-descriptions-item label="租户ID">{{ detailCourse.tenant_id ?? '公共数据' }}</el-descriptions-item>
        <el-descriptions-item label="部门ID">{{ detailCourse.dept_id ?? '-' }}</el-descriptions-item>
        <el-descriptions-item label="团队ID">{{ detailCourse.team_id ?? '-' }}</el-descriptions-item>
        <el-descriptions-item label="课程名称">{{ detailCourse.title }}</el-descriptions-item>
        <el-descriptions-item label="分类">{{ detailCourse.category }}</el-descriptions-item>
        <el-descriptions-item label="状态">{{ detailCourse.status }}</el-descriptions-item>
        <el-descriptions-item label="学员数">{{ detailCourse.student_count?.toLocaleString() }}</el-descriptions-item>
        <el-descriptions-item label="负责人ID">{{ detailCourse.owner_id }}</el-descriptions-item>
        <el-descriptions-item label="创建人ID">{{ detailCourse.created_by }}</el-descriptions-item>
        <el-descriptions-item label="创建时间" :span="2">{{ detailCourse.created_at }}</el-descriptions-item>
      </el-descriptions>
    </el-dialog>

    <el-dialog v-model="showDebugDialog" title="SQL 调试 - 自动应用的租户过滤条件" width="720px">
      <el-alert
        title="查询构建器会自动根据当前用户的租户ID + 数据可见范围，注入对应的 WHERE 条件"
        type="info"
        show-icon
        :closable="false"
        style="margin-bottom: 16px"
      />
      <pre v-if="debugInfo" class="debug-pre">{{ formattedDebug }}</pre>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onMounted, reactive, ref, watch } from 'vue'
import { useDataScopeStore } from '@/stores/dataScope'
import { courseApi } from '@/api'
import type { Course } from '@/types'
import {
  Search,
  Refresh,
  Document,
  Plus,
  Collection,
  CircleCheck,
  EditPen
} from '@element-plus/icons-vue'
import {
  ElMessage,
  ElMessageBox,
  type FormInstance,
  type FormRules
} from 'element-plus'

const dataScopeStore = useDataScopeStore()

const refreshTrigger = inject<number>('refreshTrigger', ref(0))
const refreshKey = computed(() => refreshTrigger.value)

const loading = ref(false)
const courses = ref<Course[]>([])
const total = ref(0)
const debugInfo = ref<any>(null)

const filter = reactive({
  keyword: '',
  category: '',
  status: ''
})

const showCreate = ref(false)
const showDetail = ref(false)
const showDebugDialog = ref(false)
const detailCourse = ref<Course | null>(null)
const formRef = ref<FormInstance>()

const courseForm = reactive({
  title: '',
  category: '',
  status: 'draft'
})

const formRules: FormRules = {
  title: [{ required: true, message: '请输入课程名称', trigger: 'blur' }],
  category: [{ required: true, message: '请选择分类', trigger: 'change' }]
}

const formattedDebug = computed(() => {
  if (!debugInfo.value) return ''
  return JSON.stringify(debugInfo.value, null, 2)
})

function resetFilter() {
  filter.keyword = ''
  filter.category = ''
  filter.status = ''
  loadCourses()
}

async function loadCourses() {
  loading.value = true
  try {
    const params: Record<string, any> = {}
    if (filter.keyword) params.keyword = filter.keyword
    if (filter.category) params.category = filter.category
    if (filter.status) params.status = filter.status
    const res: any = await courseApi.list(params)
    courses.value = res.data.list || []
    total.value = res.data.total || 0
  } catch (e) {
    // ignore
  } finally {
    loading.value = false
  }
}

async function showDebug() {
  try {
    const res: any = await courseApi.debug()
    debugInfo.value = res.data
    showDebugDialog.value = true
  } catch (e) {
    // ignore
  }
}

function openCreateDialog() {
  courseForm.title = ''
  courseForm.category = ''
  courseForm.status = 'draft'
  showCreate.value = true
}

async function submitCreate() {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
    const res: any = await courseApi.create({ ...courseForm })
    ElMessage.success(res.message || '创建成功')
    showCreate.value = false
    loadCourses()
  } catch (e: any) {
    if (e?.message) ElMessage.error(e.message)
  }
}

async function viewCourse(row: Course) {
  try {
    const res: any = await courseApi.getById(row.id)
    detailCourse.value = res.data
    showDetail.value = true
  } catch (e: any) {
    // 错误已在拦截器提示
  }
}

function editCourse(row: Course) {
  ElMessage.info('编辑功能示例 - 实际开发中可弹出编辑表单')
}

async function deleteCourse(row: Course) {
  try {
    await ElMessageBox.confirm(`确认删除课程【${row.title}】？`, '提示', {
      confirmButtonText: '删除',
      cancelButtonText: '取消',
      type: 'warning'
    })
    const res: any = await courseApi.remove(row.id)
    ElMessage.success(res.message || '删除成功')
    loadCourses()
  } catch (e) {
    if (e !== 'cancel' && e?.message) ElMessage.error(e.message)
  }
}

watch(refreshKey, () => {
  loadCourses()
})

onMounted(() => {
  loadCourses()
})
</script>

<style scoped>
.filter-card {
  border-radius: 8px;
}

:deep(.el-form-item) {
  margin-bottom: 0;
}

.table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-weight: 600;
}

.table-title {
  display: flex;
  align-items: center;
  gap: 8px;
}

.table-actions {
  display: flex;
  gap: 8px;
}

.course-title {
  display: flex;
  align-items: center;
  gap: 6px;
}

.num {
  font-family: 'Monaco', 'Menlo', monospace;
  color: #1f2937;
  font-weight: 600;
}

.dash {
  color: #d1d5db;
}

.debug-pre {
  background: #0f172a;
  color: #e2e8f0;
  padding: 16px;
  border-radius: 8px;
  font-size: 12px;
  line-height: 1.6;
  max-height: 480px;
  overflow: auto;
  font-family: 'Monaco', 'Menlo', monospace;
}
</style>
