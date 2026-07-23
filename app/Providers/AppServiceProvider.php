<?php

namespace App\Providers;

use App\Models\Delivery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // MySQL 5.7 (the version shipped with MAMP) caps an index entry at 767
        // bytes under the older row formats. utf8mb4 uses 4 bytes per character,
        // so a default 255-character string column would overflow a unique
        // index. 191 * 4 = 764 keeps every unique index inside the limit.
        Schema::defaultStringLength(191);

        // Fail loudly in development instead of silently returning null for a
        // relationship nobody remembered to eager load.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Blocks mass-assignment of an `id` or a relation key that is not in
        // the model's $fillable list.
        Model::unguard(false);

        $this->configurePasswordRules();
        $this->configureHttps();
        $this->configureBlade();
        $this->shareNavigationData();
        $this->logSlowQueries();
    }

    /**
     * Blade helpers used by the layout and listing views.
     */
    private function configureBlade(): void
    {
        // @active('parcels*') -> prints "active" when the current URL matches,
        // which is what highlights the sidebar link.
        Blade::directive('active', function (string $expression): string {
            return "<?php echo request()->is({$expression}) ? 'active' : ''; ?>";
        });

        // @money(1234.5) -> "Rs. 1,234.50"
        Blade::directive('money', function (string $expression): string {
            return "<?php echo config('courier.pricing.currency_symbol').' '.number_format((float) ({$expression}), 2); ?>";
        });
    }

    /**
     * Counts the sidebar badges need, resolved lazily so pages that do not
     * render the sidebar never run the query.
     */
    private function shareNavigationData(): void
    {
        View::composer('layouts.partials.sidebar', function ($view): void {
            $user = Auth::user();

            $view->with(
                'pendingAssignments',
                $user?->isDriver() && $user->driver
                    ? Delivery::query()
                        ->where('driver_id', $user->driver->id)
                        ->pendingResponse()
                        ->count()
                    : 0
            );
        });
    }

    /**
     * One password policy for registration, reset and change-password screens.
     */
    private function configurePasswordRules(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8)->letters()->mixedCase()->numbers();

            // Checking passwords against the haveibeenpwned API is valuable in
            // production but makes local tests depend on the network.
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * Generate https:// links when the app is served over TLS, so QR codes and
     * password-reset emails do not point at an insecure URL.
     */
    private function configureHttps(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Surface N+1 queries and missing indexes during development.
     */
    private function logSlowQueries(): void
    {
        if ($this->app->isProduction()) {
            return;
        }

        DB::listen(function ($query) {
            if ($query->time > 500) {
                logger()->warning('Slow query detected', [
                    'sql' => $query->sql,
                    'time_ms' => $query->time,
                ]);
            }
        });
    }
}
