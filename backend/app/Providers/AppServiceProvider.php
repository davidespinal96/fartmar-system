<?php

namespace App\Providers;

use App\Contracts\InventoryAssistantProvider;
use App\Models\User;
use App\Services\MockInventoryAssistantProvider;
use App\Services\OpenAIInventoryAssistantProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(InventoryAssistantProvider::class, function ($app) {
            return config('services.inventory_assistant.provider') === 'openai'
                ? $app->make(OpenAIInventoryAssistantProvider::class)
                : $app->make(MockInventoryAssistantProvider::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('inventory.adjust', fn (User $user) => $user->role === 'regente_farmacia');
        Gate::define('transfer.approve', fn (User $user) => in_array($user->role, ['regente_farmacia', 'admin'], true));
        Gate::define('prescription.create', fn (User $user) => $user->role === 'medico');
        Gate::define('controlled-medication.authorize', fn (User $user) => $user->role === 'regente_farmacia');
    }
}
