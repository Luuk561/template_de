<?php

namespace App\Console\Commands\System;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorSystemHealth extends Command
{
    protected $signature = 'system:monitor {--alert-memory=80 : Memory usage percentage to trigger alert}';

    protected $description = 'Monitor system health and log warnings for high resource usage';

    public function handle()
    {
        $alertThreshold = (int) $this->option('alert-memory');

        Log::info('🔍 System health check gestart');

        // Check memory usage
        $memoryInfo = $this->getMemoryInfo();
        if ($memoryInfo) {
            $memoryUsage = $memoryInfo['usage_percentage'];
            $swapUsage = $memoryInfo['swap_percentage'];

            Log::info("💾 Geheugen: {$memoryUsage}%, Swap: {$swapUsage}%");

            if ($memoryUsage > $alertThreshold) {
                Log::warning("⚠️ Hoog geheugengebruik: {$memoryUsage}% (threshold: {$alertThreshold}%)");
            }

            if ($swapUsage > 50) {
                Log::warning("⚠️ Hoog swap gebruik: {$swapUsage}% - server mogelijk overbelast");
            }
        }

        // Check for long-running PHP processes
        $this->checkLongRunningProcesses();

        // Check Laravel log file size
        $this->checkLogFileSize();

        Log::info('✅ System health check voltooid');
    }

    private function getMemoryInfo(): ?array
    {
        try {
            // Read /proc/meminfo (Linux only)
            if (!file_exists('/proc/meminfo')) {
                return null;
            }

            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
            preg_match('/SwapTotal:\s+(\d+)/', $meminfo, $swapTotal);
            preg_match('/SwapFree:\s+(\d+)/', $meminfo, $swapFree);

            if (empty($total[1]) || empty($available[1])) {
                return null;
            }

            $totalMem = (int) $total[1];
            $availableMem = (int) $available[1];
            $usedMem = $totalMem - $availableMem;
            $memoryUsage = round(($usedMem / $totalMem) * 100, 1);

            $swapUsage = 0;
            if (!empty($swapTotal[1]) && $swapTotal[1] > 0) {
                $swapUsed = (int) $swapTotal[1] - (int) ($swapFree[1] ?? 0);
                $swapUsage = round(($swapUsed / (int) $swapTotal[1]) * 100, 1);
            }

            return [
                'usage_percentage' => $memoryUsage,
                'swap_percentage' => $swapUsage,
                'total_mb' => round($totalMem / 1024, 0),
                'used_mb' => round($usedMem / 1024, 0)
            ];

        } catch (\Exception $e) {
            Log::error("❌ Kon geheugeninformatie niet ophalen: " . $e->getMessage());
            return null;
        }
    }

    private function checkLongRunningProcesses(): void
    {
        try {
            // Check for PHP processes running longer than 10 minutes
            $output = shell_exec("ps -eo pid,etime,cmd | grep php | grep -v grep");

            if (empty($output)) {
                return;
            }

            $lines = explode("\n", trim($output));
            $longRunning = [];

            foreach ($lines as $line) {
                if (preg_match('/^\s*(\d+)\s+(\S+)\s+(.+)$/', $line, $matches)) {
                    $pid = $matches[1];
                    $elapsed = $matches[2];
                    $command = $matches[3];

                    // Convert elapsed time to minutes (basic parsing)
                    if (str_contains($elapsed, ':') && !str_contains($elapsed, '-')) {
                        // Format: MM:SS or HH:MM:SS
                        $parts = explode(':', $elapsed);
                        $minutes = count($parts) === 3 ? (int)$parts[1] : (int)$parts[0];

                        if ($minutes > 10 && str_contains($command, 'artisan')) {
                            $longRunning[] = "PID {$pid}: {$elapsed} - {$command}";
                        }
                    }
                }
            }

            if (!empty($longRunning)) {
                Log::warning("⚠️ Lang-lopende PHP processen gevonden:\n" . implode("\n", $longRunning));
            }

        } catch (\Exception $e) {
            // Silent fail - process monitoring is optional
        }
    }

    private function checkLogFileSize(): void
    {
        try {
            $logFile = storage_path('logs/laravel.log');

            if (file_exists($logFile)) {
                $sizeBytes = filesize($logFile);
                $sizeMB = round($sizeBytes / (1024 * 1024), 1);

                if ($sizeMB > 100) {
                    Log::warning("⚠️ Laravel log file is groot: {$sizeMB}MB - overweeg rotatie");
                }
            }

        } catch (\Exception $e) {
            // Silent fail
        }
    }
}