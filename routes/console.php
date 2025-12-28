<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Commands - Affiliate Site Content Pipeline
|--------------------------------------------------------------------------
|
| Deze scheduler is geoptimaliseerd voor affiliate sites met:
| - GSC-based content generatie (prioriteit #1)
| - Regelmatige content productie (kwaliteit over kwantiteit)
| - Content audit en cleanup systeem
| - Backup systemen voor betrouwbaarheid
|
| Totale content output: 6 artikelen/week
| - Product blogs: 2x/week (maandag en donderdag)
| - Algemene blogs: 2x/week (dinsdag en vrijdag)
| - Reviews: 2x/week (woensdag en zaterdag)
|
*/

// ═══════════════════════════════════════════════════════════════════════════
// 🏷️  CORE SYSTEMS - Essentieel voor site functionaliteit
// ═══════════════════════════════════════════════════════════════════════════

// ✅ Bol.com prijzen updaten (dagelijks om 00:15) - KRITIEK voor actuele prijzen
Schedule::command('app:update-bol-prices --limit=100')
    ->dailyAt('00:15')
    ->withoutOverlapping(60) // Max 60 minuten overlap voorkomen
    ->runInBackground()
    ->onSuccess(function () {
        Log::info('✅ Bol.com prijzen succesvol geüpdatet');
    })
    ->onFailure(function () {
        Log::error('❌ Fout bij updaten Bol.com prijzen');
    })
    ->emailOutputOnFailure(config('mail.admin_email', 'admin@example.com'));

// ✅ Bol.com nieuwe categorieproducten ophalen (1x per maand, op specifieke dag per site)
$categoryId = env('BOL_CATEGORY_ID');
if ($categoryId) {
    // Automatisch dag bepalen op basis van APP_URL hash (geen handmatige .env config nodig)
    $siteHash = crc32(env('APP_URL', 'default'));
    $siteDay = ($siteHash % 31) + 1; // Dag 1-31 (maximale spreiding)

    Schedule::command("app:fetch-bol-category-products {$categoryId} --limit=100")
        ->dailyAt('00:30')
        ->withoutOverlapping(120) // Max 2 uur overlap
        ->runInBackground()
        ->when(function () use ($siteDay) {
            $currentDay = (int) date('j'); // Dag van de maand (1-31)
            $shouldRun = $currentDay === $siteDay;
            Log::info("Categorie producten check: dag {$currentDay}, site dag: {$siteDay}, uitvoeren: " . ($shouldRun ? 'ja' : 'nee'));
            return $shouldRun;
        })
        ->onSuccess(function () use ($siteDay) {
            Log::info("✅ Categorie producten succesvol opgehaald (max 250) - site dag: {$siteDay}");
        })
        ->onFailure(function () use ($siteDay) {
            Log::error("❌ Fout bij ophalen categorie producten - site dag: {$siteDay}");
        });
}

// ═══════════════════════════════════════════════════════════════════════════
// 🎯 GSC CONTENT PIPELINE - TIJDELIJK UITGESCHAKELD
// ═══════════════════════════════════════════════════════════════════════════
// GSC pipeline is uitgeschakeld om te focussen op normale content productie
// Te heractiveren wanneer GSC data consistency verbeterd is

// 🚀 GSC Content Pipeline (maandag en donderdag) - UITGESCHAKELD
// $siteHash = crc32(env('APP_URL', 'default'));
// $staggerHour = 1 + ($siteHash % 8);
// $staggerMinute = ($siteHash % 60);

// Schedule::command('gsc:content-pipeline --days=30 --min-impressions=10 --content-limit=1')
//     ->weeklyOn(1, sprintf('%02d:%02d', $staggerHour, $staggerMinute))
//     ->environments(['production'])
//     ->withoutOverlapping(90)
//     ->runInBackground();

// Schedule::command('gsc:content-pipeline --days=30 --min-impressions=10 --content-limit=1')
//     ->weeklyOn(4, sprintf('%02d:%02d', $staggerHour, $staggerMinute))
//     ->environments(['production'])
//     ->withoutOverlapping(90)
//     ->runInBackground();

// 📊 GSC Content Performance Monitoring - UITGESCHAKELD
// Schedule::command('content:monitor --days=30 --min-impressions=1')
//     ->dailyAt('02:00')
//     ->environments(['production'])
//     ->withoutOverlapping(30)
//     ->runInBackground();

// 🗑️ Content Audit & Cleanup (UITGESCHAKELD - was te agressief)
// Schedule::command('content:audit --type=blogs --keep-percentage=70 --execute --force')
//     ->weeklyOn(0, '03:00') // Zondag
//     ->environments(['production'])
//     ->withoutOverlapping(60)
//     ->runInBackground()
//     ->onSuccess(function () {
//         Log::info('✅ Blog content audit succesvol uitgevoerd');
//     })
//     ->onFailure(function () {
//         Log::error('❌ Fout bij blog content audit');
//     });

// Schedule::command('content:audit --type=reviews --keep-percentage=70 --execute --force')
//     ->weeklyOn(0, '03:30') // Zondag
//     ->environments(['production'])
//     ->withoutOverlapping(60)
//     ->runInBackground()
//     ->onSuccess(function () {
//         Log::info('✅ Review content audit succesvol uitgevoerd');
//     })
//     ->onFailure(function () {
//         Log::error('❌ Fout bij review content audit');
//     });

// ═══════════════════════════════════════════════════════════════════════════
// 📝 CONTENT GENERATIE - 6 artikelen per week (2+2+2 schema)
// ═══════════════════════════════════════════════════════════════════════════

// Stagger times berekenen (spread tussen 01:00-07:00)
$siteHash = crc32(env('APP_URL', 'default'));
$blogStagger1 = sprintf('%02d:%02d', 1 + ($siteHash % 6), ($siteHash * 2) % 60);
$blogStagger2 = sprintf('%02d:%02d', 1 + (($siteHash + 10) % 6), (($siteHash + 10) * 2) % 60);
$reviewStagger1 = sprintf('%02d:%02d', 2 + ($siteHash % 5), ($siteHash * 3) % 60);
$reviewStagger2 = sprintf('%02d:%02d', 2 + (($siteHash + 15) % 5), (($siteHash + 15) * 3) % 60);
$productBlogStagger1 = sprintf('%02d:%02d', 1 + ($siteHash % 7), ($siteHash * 5) % 60);
$productBlogStagger2 = sprintf('%02d:%02d', 1 + (($siteHash + 20) % 7), (($siteHash + 20) * 5) % 60);

// ✅ Product blogs: 2x per week (maandag en donderdag)
Schedule::command('app:generate-popular-product-blogs', ['count' => 1])
    ->weeklyOn(1, $productBlogStagger1) // Maandag
    ->withoutOverlapping(90)
    ->runInBackground()
    ->onSuccess(function () use ($productBlogStagger1) {
        Log::info("✅ Product blog 1 succesvol gegenereerd (maandag {$productBlogStagger1})");
    })
    ->onFailure(function () use ($productBlogStagger1) {
        Log::error("❌ Fout bij genereren product blog 1 (maandag {$productBlogStagger1})");
    });

Schedule::command('app:generate-popular-product-blogs', ['count' => 1])
    ->weeklyOn(4, $productBlogStagger2) // Donderdag
    ->withoutOverlapping(90)
    ->runInBackground()
    ->onSuccess(function () use ($productBlogStagger2) {
        Log::info("✅ Product blog 2 succesvol gegenereerd (donderdag {$productBlogStagger2})");
    })
    ->onFailure(function () use ($productBlogStagger2) {
        Log::error("❌ Fout bij genereren product blog 2 (donderdag {$productBlogStagger2})");
    });

// ✅ Algemene blogs: 2x per week (dinsdag en vrijdag)
Schedule::command('app:generate-blog')
    ->weeklyOn(2, $blogStagger1) // Dinsdag
    ->withoutOverlapping(90)
    ->runInBackground()
    ->onSuccess(function () use ($blogStagger1) {
        Log::info("✅ Algemene blog 1 succesvol gegenereerd (dinsdag {$blogStagger1})");
    })
    ->onFailure(function () use ($blogStagger1) {
        Log::error("❌ Fout bij genereren algemene blog 1 (dinsdag {$blogStagger1})");
    });

Schedule::command('app:generate-blog')
    ->weeklyOn(5, $blogStagger2) // Vrijdag
    ->withoutOverlapping(90)
    ->runInBackground()
    ->onSuccess(function () use ($blogStagger2) {
        Log::info("✅ Algemene blog 2 succesvol gegenereerd (vrijdag {$blogStagger2})");
    })
    ->onFailure(function () use ($blogStagger2) {
        Log::error("❌ Fout bij genereren algemene blog 2 (vrijdag {$blogStagger2})");
    });

// ✅ Reviews: 2x per week (woensdag en zaterdag)
Schedule::command('generate:review')
    ->weeklyOn(3, $reviewStagger1) // Woensdag
    ->withoutOverlapping(60)
    ->runInBackground()
    ->onSuccess(function () use ($reviewStagger1) {
        Log::info("✅ Review 1 succesvol gegenereerd (woensdag {$reviewStagger1})");
    })
    ->onFailure(function () use ($reviewStagger1) {
        Log::error("❌ Fout bij genereren review 1 (woensdag {$reviewStagger1})");
    });

Schedule::command('generate:review')
    ->weeklyOn(6, $reviewStagger2) // Zaterdag
    ->withoutOverlapping(60)
    ->runInBackground()
    ->onSuccess(function () use ($reviewStagger2) {
        Log::info("✅ Review 2 succesvol gegenereerd (zaterdag {$reviewStagger2})");
    })
    ->onFailure(function () use ($reviewStagger2) {
        Log::error("❌ Fout bij genereren review 2 (zaterdag {$reviewStagger2})");
    });

// ═══════════════════════════════════════════════════════════════════════════
// 🔧 MONITORING & MAINTENANCE - System health en backup systemen
// ═══════════════════════════════════════════════════════════════════════════

// 💓 Scheduler heartbeat (elke 5 minuten een ping)
Schedule::call(function () {
    Log::info('💓 Scheduler heartbeat - ' . now()->format('Y-m-d H:i:s'));
})->everyFiveMinutes()->name('scheduler-heartbeat');

// 🔍 System health monitoring (elk uur)
Schedule::command('system:monitor --alert-memory=75')
    ->hourly()
    ->withoutOverlapping(5)
    ->runInBackground()
    ->onFailure(function () {
        Log::error('❌ System health monitoring gefaald');
    })
    ->name('system-health-monitor');

// 🔧 Dagelijkse scheduler status check (07:00)
Schedule::call(function () {
    $logFile = storage_path('logs/laravel.log');
    $today = now()->format('Y-m-d');

    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $dayOfWeek = now()->dayOfWeek; // 1=maandag, 2=dinsdag, etc.

        $successfulTasks = [
            'prijzen' => str_contains($logContent, "[$today]") && str_contains($logContent, '✅ Bol.com prijzen succesvol'),
        ];

        // Check taken op basis van dag van de week (nieuw 2+2+2 schema)
        if ($dayOfWeek === 1) { // Maandag
            $successfulTasks['product_blog_1'] = str_contains($logContent, "[$today]") && str_contains($logContent, '✅ Product blog 1 succesvol gegenereerd (maandag)');
        }
        if ($dayOfWeek === 2) { // Dinsdag
            $successfulTasks['algemene_blog_1'] = str_contains($logContent, "[$today]") && str_contains($logContent, '✅ Algemene blog 1 succesvol gegenereerd (dinsdag)');
        }
        if ($dayOfWeek === 3) { // Woensdag
            $successfulTasks['review_1'] = str_contains($logContent, "[$today]") && str_contains($logContent, '✅ Review 1 succesvol gegenereerd (woensdag)');
        }
        if ($dayOfWeek === 4) { // Donderdag
            $successfulTasks['product_blog_2'] = str_contains($logContent, "[$today]") && str_contains($logContent, '✅ Product blog 2 succesvol gegenereerd (donderdag)');
        }
        if ($dayOfWeek === 5) { // Vrijdag
            $successfulTasks['algemene_blog_2'] = str_contains($logContent, "[$today]") && str_contains($logContent, '✅ Algemene blog 2 succesvol gegenereerd (vrijdag)');
        }
        if ($dayOfWeek === 6) { // Zaterdag
            $successfulTasks['review_2'] = str_contains($logContent, "[$today]") && str_contains($logContent, '✅ Review 2 succesvol gegenereerd (zaterdag)');
        }

        $failedTasks = array_filter($successfulTasks, fn($success) => !$success);

        if (empty($failedTasks)) {
            Log::info('✅ Alle scheduled taken succesvol uitgevoerd vandaag');
        } else {
            Log::warning('⚠️  Sommige scheduled taken zijn gefaald: ' . implode(', ', array_keys($failedTasks)));
        }
    }
})->dailyAt('07:00')->name('daily-status-check');

