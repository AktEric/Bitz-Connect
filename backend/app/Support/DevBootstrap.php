<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Bootstrap de DESENVOLVIMENTO (APP_ENV=local + DEV_AUTO_BOOTSTRAP=true no backend/.env).
 *
 * Em requisições web, roda sozinho dentro do container:
 *  1. `migrate` sempre que a lista de migrations mudar;
 *  2. o seeder de demonstração do Workspace (DevWorkspaceSeeder), a cada nova VERSION.
 *
 * Marcadores e logs ficam em storage/app/dev-* (ignorados pelo git).
 * Em produção o flag não existe e nada disso roda.
 */
class DevBootstrap
{
    public static function run(): void
    {
        $dir   = storage_path('app');
        $files = glob(database_path('migrations/*.php')) ?: [];
        sort($files);
        $hash  = md5(implode('|', array_map('basename', $files)));

        $seederClass = 'Database\\Seeders\\DevWorkspaceSeeder';
        $seedVersion = class_exists($seederClass) ? (string) $seederClass::VERSION : '';

        $hashFile = $dir . '/dev-migrations.hash';
        $seedFile = $dir . '/dev-seed.version';

        $migrated = is_file($hashFile) && trim((string) file_get_contents($hashFile)) === $hash;
        $seeded   = $seedVersion === '' || (is_file($seedFile) && trim((string) file_get_contents($seedFile)) === $seedVersion);

        self::maybeRunTests($dir);

        if ($migrated && $seeded) {
            return;
        }

        $lock = @fopen($dir . '/dev-bootstrap.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return; // outra requisição já está rodando o bootstrap
        }

        try {
            if (! $migrated) {
                Artisan::call('migrate', ['--force' => true]);
                file_put_contents($dir . '/dev-bootstrap.log', date('c') . "\n" . Artisan::output() . "\n", FILE_APPEND);
                file_put_contents($hashFile, $hash);
            }

            if (! $seeded) {
                Artisan::call('db:seed', ['--class' => $seederClass, '--force' => true]);
                file_put_contents($dir . '/dev-bootstrap.log', date('c') . "\n" . Artisan::output() . "\n", FILE_APPEND);
                file_put_contents($seedFile, $seedVersion);
            }

            file_put_contents($dir . '/dev-bootstrap-error.txt', '');
        } catch (Throwable $e) {
            file_put_contents($dir . '/dev-bootstrap-error.txt', date('c') . "\n" . $e->getMessage() . "\n" . $e->getTraceAsString());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Roda a suíte Pest em background quando existe storage/app/dev-run-tests.flag (conteúdo = filtro, opcional).
     * Sempre contra o banco `hotelconnect_test` (variáveis passadas explicitamente — nunca o banco de dev).
     * Saída em storage/app/dev-test-output.txt.
     */
    private static function maybeRunTests(string $dir): void
    {
        $flag = $dir . '/dev-run-tests.flag';
        if (! is_file($flag)) {
            return;
        }
        $filter = trim((string) file_get_contents($flag));
        @unlink($flag);

        try {
            $default = config('database.default');
            $pdo = \Illuminate\Support\Facades\DB::connection($default)->getPdo();
            $exists = $pdo->query("select 1 from pg_database where datname = 'hotelconnect_test'")->fetchColumn();
            if (! $exists) {
                $pdo->exec('CREATE DATABASE hotelconnect_test');
            }
        } catch (Throwable $e) {
            file_put_contents($dir . '/dev-test-output.txt', 'Falha ao preparar banco de teste: ' . $e->getMessage());
            return;
        }

        $cmd = 'cd ' . escapeshellarg(base_path()) . ' && env APP_ENV=testing DB_DATABASE=hotelconnect_test DB_CONNECTION=pgsql '
            . 'php artisan test' . ($filter !== '' ? ' --filter=' . escapeshellarg($filter) : '')
            . ' > ' . escapeshellarg($dir . '/dev-test-output.txt') . ' 2>&1 &';
        exec($cmd);
    }
}
