<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('rupiah', function ($expression) {
            return "<?php echo 'Rp. ' . number_format($expression,0,',','.'); ?>";
        });

        View::composer('cashless.layouts.admin', function ($view) {
            $namaKantin = (string) (session('cashless_user.kantin') ?? '');
            if ($namaKantin === '' && session('cashless_user.username')) {
                try {
                    $result = DB::connection('DATA_MYSQL')
                        ->table('sm_kantin')
                        ->where('username', session('cashless_user.username'))
                        ->first();
                    $namaKantin = (string) ($result->NamaKantin ?? '');
                } catch (\Throwable $e) {
                    $namaKantin = '';
                }
            }
            $view->with('namaKantin', $namaKantin);
        });
    }
}
