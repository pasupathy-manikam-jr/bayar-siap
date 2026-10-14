<?php

use App\Http\Controllers\ClientLink\JobController as ClientJobController;
use App\Http\Controllers\ClientLink\OtpController;
use App\Http\Controllers\Contractor\CommentController;
use App\Http\Controllers\Contractor\DashboardController;
use App\Http\Controllers\Contractor\JobController;
use App\Http\Controllers\Contractor\MilestoneController;
use App\Http\Controllers\Contractor\MoneyController;
use App\Http\Controllers\Contractor\NotificationController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::post('locale', [LocaleController::class, 'update'])->middleware('throttle:30,1')->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('notifications', NotificationController::class)->name('notifications');
    Route::inertia('guide', 'guide')->name('guide');

    Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('jobs/create', [JobController::class, 'create'])->name('jobs.create');
    Route::post('jobs', [JobController::class, 'store'])->name('jobs.store');
    Route::get('jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
    Route::get('jobs/{job}/edit', [JobController::class, 'edit'])->name('jobs.edit');
    Route::put('jobs/{job}', [JobController::class, 'update'])->name('jobs.update');
    Route::post('jobs/{job}/send', [JobController::class, 'send'])->name('jobs.send');
    Route::post('jobs/{job}/cancel', [JobController::class, 'cancel'])->name('jobs.cancel');
    Route::get('jobs/{job}/record.pdf', [JobController::class, 'record'])->name('jobs.record');
    Route::post('jobs/{job}/variation-orders', [MoneyController::class, 'storeVariationOrder'])->name('jobs.variation-orders.store');
    Route::post('variation-orders/{variationOrder}/send', [MoneyController::class, 'sendVariationOrder'])->name('variation-orders.send');
    Route::post('jobs/{job}/payments', [MoneyController::class, 'storePayment'])->name('jobs.payments.store');

    Route::get('milestones/{milestone}', [MilestoneController::class, 'show'])->name('milestones.show');
    Route::post('milestones/{milestone}/start', [MilestoneController::class, 'start'])->name('milestones.start');
    Route::post('milestones/{milestone}/proofs', [MilestoneController::class, 'storeProofs'])->name('milestones.proofs.store');
    Route::post('milestones/{milestone}/notes', [MilestoneController::class, 'storeNote'])->name('milestones.notes.store');
    Route::delete('proofs/{proof}', [MilestoneController::class, 'destroyProof'])->name('proofs.destroy');
    Route::post('milestones/{milestone}/payment-requests', [MilestoneController::class, 'requestPayment'])->name('milestones.payment-requests.store');
    Route::post('comments', CommentController::class)->name('comments.store');
    Route::post('payment-requests/{paymentRequest}/approve-verbally', [MilestoneController::class, 'approveVerbally'])->name('payment-requests.approve-verbally');
    Route::post('payment-requests/{paymentRequest}/withdraw', [MilestoneController::class, 'withdraw'])->name('payment-requests.withdraw');
});

// Client link: the job's client opens this from WhatsApp/SMS and proves who they are by phone OTP.
Route::prefix('c/{shareToken}')->name('client.')->group(function () {
    Route::middleware('client.link:optional')->group(function () {
        Route::get('/', [ClientJobController::class, 'show'])->name('show');
        Route::post('otp/send', [OtpController::class, 'send'])->middleware('throttle:10,1')->name('otp.send');
        Route::post('otp/verify', [OtpController::class, 'verify'])->middleware('throttle:10,1')->name('otp.verify');
        Route::post('locale', [LocaleController::class, 'updateForClient'])->middleware('throttle:30,1')->name('locale');
    });

    Route::middleware('client.link')->group(function () {
        Route::post('accept', [ClientJobController::class, 'accept'])->name('accept');
        Route::get('payment-requests/{paymentRequest}', [ClientJobController::class, 'showPaymentRequest'])->name('payment-requests.show');
        Route::post('payment-requests/{paymentRequest}/approve', [ClientJobController::class, 'approve'])->name('payment-requests.approve');
        Route::post('payment-requests/{paymentRequest}/reject', [ClientJobController::class, 'reject'])->name('payment-requests.reject');
        Route::get('variation-orders/{variationOrder}', [ClientJobController::class, 'showVariationOrder'])->name('variation-orders.show');
        Route::post('variation-orders/{variationOrder}/sign', [ClientJobController::class, 'sign'])->name('variation-orders.sign');
        Route::post('variation-orders/{variationOrder}/reject', [ClientJobController::class, 'rejectVariationOrder'])->name('variation-orders.reject');
        Route::post('payments/{payment}/confirm', [ClientJobController::class, 'confirmPayment'])->name('payments.confirm');
        Route::post('payments/{payment}/dispute', [ClientJobController::class, 'disputePayment'])->name('payments.dispute');
        Route::post('comments', [ClientJobController::class, 'comment'])->name('comments.store');
        Route::get('record.pdf', [ClientJobController::class, 'record'])->name('record');
    });
});

require __DIR__.'/settings.php';
