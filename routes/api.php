<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\MilestoneController;
use App\Http\Controllers\Api\MoneyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('company', [AuthController::class, 'updateCompany']);
        Route::get('notifications', [AuthController::class, 'notifications']);
        Route::post('billing/{action}', [AuthController::class, 'billing'])->whereIn('action', ['checkout', 'portal']);

        Route::get('clients', [JobController::class, 'clients']);
        Route::post('clients', [JobController::class, 'storeClient']);
        Route::get('milestone-templates', [JobController::class, 'templates']);

        Route::get('jobs', [JobController::class, 'index']);
        Route::post('jobs', [JobController::class, 'store']);
        Route::get('jobs/{job}', [JobController::class, 'show']);
        Route::put('jobs/{job}', [JobController::class, 'update']);
        Route::post('jobs/{job}/send', [JobController::class, 'send']);
        Route::post('jobs/{job}/cancel', [JobController::class, 'cancel']);
        Route::get('jobs/{job}/activity', [JobController::class, 'activity']);
        Route::get('jobs/{job}/record.pdf', [JobController::class, 'record']);
        Route::post('jobs/{job}/milestones', [JobController::class, 'storeMilestone']);

        Route::get('milestones/{milestone}', [MilestoneController::class, 'show']);
        Route::put('milestones/{milestone}', [MilestoneController::class, 'update']);
        Route::post('milestones/{milestone}/start', [MilestoneController::class, 'start']);
        Route::post('milestones/{milestone}/proofs/upload-url', [MilestoneController::class, 'uploadUrl']);
        Route::post('milestones/{milestone}/proofs', [MilestoneController::class, 'storeProof']);
        Route::delete('proofs/{proof}', [MilestoneController::class, 'destroyProof']);
        Route::post('milestones/{milestone}/payment-requests', [MilestoneController::class, 'requestPayment']);
        Route::post('payment-requests/{paymentRequest}/withdraw', [MilestoneController::class, 'withdraw']);

        Route::post('jobs/{job}/variation-orders', [MoneyController::class, 'storeVariationOrder']);
        Route::post('variation-orders/{variationOrder}/send', [MoneyController::class, 'sendVariationOrder']);
        Route::post('jobs/{job}/payments', [MoneyController::class, 'storePayment']);
        Route::post('comments', [MoneyController::class, 'storeComment']);
        Route::post('devices', [MoneyController::class, 'storeDevice']);
    });
});