// ═══════════════════════════════════════════════════════════════════════════
// 🚨 VEILIGE BACKUP SYSTEMEN - Gescheiden, nachtelijke uitvoering
// ═══════════════════════════════════════════════════════════════════════════

// 🔄 Backup: Prijzen update (alleen 's nachts om 03:15)
Schedule::command('app:update-bol-prices --limit=50')
    ->dailyAt('03:15')
    ->withoutOverlapping(45)
    ->runInBackground()
    ->when(function () {
        // Alleen uitvoeren als de hoofdtaak gefaald is
        $logFile = storage_path('logs/laravel.log');
        $today = now()->format('Y-m-d');

        if (!file_exists($logFile)) {
            return true; // Uitvoeren als er geen log is
        }

        $logContent = file_get_contents($logFile);
        $pricesSuccessful = str_contains($logContent, "[$today]") && str_contains($logContent, '✅ Bol.com prijzen succesvol');

        return !$pricesSuccessful; // Alleen uitvoeren als hoofdtaak niet succesvol was
    })
    ->onSuccess(function () {
        Log::info('✅ Backup: Prijzen update succesvol uitgevoerd');
    })
    ->onFailure(function () {
        Log::error('❌ Backup: Prijzen update gefaald');
    })
    ->name('backup-prices');

