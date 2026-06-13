<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SendController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\Trb140WebhookController;
use App\Http\Controllers\Api\V1\TwoFactorController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', [HealthController::class, 'index'])->name('api.health');

    // Signup is throttled per IP (not per token, since there's no token yet).
    // `turnstile` middleware is a no-op unless TURNSTILE_SECRET is set.
    Route::post('auth/signup',          [AuthController::class, 'signup'])->middleware(['throttle:signup', 'turnstile']);
    Route::post('auth/login',           [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('auth/reset-password',  [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::get('auth/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

    // Inbound webhooks from external systems.
    Route::post('webhooks/stripe',  [StripeWebhookController::class,  'handle']);
    Route::post('webhooks/trb140',  [Trb140WebhookController::class,  'handle'])->middleware('webhook.signed');

    Route::middleware(['auth:sanctum', 'team', 'verified.2fa'])->group(function () {
        Route::post('auth/logout',  [AuthController::class, 'logout']);
        Route::get ('auth/me',      [AuthController::class, 'me']);

        // Profile / team / GDPR.
        Route::patch('me',                  [ProfileController::class, 'update']);
        Route::post ('me/change-password',  [ProfileController::class, 'changePassword']);
        Route::patch('me/team',             [ProfileController::class, 'team']);
        Route::get  ('me/export',           [ProfileController::class, 'export']);

        // 2FA.
        Route::post  ('auth/2fa/enable',  [TwoFactorController::class, 'enable']);
        Route::post  ('auth/2fa/confirm', [TwoFactorController::class, 'confirm']);
        Route::delete('auth/2fa',         [TwoFactorController::class, 'disable']);

        // API key CRUD (token mgmt).
        Route::get   ('api-keys',        [AuthController::class, 'listTokens']);
        Route::post  ('api-keys',        [AuthController::class, 'issueToken'])->middleware('throttle:10,1');
        Route::delete('api-keys/{id}',   [AuthController::class, 'revokeToken']);

        // Send SMS.
        Route::post('send-sms',   [SendController::class, 'single'])->middleware('throttle:sms');
        Route::post('send-bulk',  [SendController::class, 'bulk'])->middleware('throttle:sms');

        // Messages.
        Route::get('messages',                [MessageController::class, 'index']);
        Route::get('messages/{id}',           [MessageController::class, 'show']);
        Route::get('messages/{id}/events',    [MessageController::class, 'events']);
        Route::get('inbox/threads',           [MessageController::class, 'threads']);
        Route::get('inbox/threads/{contact}', [MessageController::class, 'thread']);

        // Contacts & groups.
        Route::apiResource('contacts',  ContactController::class);
        Route::post  ('contacts/import',  [ContactController::class, 'import']);
        Route::get   ('contacts/export',  [ContactController::class, 'export']);
        Route::apiResource('groups',     GroupController::class);
        Route::post  ('groups/{id}/contacts', [GroupController::class, 'attach']);
        Route::delete('groups/{id}/contacts', [GroupController::class, 'detach']);

        // Templates & campaigns.
        Route::apiResource('templates', TemplateController::class);
        Route::apiResource('campaigns', CampaignController::class);
        Route::post('campaigns/{id}/launch', [CampaignController::class, 'launch']);
        Route::post('campaigns/{id}/pause',  [CampaignController::class, 'pause']);

        // Reports.
        Route::get('reports/usage',    [ReportController::class, 'usage']);
        Route::get('reports/delivery', [ReportController::class, 'delivery']);

        // Webhook subscriptions.
        Route::apiResource('webhooks', WebhookController::class)->only(['index', 'store', 'destroy']);

        // Automations (trigger/action rules).
        Route::apiResource('automations', AutomationController::class);
    });
});
