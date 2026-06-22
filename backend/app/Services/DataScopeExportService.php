<?php

namespace App\Services;

use App\Enums\DataScopeEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DataScopeExportService
{
    protected $modelClass;
    protected $queryCallback;
    protected $tenantId;
    protected $results = [];
    protected $anomalies = [];
    protected $summary = [];

    public function __construct(string $modelClass, callable $queryCallback = null)
    {
        $this->modelClass = $modelClass;
        $this->queryCallback = $queryCallback;
        $this->tenantId = TenantContext::getTenantId();
    }

    public function runAudit(): array
    {
        if (!TenantContext::isSuperAdmin()) {
            return [
                'code' => 403,
                'message' => '仅超级管理员可执行数据范围核对',
            ];
        }

        $roles = $this->getTenantRoles();
        if (empty($roles)) {
            return [
                'code' => 404,
                'message' => '当前租户无角色数据',
            ];
        }

        $this->results = [];
        $this->anomalies = [];

        $originalUser = TenantContext::getUser();

        foreach ($roles as $role) {
            $this->auditRole($role);
        }

        TenantContext::setUser($originalUser);

        $this->detectCrossRoleAnomalies();
        $this->buildSummary();

        return [
            'code' => 200,
            'message' => 'success',
            'data' => [
                'summary' => $this->summary,
                'results' => $this->results,
                'anomalies' => $this->anomalies,
                'audited_at' => now()->toIso8601String(),
            ],
        ];
    }

    protected function getTenantRoles(): array
    {
        return Role::where('tenant_id', $this->tenantId)
            ->orderBy('data_scope', 'asc')
            ->get()
            ->all();
    }

    protected function auditRole(Role $role): void
    {
        $tempUser = $this->buildTempUserForRole($role);
        TenantContext::setUser($tempUser);

        $query = $this->buildBaseQuery();

        if ($this->queryCallback) {
            call_user_func($this->queryCallback, $query);
        }

        $totalCount = $query->count();
        $sampleRecords = $query->limit(5)->get()->toArray();

        $scopeApplied = \App\Scopes\DataScopeScope::flushAppliedWarnings();
        $hasDowngrade = \App\Scopes\DataScopeScope::hasWarnings();

        $this->results[$role->code] = [
            'role_id' => $role->id,
            'role_code' => $role->code,
            'role_name' => $role->name,
            'declared_scope' => $role->data_scope,
            'declared_scope_label' => DataScopeEnum::label($role->data_scope),
            'visible_count' => $totalCount,
            'sample_records' => $sampleRecords,
            'scope_applied' => $scopeApplied,
            'has_downgrade' => $hasDowngrade,
            'visible_dept_ids' => DataScopeService::getVisibleDeptIds(),
            'visible_user_count' => count(DataScopeService::getVisibleUserIds()),
        ];
    }

    protected function buildTempUserForRole(Role $role): User
    {
        $user = new User();
        $user->id = 0;
        $user->tenant_id = $this->tenantId;
        $user->role_code = $role->code;
        $user->data_scope = $role->data_scope;
        $user->dept_id = $this->getRepresentativeDeptId($role);
        $user->setRelation('role', $role);

        return $user;
    }

    protected function getRepresentativeDeptId(Role $role): ?int
    {
        if (in_array($role->data_scope, [DataScopeEnum::DEPARTMENT, DataScopeEnum::DEPARTMENT_AND_SUB, DataScopeEnum::CUSTOM])) {
            $dept = \App\Models\Dept::where('tenant_id', $this->tenantId)
                ->where('status', true)
                ->first();
            return $dept ? $dept->id : null;
        }
        return null;
    }

    protected function buildBaseQuery(): Builder
    {
        $model = new $this->modelClass();
        $query = $model->newQuery();

        $traits = class_uses($this->modelClass);
        if (in_array(\App\Traits\HasDataScope::class, $traits)) {
            $query = $query->withDataScope();
        }

        return $query;
    }

    protected function detectCrossRoleAnomalies(): void
    {
        $roleList = array_values($this->results);
        $scopePriority = $this->getScopePriority();

        for ($i = 0; $i < count($roleList); $i++) {
            for ($j = $i + 1; $j < count($roleList); $j++) {
                $roleA = $roleList[$i];
                $roleB = $roleList[$j];

                $this->checkScopeConsistency($roleA, $roleB, $scopePriority);
                $this->checkDataCountAnomaly($roleA, $roleB, $scopePriority);
            }
        }

        $this->checkDowngradeAnomalies();
        $this->checkCustomScopeAnomalies();
    }

    protected function getScopePriority(): array
    {
        return [
            DataScopeEnum::ALL => 100,
            DataScopeEnum::TENANT => 90,
            DataScopeEnum::DEPARTMENT_AND_SUB => 70,
            DataScopeEnum::DEPARTMENT => 60,
            DataScopeEnum::CUSTOM => 50,
            DataScopeEnum::SELF => 10,
        ];
    }

    protected function checkScopeConsistency(array $roleA, array $roleB, array $priority): void
    {
        $priorityA = $priority[$roleA['declared_scope']] ?? 0;
        $priorityB = $priority[$roleB['declared_scope']] ?? 0;

        if ($priorityA > $priorityB && $roleA['visible_count'] < $roleB['visible_count']) {
            $this->anomalies[] = [
                'type' => 'scope_data_mismatch',
                'severity' => 'high',
                'message' => sprintf(
                    '权限层级异常：%s（%s）权限高于 %s（%s），但可见数据量更少（%d vs %d）',
                    $roleA['role_name'],
                    $roleA['declared_scope_label'],
                    $roleB['role_name'],
                    $roleB['declared_scope_label'],
                    $roleA['visible_count'],
                    $roleB['visible_count']
                ),
                'roles' => [$roleA['role_code'], $roleB['role_code']],
                'details' => [
                    'higher_role' => $roleA,
                    'lower_role' => $roleB,
                ],
            ];
        }
    }

    protected function checkDataCountAnomaly(array $roleA, array $roleB, array $priority): void
    {
        $priorityA = $priority[$roleA['declared_scope']] ?? 0;
        $priorityB = $priority[$roleB['declared_scope']] ?? 0;

        if ($priorityA === $priorityB && abs($roleA['visible_count'] - $roleB['visible_count']) > 0) {
            $this->anomalies[] = [
                'type' => 'same_scope_diff_count',
                'severity' => 'medium',
                'message' => sprintf(
                    '同范围不同角色数据量不一致：%s 和 %s 同为 %s，但数据量不同（%d vs %d）',
                    $roleA['role_name'],
                    $roleB['role_name'],
                    $roleA['declared_scope_label'],
                    $roleA['visible_count'],
                    $roleB['visible_count']
                ),
                'roles' => [$roleA['role_code'], $roleB['role_code']],
                'details' => [
                    'role_a' => $roleA,
                    'role_b' => $roleB,
                ],
            ];
        }
    }

    protected function checkDowngradeAnomalies(): void
    {
        foreach ($this->results as $roleCode => $result) {
            if ($result['has_downgrade']) {
                foreach ($result['scope_applied'] as $modelClass => $info) {
                    if (!empty($info['extra']['from']) && $info['extra']['from'] !== $info['extra']['to']) {
                        $this->anomalies[] = [
                            'type' => 'scope_downgrade',
                            'severity' => 'medium',
                            'message' => sprintf(
                                '数据范围降级：角色 %s 声明为 %s，实际降级为 %s（原因：%s）',
                                $result['role_name'],
                                DataScopeEnum::label($info['extra']['from']),
                                DataScopeEnum::label($info['extra']['to']),
                                $info['extra']['reason'] ?? '未知'
                            ),
                            'roles' => [$roleCode],
                            'details' => $info,
                        ];
                    }
                }
            }
        }
    }

    protected function checkCustomScopeAnomalies(): void
    {
        foreach ($this->results as $roleCode => $result) {
            if ($result['declared_scope'] === DataScopeEnum::CUSTOM) {
                if (empty($result['visible_dept_ids'])) {
                    $this->anomalies[] = [
                        'type' => 'custom_scope_empty',
                        'severity' => 'high',
                        'message' => sprintf(
                            '自定义数据范围为空：角色 %s 设置了自定义数据范围，但未关联任何部门',
                            $result['role_name']
                        ),
                        'roles' => [$roleCode],
                        'details' => $result,
                    ];
                }
            }
        }
    }

    protected function buildSummary(): void
    {
        $totalRoles = count($this->results);
        $totalAnomalies = count($this->anomalies);

        $severityCount = [
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        $typeCount = [];

        foreach ($this->anomalies as $anomaly) {
            $severity = $anomaly['severity'] ?? 'low';
            if (isset($severityCount[$severity])) {
                $severityCount[$severity]++;
            }

            $type = $anomaly['type'] ?? 'unknown';
            if (!isset($typeCount[$type])) {
                $typeCount[$type] = 0;
            }
            $typeCount[$type]++;
        }

        $maxCount = 0;
        $minCount = PHP_INT_MAX;
        $maxRole = null;
        $minRole = null;

        foreach ($this->results as $result) {
            if ($result['visible_count'] > $maxCount) {
                $maxCount = $result['visible_count'];
                $maxRole = $result;
            }
            if ($result['visible_count'] < $minCount) {
                $minCount = $result['visible_count'];
                $minRole = $result;
            }
        }

        $this->summary = [
            'audited_role_count' => $totalRoles,
            'anomaly_count' => $totalAnomalies,
            'anomaly_severity_distribution' => $severityCount,
            'anomaly_type_distribution' => $typeCount,
            'max_visible' => [
                'role' => $maxRole ? $maxRole['role_name'] : null,
                'count' => $maxCount,
            ],
            'min_visible' => [
                'role' => $minRole ? $minRole['role_name'] : null,
                'count' => $minCount,
            ],
            'has_issues' => $totalAnomalies > 0,
            'overall_status' => $totalAnomalies === 0 ? 'normal' : ($severityCount['high'] > 0 ? 'critical' : 'warning'),
        ];
    }

    public function exportToCsv(): array
    {
        $auditResult = $this->runAudit();
        if ($auditResult['code'] !== 200) {
            return $auditResult;
        }

        $data = $auditResult['data'];

        $csvContent = $this->generateCsvContent($data);

        return [
            'code' => 200,
            'message' => 'success',
            'data' => [
                'filename' => 'data_scope_audit_' . date('YmdHis') . '.csv',
                'content' => base64_encode($csvContent),
                'summary' => $data['summary'],
                'anomaly_count' => count($data['anomalies']),
            ],
        ];
    }

    protected function generateCsvContent(array $data): string
    {
        $output = fopen('php://temp', 'r+');

        fputcsv($output, ['=== 数据可见范围核对报告 ===']);
        fputcsv($output, ['生成时间', $data['audited_at']]);
        fputcsv($output, ['审核角色数', $data['summary']['audited_role_count']]);
        fputcsv($output, ['异常数量', $data['summary']['anomaly_count']]);
        fputcsv($output, ['整体状态', $data['summary']['overall_status']]);
        fputcsv($output, []);

        fputcsv($output, ['=== 一、各角色数据可见情况 ===']);
        fputcsv($output, ['角色编码', '角色名称', '声明数据范围', '可见数据量', '是否降级', '可见部门数', '可见用户数']);

        foreach ($data['results'] as $result) {
            fputcsv($output, [
                $result['role_code'],
                $result['role_name'],
                $result['declared_scope_label'],
                $result['visible_count'],
                $result['has_downgrade'] ? '是' : '否',
                count($result['visible_dept_ids']),
                $result['visible_user_count'],
            ]);
        }
        fputcsv($output, []);

        fputcsv($output, ['=== 二、异常清单 ===']);
        if (empty($data['anomalies'])) {
            fputcsv($output, ['无异常']);
        } else {
            fputcsv($output, ['序号', '严重程度', '异常类型', '异常描述', '涉及角色']);
            foreach ($data['anomalies'] as $index => $anomaly) {
                fputcsv($output, [
                    $index + 1,
                    $this->getSeverityLabel($anomaly['severity']),
                    $anomaly['type'],
                    $anomaly['message'],
                    implode(', ', $anomaly['roles']),
                ]);
            }
        }
        fputcsv($output, []);

        fputcsv($output, ['=== 三、异常严重程度分布 ===']);
        foreach ($data['summary']['anomaly_severity_distribution'] as $severity => $count) {
            fputcsv($output, [$this->getSeverityLabel($severity), $count]);
        }
        fputcsv($output, []);

        fputcsv($output, ['=== 四、异常类型分布 ===']);
        foreach ($data['summary']['anomaly_type_distribution'] as $type => $count) {
            fputcsv($output, [$this->getTypeLabel($type), $count]);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    protected function getSeverityLabel(string $severity): string
    {
        $labels = [
            'high' => '高',
            'medium' => '中',
            'low' => '低',
        ];
        return $labels[$severity] ?? $severity;
    }

    protected function getTypeLabel(string $type): string
    {
        $labels = [
            'scope_data_mismatch' => '权限层级异常',
            'same_scope_diff_count' => '同范围数据量不一致',
            'scope_downgrade' => '数据范围降级',
            'custom_scope_empty' => '自定义范围为空',
        ];
        return $labels[$type] ?? $type;
    }
}
