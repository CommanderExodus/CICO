<?php

namespace App\Providers;

use App\Enums\AuditChannel;
use App\Support\AuditContext;
use App\Support\LocationTree;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AuditContext::class);
        $this->app->scoped(LocationTree::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuditContext();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Reicht den Audit-Kontext (wer, über welchen Kanal) vor der nächsten Abfrage
     * an PostgreSQL weiter, wo ihn die Audit-Trigger auslesen (R18, R33).
     */
    protected function configureAuditContext(): void
    {
        Event::listen(function (ConnectionEstablished $event) {
            $event->connection->beforeExecuting(
                fn ($query, $bindings, $connection) => $this->app->make(AuditContext::class)->applyIfPending($connection),
            );
        });

        // Ein Rollback setzt auch set_config() zurück; der Kontext muss danach neu übertragen werden.
        Event::listen(fn (TransactionRolledBack $event) => $this->app->make(AuditContext::class)->markPending());

        Event::listen(fn (CommandStarting $event) => $this->app->make(AuditContext::class)->set(null, AuditChannel::Console));
    }
}