// 🔄 Backup: Algemene blogs (alleen 's nachts om 03:45)
Schedule::command('app:generate-blog')
    ->dailyAt('03:45')
    ->withoutOverlapping(30)
    ->runInBackground()
    ->when(function () {
        // Alleen uitvoeren als de hoofdtaak gefaald is EN het een dinsdag/vrijdag is of was
        $today = now();
        $isBlogDay = in_array($today->dayOfWeek, [2, 3, 5, 6]); // Di, Wo, Vr, Za (dag na blog)

        if (!$isBlogDay) {
            return false;
        }

        $logFile = storage_path('logs/laravel.log');
        $todayStr = $today->format('Y-m-d');

        if (!file_exists($logFile)) {
            return true;
        }

        $logContent = file_get_contents($logFile);
        $blogsSuccessful = str_contains($logContent, "[$todayStr]") &&
            (str_contains($logContent, '✅ Algemene blog 1 succesvol') ||
             str_contains($logContent, '✅ Algemene blog 2 succesvol'));

        return !$blogsSuccessful;
    })
    ->onSuccess(function () {
        Log::info('✅ Backup: Algemene blog succesvol uitgevoerd');
    })
    ->onFailure(function () {
        Log::error('❌ Backup: Algemene blog gefaald');
    })
    ->name('backup-blogs');

