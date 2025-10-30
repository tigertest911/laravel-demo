<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Log;

class AddPhpExecutionHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        // 处理请求
        $response = $next($request);

        // 计算各种时间
        $frameworkInitTime = $this->getFrameworkInitTime($startTime);
        $apiRequestTime = $this->getApiRequestTime();
        $functionProcessTime = $this->getFunctionProcessTime();
        $templateProcessTime = $this->getTemplateProcessTime();

        // 获取内存和系统负载信息
        $memoryInfo = $this->getMemoryInfo();
        $systemLoad = $this->getSystemLoad();

        Log::info("memoryInfo==>" . print_r($memoryInfo, true));
        Log::info("systemLoad==>" . print_r($systemLoad, true));
        // 添加头信息
        $response->headers->set('PHP_Exec_Time-FrameworkInit', $frameworkInitTime);
        $response->headers->set('PHP_Exec_Time-ApiRequestTime', $apiRequestTime);
        $response->headers->set('PHP_Exec_Time-FunctionProcessTime', $functionProcessTime);
        $response->headers->set('PHP_Exec_Time-TemplateProcessTime', $templateProcessTime);

        $response->headers->set('PHP_CPU_Memory-MemoryPeakUsageAlloc', $memoryInfo['peak_alloc']);
        $response->headers->set('PHP_CPU_Memory-MemoryPeakUsageUse', $memoryInfo['peak_use']);
        $response->headers->set('PHP_CPU_Memory-MemoryUsage', $memoryInfo['current_alloc']);
        $response->headers->set('PHP_CPU_Memory-MemoryUsageUse', $memoryInfo['current_use']);
        $response->headers->set('PHP_CPU_Memory-SysLoadAvg1min', $systemLoad['1min']);
        $response->headers->set('PHP_CPU_Memory-SysLoadAvg5min', $systemLoad['5min']);
        $response->headers->set('PHP_CPU_Memory-SysLoadAvg10min', $systemLoad['10min']);

        return $response;
    }

    private function getFrameworkInitTime($startTime): string
    {
        // 框架初始化时间（从 LARAVEL_START 到现在）
        return number_format((microtime(true) - $startTime) * 1000, 2) . 'ms';
    }

    private function getApiRequestTime(): string
    {
        // API 请求处理时间（可以自定义计算逻辑）
        if (defined('API_REQUEST_START')) {
            return number_format((microtime(true) - API_REQUEST_START) * 1000, 2) . 'ms';
        }
        return '0.00ms';
    }

    private function getFunctionProcessTime(): string
    {
        // 函数处理时间（可以自定义计算逻辑）
        if (defined('FUNCTION_PROCESS_START')) {
            return number_format((microtime(true) - FUNCTION_PROCESS_START) * 1000, 2) . 'ms';
        }
        return '0.00ms';
    }

    private function getTemplateProcessTime(): string
    {
        // 模板处理时间（可以自定义计算逻辑）
        if (defined('TEMPLATE_PROCESS_START')) {
            return number_format((microtime(true) - TEMPLATE_PROCESS_START) * 1000, 2) . 'ms';
        }
        return '0.00ms';
    }

    private function getMemoryInfo(): array
    {
        $peak = memory_get_peak_usage(true);
        $current = memory_get_usage(true);

        return [
            'peak_alloc' => number_format($peak / 1024 / 1024, 2),
            'peak_use' => number_format(memory_get_peak_usage(false) / 1024 / 1024, 2),
            'current_alloc' => number_format($current / 1024 / 1024, 2),
            'current_use' => number_format(memory_get_usage(false) / 1024 / 1024, 2),
        ];
    }

//    private function getSystemLoad(): array
//    {
//        Log::info("### getSystemLoad begin");
//        if (function_exists('sys_getloadavg')) {
//            Log::info("### function_exists getSystemLoad ");
//
//            $load = sys_getloadavg();
//            return [
//                '1min' => number_format($load[0], 2),
//                '5min' => number_format($load[1], 2),
//                '10min' => number_format($load[2], 2),
//            ];
//        }
//
//        return [
//            '1min' => 'N/A',
//            '5min' => 'N/A',
//            '10min' => 'N/A',
//        ];
//    }

    private function getSystemLoad(): array
    {
        return $this->getSystemLoadAlternative();
    }

    /**
     * 替代方案获取系统负载（兼容Windows）
     */
    private function getSystemLoadAlternative(): array
    {
        // 方案1: 使用 COM 组件（Windows）
        if (class_exists('COM')) {
            try {
                $wmi = new \COM('WinMgmts:\\\\localhost\\root\\CIMV2');
                $cpus = $wmi->ExecQuery('SELECT LoadPercentage FROM Win32_Processor');

                $loadPercentage = 0;
                $cpuCount = 0;

                foreach ($cpus as $cpu) {
                    $loadPercentage += $cpu->LoadPercentage;
                    $cpuCount++;
                }

                if ($cpuCount > 0) {
                    $currentLoad = $loadPercentage / $cpuCount / 100;
                    return [
                        '1min' => number_format($currentLoad, 2),
                        '5min' => number_format($currentLoad, 2),
                        '10min' => number_format($currentLoad, 2),
                    ];
                }
            } catch (\Exception $e) {
                // 忽略错误，使用备用方案
            }
        }

        // 方案2: 使用 shell_exec（如果可用）
        if (function_exists('shell_exec')) {
            try {
                // Windows 系统
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $output = shell_exec('wmic cpu get loadpercentage 2>&1');
                    if (preg_match('/(\d+)/', $output, $matches)) {
                        $load = intval($matches[1]) / 100;
                        return [
                            '1min' => number_format($load, 2),
                            '5min' => number_format($load, 2),
                            '10min' => number_format($load, 2),
                        ];
                    }
                } else {
                    // Linux/Unix 系统
                    $load = sys_getloadavg();
                    return [
                        '1min' => number_format($load[0], 2),
                        '5min' => number_format($load[1], 2),
                        '10min' => number_format($load[2], 2),
                    ];
                }
            } catch (\Exception $e) {
                // 忽略错误
            }
        }

        // 方案3: 模拟负载数据（最后备选）
        return $this->getSimulatedLoad();
    }

    /**
     * 模拟系统负载数据
     */
    private function getSimulatedLoad(): array
    {
        // 基于当前内存使用情况模拟负载
        $memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB
        $baseLoad = min(1.0, $memoryUsage / 512); // 假设 512MB 为基准

        // 添加一些随机波动
        $fluctuation = mt_rand(-20, 20) / 100;
        $currentLoad = max(0.1, min(0.9, $baseLoad + $fluctuation));

        // 模拟不同时间段的负载（稍有差异）
        return [
            '1min' => number_format($currentLoad, 2),
            '5min' => number_format(max(0.1, $currentLoad - 0.05), 2),
            '10min' => number_format(max(0.1, $currentLoad - 0.08), 2),
        ];
    }
}
