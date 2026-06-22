<?php

require_once __DIR__ . '/autoload.php';

use App\Tests\Unit\TenantMiddlewareTest;
use App\Tests\Unit\DataVisibilityServiceTest;

echo "========================================\n";
echo "  单元测试套件 - 租户数据隔离 & 跨角色可见范围\n";
echo "========================================\n\n";

$testSuites = [
    TenantMiddlewareTest::class,
    DataVisibilityServiceTest::class,
];

$totalPassed = 0;
$totalFailed = 0;
$allFailures = [];

foreach ($testSuites as $testClass) {
    $reflection = new ReflectionClass($testClass);
    $className = $reflection->getShortName();

    echo "▶ 运行测试类: {$className}\n";
    echo str_repeat('-', 60) . "\n";

    $testInstance = new $testClass();
    $result = $testInstance->run();

    $passed = $result['passed'];
    $failed = $result['failed'];
    $failures = $result['failures'];

    $totalPassed += $passed;
    $totalFailed += $failed;
    $allFailures = array_merge($allFailures, $failures);

    $status = $failed === 0 ? '✅ PASS' : '❌ FAIL';
    echo "  结果: {$status} (通过: {$passed}, 失败: {$failed})\n\n";

    if (!empty($failures)) {
        echo "  失败详情:\n";
        foreach ($failures as $failure) {
            echo "    {$failure}\n";
        }
        echo "\n";
    }
}

echo str_repeat('=', 60) . "\n";
echo "▶ 测试汇总\n";
echo str_repeat('=', 60) . "\n";
echo "  总测试数: " . ($totalPassed + $totalFailed) . "\n";
echo "  通过: {$totalPassed}\n";
echo "  失败: {$totalFailed}\n";

if ($totalFailed === 0) {
    echo "\n🎉 所有单元测试通过！\n";
    echo "\n📊 测试覆盖范围:\n";
    echo "   ┌─ TenantMiddlewareTest\n";
    echo "   │  ├─ Token 解析与验证\n";
    echo "   │  ├─ 租户 ID 解析与优先级\n";
    echo "   │  ├─ 租户访问权限校验\n";
    echo "   │  ├─ 部门树递归解析\n";
    echo "   │  ├─ 团队成员解析\n";
    echo "   │  ├─ 上下文 Bootstrap\n";
    echo "   │  ├─ 默认数据范围设置\n";
    echo "   │  └─ 错误响应格式\n";
    echo "   │\n";
    echo "   └─ DataVisibilityServiceTest\n";
    echo "      ├─ 数据可见性检查 (所有 scope 级别)\n";
    echo "      ├─ 数据修改权限检查 (所有角色)\n";
    echo "      ├─ 可用范围获取\n";
    echo "      ├─ 范围切换与越权防护\n";
    echo "      ├─ 跨角色过滤\n";
    echo "      ├─ 资源按角色筛选\n";
    echo "      ├─ 审计导出与异常检测\n";
    echo "      ├─ 范围回写修正\n";
    echo "      ├─ 审计修复与重审\n";
    echo "      ├─ 状态闭环验证 (审计→修复→重审)\n";
    echo "      ├─ 权限断言 (assertCanView/Modify)\n";
    echo "      ├─ 范围摘要获取\n";
    echo "      ├─ 角色映射\n";
    echo "      └─ 上下文状态追踪\n";
    echo "\n";
} else {
    echo "\n⚠️  存在测试失败，详情如下:\n";
    foreach ($allFailures as $failure) {
        echo "  {$failure}\n";
    }
    echo "\n";
    exit(1);
}