// 🔄 Backup: Review generatie (alleen 's nachts om 04:15)
Schedule::command('generate:review')
    ->dailyAt('04:15')
    ->withoutOverlapping(30)
    ->runInBackground()
    ->when(function () {
        // Alleen uitvoeren als de hoofdtaak gefaald is EN het woensdag/zaterdag is of was
        $today = now();
        $isReviewDay = in_array($today->dayOfWeek, [3, 4, 6, 0]); // Wo, Do, Za, Zo (dag na review)

        if (!$isReviewDay) {
            return false;
        }

        $logFile = storage_path('logs/laravel.log');
        $todayStr = $today->format('Y-m-d');

        if (!file_exists($logFile)) {
            return true;
        }

        $logContent = file_get_contents($logFile);
        $reviewSuccessful = str_contains($logContent, "[$todayStr]") &&
            (str_contains($logContent, '✅ Review 1 succesvol') ||
             str_contains($logContent, '✅ Review 2 succesvol'));

        return !$reviewSuccessful;
    })
    ->onSuccess(function () {
        Log::info('✅ Backup: Review succesvol uitgevoerd');
    })
    ->onFailure(function () {
        Log::error('❌ Backup: Review gefaald');
    })
    ->name('backup-review');

// 🔄 Backup: Product blogs (alleen 's nachts om 04:45)
Schedule::command('app:generate-popular-product-blogs', ['count' => 1])
    ->dailyAt('04:45')
    ->withoutOverlapping(30)
    ->runInBackground()
    ->when(function () {
        // Alleen uitvoeren als de hoofdtaak gefaald is EN het maandag/donderdag is of was
        $today = now();
        $isProductBlogDay = in_array($today->dayOfWeek, [1, 2, 4, 5]); // Ma, Di, Do, Vr (dag na product blog)

        if (!$isProductBlogDay) {
            return false;
        }

        $logFile = storage_path('logs/laravel.log');
        $todayStr = $today->format('Y-m-d');

        if (!file_exists($logFile)) {
            return true;
        }

        $logContent = file_get_contents($logFile);
        $productBlogSuccessful = str_contains($logContent, "[$todayStr]") &&
            (str_contains($logContent, '✅ Product blog 1 succesvol') ||
             str_contains($logContent, '✅ Product blog 2 succesvol'));

        return !$productBlogSuccessful;
    })
    ->onSuccess(function () {
        Log::info('✅ Backup: Product blog succesvol uitgevoerd');
    })
    ->onFailure(function () {
        Log::error('❌ Backup: Product blog gefaald');
    })
    ->name('backup-product-blog');

