<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return view('posts.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // 使用示例
        $result = $this->increaseServerLoad(1, 1, 2, true, false);

        return $result;
    }

    /**
     * 增加服务器负荷的方法
     *
     * @param int $cpuIntensity CPU负载强度 1-10
     * @param int $memoryIntensity 内存负载强度 1-10
     * @param int $duration 负载持续时间(秒)
     * @param bool $enableDiskIO 是否启用磁盘IO负载
     * @param bool $enableNetworkIO 是否启用网络IO负载
     * @return array 负载执行结果统计
     */
    function increaseServerLoad(
        $cpuIntensity = 5,
        $memoryIntensity = 5,
        $duration = 10,
        $enableDiskIO = false,
        $enableNetworkIO = false
    ) {
        $startTime = microtime(true);
        $endTime = $startTime + $duration;
        $operations = 0;
        $memoryBlocks = [];

        // CPU 负载 - 通过数学计算消耗CPU
        $cpuOperations = $cpuIntensity * 100000;

        // 内存负载 - 分配内存块
        $memorySize = $memoryIntensity * 1024 * 1024; // MB

        // 磁盘IO负载文件
        $ioFile = null;
        if ($enableDiskIO) {
            $ioFile = tempnam(sys_get_temp_dir(), 'load_test_');
        }

        Log::info("开始增加服务器负载...");
        Log::info("CPU强度: {$cpuIntensity}, 内存强度: {$memoryIntensity}, 持续时间: {$duration}秒");

        while (microtime(true) < $endTime) {
            $operations++;

            // CPU密集型操作 - 素数计算
            for ($i = 0; $i < $cpuOperations; $i++) {
                $this->calculatePrimes(1000);
            }

            // 内存密集型操作
            if ($memoryIntensity > 0) {
                // 分配内存块
                $memoryBlocks[] = str_repeat('X', $memorySize);

                // 定期清理部分内存块防止OOM
                if (count($memoryBlocks) > 10) {
                    array_shift($memoryBlocks);
                }
            }

            // 磁盘IO操作
            if ($enableDiskIO && $ioFile) {
                file_put_contents($ioFile, str_repeat('TEST_DATA_', 1000), FILE_APPEND);
                file_get_contents($ioFile);
            }

            // 网络IO操作（模拟）
            if ($enableNetworkIO) {
                // 模拟DNS查询
                gethostbyname('www.example.com');

                // 创建TCP连接（到本地）
                $socket = @fsockopen('127.0.0.1', 80, $errno, $errstr, 0.1);
                if ($socket) {
                    fclose($socket);
                }
            }

            // 短暂休眠控制负载节奏
            usleep(100000); // 100ms
        }

        // 清理资源
        if ($ioFile && file_exists($ioFile)) {
            unlink($ioFile);
        }

        $executionTime = microtime(true) - $startTime;

        $result = [
            'total_operations' => $operations,
            'execution_time' => round($executionTime, 2),
            'operations_per_second' => round($operations / $executionTime, 2),
            'memory_peak_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'cpu_intensity' => $cpuIntensity,
            'memory_intensity' => $memoryIntensity,
            'load_duration' => $duration
        ];

        Log::info("负载测试完成!");
        Log::info("总操作数: {$result['total_operations']}");
        Log::info("执行时间: {$result['execution_time']}秒");
        Log::info("每秒操作数: {$result['operations_per_second']}");
        Log::info("内存峰值: {$result['memory_peak_usage']}");

        return $result;
    }

    /**
     * 计算素数 - CPU密集型辅助函数
     */
    private function calculatePrimes($limit) {
        $primes = [];
        for ($i = 2; $i <= $limit; $i++) {
            $isPrime = true;
            for ($j = 2; $j * $j <= $i; $j++) {
                if ($i % $j == 0) {
                    $isPrime = false;
                    break;
                }
            }
            if ($isPrime) {
                $primes[] = $i;
            }
        }
        return $primes;
    }

}
