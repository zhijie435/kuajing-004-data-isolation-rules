<?php

require_once __DIR__ . '/autoload.php';

use App\Core\Context\TenantContext;
use App\Core\Enum\DataScopeLevel;
use App\Core\Enum\RoleType;
use App\Core\Orm\TenantScope;
use App\Core\Service\DataVisibilityService;
use App\Core\Database\QueryBuilder;
use App\Module\Course\Model\CourseModel;

echo "========================================\n";
echo "  租户数据隔离过滤器 - 验证测试套件\n";
echo "========================================\n\n";

$testCases = [
    [
        'name' => '1. 超级管理员 + 全部数据范围',
        'payload' => [
            'tenant_id' => null,
            'user_id' => 999,
            'username' => 'super_admin',
            'role' => 'super_admin',
            'dept_id' => null,
            'team_id' => null,
        ],
        'expect' => '无额外 WHERE 条件，所有租户数据可见',
    ],
    [
        'name' => '2. 租户管理员(华夏) + 本租户范围',
        'payload' => [
            'tenant_id' => 1,
            'user_id' => 101,
            'username' => 'admin_huaxia',
            'role' => 'tenant_admin',
            'dept_id' => 1,
            'team_id' => null,
        ],
        'expect' => 'WHERE tenant_id = 1，仅华夏教育数据可见',
    ],
    [
        'name' => '3. 部门主管(语文部 dept_id=4) + 部门级',
        'payload' => [
            'tenant_id' => 1,
            'user_id' => 102,
            'username' => 'dept_chinese',
            'role' => 'dept_head',
            'dept_id' => 4,
            'team_id' => 101,
        ],
        'expect' => 'WHERE tenant_id=1 AND dept_id IN (4,6,7)，语文部+小学语文+中学语文',
    ],
    [
        'name' => '4. 团队负责人(team_id=101) + 团队级',
        'payload' => [
            'tenant_id' => 1,
            'user_id' => 201,
            'username' => 'team_leader_1',
            'role' => 'team_leader',
            'dept_id' => 4,
            'team_id' => 101,
        ],
        'expect' => 'WHERE tenant_id=1 AND owner_id IN (101,202,203,204)',
    ],
    [
        'name' => '5. 普通讲师张老师 + 仅本人',
        'payload' => [
            'tenant_id' => 1,
            'user_id' => 202,
            'username' => 'teacher_zhang',
            'role' => 'teacher',
            'dept_id' => 2,
            'team_id' => 101,
        ],
        'expect' => 'WHERE tenant_id=1 AND (owner_id=202 OR created_by=202)',
    ],
    [
        'name' => '6. 学员肖同学 + 仅本人',
        'payload' => [
            'tenant_id' => 3,
            'user_id' => 501,
            'username' => 'student_xiao',
            'role' => 'student',
            'dept_id' => 2,
            'team_id' => null,
        ],
        'expect' => 'WHERE tenant_id=3 AND (owner_id=501 OR created_by=501)',
    ],
    [
        'name' => '7. 超级管理员指定查看租户2 + 租户级',
        'payload' => [
            'tenant_id' => 2,
            'user_id' => 999,
            'username' => 'super_admin',
            'role' => 'super_admin',
            'dept_id' => null,
            'team_id' => null,
            'data_scope' => 2,
        ],
        'expect' => 'WHERE tenant_id=2，仅智慧学习中心数据可见',
    ],
];

$allPassed = true;

foreach ($testCases as $case) {
    echo str_repeat('-', 60) . "\n";
    echo "▶ {$case['name']}\n";
    echo "  预期: {$case['expect']}\n\n";

    TenantContext::getInstance()->reset();
    TenantContext::getInstance()->bootstrap($case['payload']);

    $ctx = TenantContext::getInstance();
    echo "  [上下文] 用户: {$ctx->getUsername()} | 角色: {$ctx->getRole()?->label()} | 租户: " . ($ctx->getTenantId() ?? 'ALL') . "\n";
    echo "  [上下文] 数据范围: {$ctx->getDataScope()->label()}\n";

    $qb = CourseModel::query()->where('status', 'published');
    $debug = $qb->debug();

    echo "  [生成SQL] {$debug['sql']}\n";
    echo "  [参数] " . json_encode($debug['params'], JSON_UNESCAPED_UNICODE) . "\n";
    echo "  [Scope] {$debug['scope']}\n";

    $service = new DataVisibilityService();
    $scopeSummary = $service->getScopeSummary();
    echo "  [可用范围] ";
    foreach ($scopeSummary['data_scope']['available'] as $s) {
        echo "[{$s['value']}]{$s['label']} ";
    }
    echo "\n";

    $courses = CourseModel::findAllByFilter();
    echo "  [可见课程数] {$courses['total']} 条\n";
    echo "  [可见课程]: ";
    foreach ($courses['list'] as $c) {
        echo "T{$c['tenant_id']}-#{$c['id']} ";
    }
    echo "\n";

    echo "  ✅ 测试用例执行完成\n\n";
}

echo str_repeat('=', 60) . "\n";
echo "▶ 数据可见范围断言测试\n";
echo str_repeat('=', 60) . "\n\n";