// 🔄 Failed blogs recovery (elk uur tussen 06:00-22:00)
Schedule::command('app:recover-failed-blogs')
    ->hourly()
    ->between('06:00', '22:00')
    ->withoutOverlapping(15)
    ->runInBackground()
    ->when(function () {
        // Alleen uitvoeren als er failed blogs zijn
        $failedBlogsPath = storage_path('app/failed_blogs');
        return is_dir($failedBlogsPath) && !empty(glob($failedBlogsPath . '/blog_*.json'));
    })
    ->onSuccess(function () {
        Log::info('✅ Failed blogs recovery succesvol uitgevoerd');
    })
    ->onFailure(function () {
        Log::error('❌ Failed blogs recovery gefaald');
    })
    ->name('failed-blogs-recovery');

// 🔄 Backup status monitoring (dagelijks om 07:30)
Schedule::call(function () {
    $logFile = storage_path('logs/laravel.log');
    $today = now()->format('Y-m-d');

    if (!file_exists($logFile)) {
        Log::warning('⚠️ Backup monitoring: Log file niet gevonden');
        return;
    }

    $logContent = file_get_contents($logFile);
    $executedBackups = [];

    // Check welke backup taken zijn uitgevoerd
    if (str_contains($logContent, "[$today]") && str_contains($logContent, '✅ Backup: Prijzen update succesvol')) {
        $executedBackups[] = 'prijzen';
    }
    if (str_contains($logContent, "[$today]") && str_contains($logContent, '✅ Backup: Algemene blog succesvol')) {
        $executedBackups[] = 'algemene_blog';
    }
    if (str_contains($logContent, "[$today]") && str_contains($logContent, '✅ Backup: Review succesvol')) {
        $executedBackups[] = 'review';
    }
    if (str_contains($logContent, "[$today]") && str_contains($logContent, '✅ Backup: Product blog succesvol')) {
        $executedBackups[] = 'product_blog';
    }

    if (empty($executedBackups)) {
        Log::info('✅ Backup monitoring: Geen backup taken uitgevoerd (hoofd-taken succesvol)');
    } else {
        Log::info('🔄 Backup monitoring: ' . count($executedBackups) . ' backup taken uitgevoerd: ' . implode(', ', $executedBackups));
    }
})->dailyAt('07:30')->name('backup-monitoring');

// ═══════════════════════════════════════════════════════════════════════════
// ⚡ MANUAL COMMANDS - Voor troubleshooting en maintenance
// ═══════════════════════════════════════════════════════════════════════════

// 🚀 Force alle content taken (voor emergency content generation)
Artisan::command('scheduler:force-content', function () {
    $this->info('🚀 Forceren van alle content taken (2+2+2 schema)...');

    $this->info('1/6 - Product blog 1 genereren...');
    Artisan::call('app:generate-popular-product-blogs', ['count' => 1]);

    $this->info('2/6 - Product blog 2 genereren...');
    Artisan::call('app:generate-popular-product-blogs', ['count' => 1]);

    $this->info('3/6 - Algemene blog 1...');
    Artisan::call('app:generate-blog');

    $this->info('4/6 - Algemene blog 2...');
    Artisan::call('app:generate-blog');

    $this->info('5/6 - Review 1 genereren...');
    Artisan::call('generate:review');

    $this->info('6/6 - Review 2 genereren...');
    Artisan::call('generate:review');

    $this->info('✅ Alle 6 content taken geforceerd uitgevoerd!');
})->purpose('Force alle content generation taken (6 artikelen)');

// 🧹 Force content cleanup (voor emergency cleanup)
Artisan::command('scheduler:force-cleanup', function () {
    $this->info('🗑️ Forceren van content cleanup...');
    
    $this->info('1/2 - Blog audit...');
    Artisan::call('content:audit', ['--type' => 'blogs', '--keep-percentage' => 70, '--execute' => true, '--force' => true]);
    
    $this->info('2/2 - Review audit...');
    Artisan::call('content:audit', ['--type' => 'reviews', '--keep-percentage' => 70, '--execute' => true, '--force' => true]);
    
    $this->info('✅ Content cleanup geforceerd uitgevoerd!');
})->purpose('Force content audit en cleanup');

// 💾 Force system maintenance (prijzen + monitoring)
Artisan::command('scheduler:force-maintenance', function () {
    $this->info('🔧 Forceren van systeem onderhoud...');

    $this->info('1/2 - Prijzen updaten...');
    Artisan::call('app:update-bol-prices', ['--limit' => 100]);

    $this->info('2/2 - Content performance monitoring...');
    Artisan::call('content:monitor', ['--days' => 30]);

    $this->info('✅ Systeem onderhoud geforceerd uitgevoerd!');
})->purpose('Force system maintenance taken');

// 📅 Show staggered schedule for this site
Artisan::command('scheduler:show-times', function () {
    $siteHash = crc32(env('APP_URL', 'default'));
    $blogStagger1 = sprintf('%02d:%02d', 1 + ($siteHash % 6), ($siteHash * 2) % 60);
    $blogStagger2 = sprintf('%02d:%02d', 1 + (($siteHash + 10) % 6), (($siteHash + 10) * 2) % 60);
    $reviewStagger1 = sprintf('%02d:%02d', 2 + ($siteHash % 5), ($siteHash * 3) % 60);
    $reviewStagger2 = sprintf('%02d:%02d', 2 + (($siteHash + 15) % 5), (($siteHash + 15) * 3) % 60);
    $productBlogStagger1 = sprintf('%02d:%02d', 1 + ($siteHash % 7), ($siteHash * 5) % 60);
    $productBlogStagger2 = sprintf('%02d:%02d', 1 + (($siteHash + 20) % 7), (($siteHash + 20) * 5) % 60);

    $this->info('📅 Content Schedule (2+2+2 schema) voor deze site:');
    $this->info('🌐 Site: ' . env('APP_URL', 'default'));
    $this->info('🔢 Hash: ' . $siteHash);
    $this->line('');
    $this->info('📝 ALGEMENE BLOGS (2x/week):');
    $this->info("   Dinsdag:  {$blogStagger1}");
    $this->info("   Vrijdag:  {$blogStagger2}");
    $this->line('');
    $this->info('⭐ REVIEWS (2x/week):');
    $this->info("   Woensdag: {$reviewStagger1}");
    $this->info("   Zaterdag: {$reviewStagger2}");
    $this->line('');
    $this->info('🏷️ PRODUCT BLOGS (2x/week):');
    $this->info("   Maandag:  {$productBlogStagger1}");
    $this->info("   Donderdag: {$productBlogStagger2}");
    $this->line('');
    $this->info('📊 Totaal: 6 artikelen per week');
    $this->info('💡 Alle sites krijgen verschillende tijden om server load te spreiden.');
})->purpose('Toon staggered schedule tijden voor deze site');