$testAssertions = [
    [
        'setup_user' => ['user_id' => 202, 'role' => 'teacher', 'tenant_id' => 1, 'username' => '张老师'],
        'resource' => ['tenant_id' => 1, 'owner_id' => 202, 'created_by' => 202, 'id' => 1001, 'title' => 'PHP高级编程实战'],
        'action' => 'view',
        'expected' => true,
        'desc' => '张老师查看自己创建的课程#1001'
    ],
    [
        'setup_user' => ['user_id' => 202, 'role' => 'teacher', 'tenant_id' => 1, 'username' => '张老师'],
        'resource' => ['tenant_id' => 2, 'owner_id' => 401, 'created_by' => 401, 'id' => 1006, 'title' => 'Vue3企业级项目开发'],
        'action' => 'view',
        'expected' => false,
        'desc' => '张老师尝试查看租户2的课程#1006（跨租户越权）'
    ],
    [
        'setup_user' => ['user_id' => 102, 'role' => 'dept_head', 'tenant_id' => 1, 'username' => '李主任', 'dept_id' => 4],
        'resource' => ['tenant_id' => 1, 'dept_id' => 7, 'owner_id' => 204, 'created_by' => 204, 'id' => 1010, 'title' => '高考语文阅读'],
        'action' => 'modify',
        'expected' => true,
        'desc' => '李主任(语文部=4)修改下属中学语文组(dept=7)的课程#1010'
    ],
    [
        'setup_user' => ['user_id' => 301, 'role' => 'teacher', 'tenant_id' => 1, 'username' => '周老师'],
        'resource' => ['tenant_id' => 1, 'dept_id' => 4, 'owner_id' => 202, 'created_by' => 202, 'id' => 1001, 'title' => 'PHP高级编程'],
        'action' => 'modify',
        'expected' => false,
        'desc' => '数学组周老师尝试修改张老师(语文)的课程#1001'
    ],
];

$ctx = TenantContext::getInstance();
$svc = new DataVisibilityService();

$resolveDeptTree = function(?int $deptId): array {
    if ($deptId === null) return [];
    $allDepts = [
        1 => ['id' => 1, 'parent_id' => null],
        2 => ['id' => 2, 'parent_id' => 1],
        3 => ['id' => 3, 'parent_id' => 1],
        4 => ['id' => 4, 'parent_id' => 2],
        5 => ['id' => 5, 'parent_id' => 2],
        6 => ['id' => 6, 'parent_id' => 4],
        7 => ['id' => 7, 'parent_id' => 4],
    ];
    $result = [$deptId];
    $collectChildren = function(int $parentId) use ($allDepts, &$result, &$collectChildren) {
        foreach ($allDepts as $dept) {
            if ($dept['parent_id'] === $parentId) {
                $result[] = $dept['id'];
                $collectChildren($dept['id']);
            }
        }
    };
    $collectChildren($deptId);
    return $result;
};

foreach ($testAssertions as $idx => $test) {
    echo "测试 #" . ($idx + 1) . ": {$test['desc']}\n";

    $deptId = $test['setup_user']['dept_id'] ?? null;
    $deptChildren = $resolveDeptTree($deptId);

    $ctx->reset();
    $ctx->bootstrap([
        'tenant_id' => $test['setup_user']['tenant_id'],
        'user_id' => $test['setup_user']['user_id'],
        'username' => $test['setup_user']['username'],
        'role' => $test['setup_user']['role'],
        'dept_id' => $deptId,
        'dept_child_ids' => $deptChildren,
        'team_id' => $test['setup_user']['team_id'] ?? null,
    ]);

    $result = $test['action'] === 'modify'
        ? $svc->canModifyResource($test['resource'])
        : $svc->canViewResource($test['resource']);

    $status = $result === $test['expected'] ? '✅ PASS' : '❌ FAIL';
    echo "  → 结果: " . ($result ? 'ALLOWED' : 'DENIED') . " (预期: " . ($test['expected'] ? 'ALLOWED' : 'DENIED') . ") → {$status}\n\n";

    if ($result !== $test['expected']) {
        $allPassed = false;
    }
}

echo str_repeat('=', 60) . "\n";
echo "▶ 跨角色层级过滤测试\n";
echo str_repeat('=', 60) . "\n\n";

$roleHierarchyTest = [
    'super_admin' => '超级管理员 → 所有下级角色均可见',
    'tenant_admin' => '租户管理员 → 部门主管及以下可见，超级管理员不可见',
    'dept_head' => '部门主管 → 团队负责人及以下可见',
    'team_leader' => '团队负责人 → 讲师/学员可见',
    'teacher' => '讲师 → 仅学员可见',
    'student' => '学员 → 仅自己可见',
];

foreach ($roleHierarchyTest as $role => $desc) {
    $ctx->reset();
    $ctx->bootstrap([
        'tenant_id' => 1,
        'user_id' => 100,
        'username' => 'test_' . $role,
        'role' => $role,
    ]);

    $visible = $svc->buildCrossRoleFilter();
    echo "{$desc}\n";
    echo "  → 当前角色[{$role}] 可见角色: " . implode(', ', $visible) . "\n\n";
}

echo str_repeat('=', 60) . "\n";
echo $allPassed ? "🎉 全部断言测试通过！\n" : "⚠️ 存在断言失败，请检查实现\n";
echo str_repeat('=', 60) . "\n";

echo "\n📌 架构总结：\n";
echo "   ┌─ TenantMiddleware: 解析令牌 → 提取租户/用户 → 校验X-Tenant-Id合法性\n";
echo "   │    ↓\n";
echo "   ├─ TenantContext(Singleton): 持有当前请求租户上下文 + 部门树 + 团队成员\n";
echo "   │    ↓\n";
echo "   ├─ TenantScope: 根据上下文自动生成 WHERE 条件 (tenant_id + 数据范围)\n";
echo "   │    ↓\n";
echo "   ├─ QueryBuilder: 在toSql/getParams时自动应用TenantScope注入条件\n";
echo "   │    ↓\n";
echo "   └─ DataVisibilityService: 提供粒度更细的资源级权限断言 + 跨角色层级串联\n";
echo "\n";
