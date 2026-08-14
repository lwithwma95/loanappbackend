<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SetupPasswordController;
use App\Http\Controllers\TransferRequestController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\MIS\MISController;
use App\Http\Controllers\MIS\MappingController;
use App\Http\Controllers\Financial\BankController;
use App\Http\Controllers\Financial\BlockCadreController;
use App\Http\Controllers\Financial\GeoController;
use App\Http\Controllers\Financial\MemberLoanController;
use App\Http\Controllers\Financial\MemberLoanPaymentController;
use App\Http\Controllers\Financial\MemberSearchController;
use App\Http\Controllers\Financial\ShgLoanController;
use App\Http\Controllers\Financial\ShgLoanPaymentController;
use App\Http\Controllers\Public\PublicGeoController;
use App\Http\Controllers\Financial\LoanSchemeController;
use App\Http\Controllers\HR\AttendanceController;
use App\Http\Controllers\HR\DesignationController;
use App\Http\Controllers\HR\EmployeeController;
use App\Http\Controllers\HR\LocationController;
use App\Http\Controllers\HR\PayrollController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::get('/test', fn () => response()->json(['status' => 'working']));

// Public (no auth, fully stateless) — geographic hierarchy lookups
Route::prefix('public')
    ->withoutMiddleware([StartSession::class, EncryptCookies::class, AddQueuedCookiesToResponse::class])
    ->group(function () {
    Route::get('designations',  [PublicGeoController::class, 'designations']);
    Route::get('geo/districts', [PublicGeoController::class, 'districts']);
    Route::get('geo/blocks',    [PublicGeoController::class, 'blocks']);
    Route::get('geo/gps',       [PublicGeoController::class, 'gps']);
    Route::get('geo/villages',  [PublicGeoController::class, 'villages']);
    Route::get('geo/shgs',      [PublicGeoController::class, 'shgs']);
    Route::get('geo/members',   [PublicGeoController::class, 'members']);
    Route::get('geo/member-lookup', [PublicGeoController::class, 'lookupMember']);
});

Route::prefix('auth')->group(function () {
    // Public auth endpoints — no token required
    Route::post('/login',                     [LoginController::class,        'login']);
    Route::post('/register',                  [RegisterController::class,     'register']);
    Route::post('/setup-password',            [SetupPasswordController::class,'setup']);
    Route::post('/forgot-password/verify',    [ForgotPasswordController::class,'verify']);
    Route::post('/forgot-password/reset',     [ForgotPasswordController::class,'reset']);

    // Protected auth endpoints — Sanctum token required
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout',   [LoginController::class, 'logout']);
        Route::get('/me',        [LoginController::class, 'me']);
        Route::put('/profile',   [LoginController::class, 'profile']);
    });
});

Route::prefix('users')->middleware('auth:sanctum')->group(function () {
    Route::get('/pending',               [UserManagementController::class, 'pending']);
    Route::get('/all',                   [UserManagementController::class, 'all']);
    Route::post('/{id}/approve',         [UserManagementController::class, 'approve']);
    Route::post('/{id}/reject',          [UserManagementController::class, 'reject']);
    Route::get('/{id}/credential-status',[UserManagementController::class, 'credentialStatus']);
    Route::post('/{id}/reset-password',  [UserManagementController::class, 'setPassword']);
});

Route::prefix('transfers')->middleware('auth:sanctum')->group(function () {
    Route::get('/',       [TransferRequestController::class, 'index']);
    Route::post('/',      [TransferRequestController::class, 'store']);
    Route::patch('/{id}', [TransferRequestController::class, 'update']);
});

Route::prefix('hr')->middleware('auth:sanctum')->group(function () {
    Route::get('districts', [LocationController::class, 'districts']);
    Route::get('blocks',    [LocationController::class, 'blocks']);
    Route::apiResource('designations', DesignationController::class);
    Route::apiResource('employees',    EmployeeController::class);
    Route::get('employees/{employee}/credential-status',  [EmployeeController::class, 'credentialStatus']);
    Route::patch('employees/{employee}/reset-password',   [EmployeeController::class, 'resetPassword']);
    Route::apiResource('attendance', AttendanceController::class)->only(['index', 'store', 'show']);
    Route::apiResource('payroll', PayrollController::class)->except(['edit', 'create']);
    Route::patch('payroll/{payroll}/status', [PayrollController::class, 'updateStatus']);
});

Route::prefix('financial')->middleware('auth:sanctum')->group(function () {
    Route::get('members/search', [MemberSearchController::class, 'search']);

    // Geographic hierarchy (Districts → Blocks → GP → Village → SHG → Member)
    Route::get('geo/districts', [GeoController::class, 'districts']);
    Route::get('geo/blocks',    [GeoController::class, 'blocks']);
    Route::get('geo/gps',     [GeoController::class, 'gps']);
    Route::get('geo/villages',[GeoController::class, 'villages']);
    Route::get('geo/shgs',    [GeoController::class, 'shgs']);
    Route::get('geo/members', [GeoController::class, 'members']);

    Route::apiResource('cadres', BlockCadreController::class, ['parameters' => ['cadres' => 'blockCadre']]);

    // Banks
    Route::get('banks/unassigned',  [BankController::class, 'unassigned']);
    Route::get('banks/names',       [BankController::class, 'names']);
    Route::get('banks/search',      [BankController::class, 'search']);
    Route::post('banks/bulk',      [BankController::class, 'bulkUpload']);
    Route::post('banks/pull-ifsc', [BankController::class, 'pullIfsc']);
    Route::apiResource('banks', BankController::class)->except(['show']);
    Route::apiResource('loan-schemes', LoanSchemeController::class)->parameters(['loan-schemes' => 'loanScheme'])->except(['show']);

    // Member Loans
    Route::post('member-loans/bulk',            [MemberLoanController::class, 'bulkUpload']);
    Route::post('member-loans/bank-reconcile',  [MemberLoanController::class, 'bankReconcile']);
    Route::get('member-loans/export',           [MemberLoanController::class, 'export']);
    Route::get('member-loans/needs-block-mapping',        [MemberLoanController::class, 'needsBlockMapping']);
    Route::patch('member-loans/{memberloan}/assign-block',    [MemberLoanController::class, 'assignBlock']);
    Route::patch('member-loans/{memberloan}/assign-district', [MemberLoanController::class, 'assignDistrict']);
    Route::apiResource('member-loans', MemberLoanController::class, ['parameters' => ['member-loans' => 'memberloan']]);
    Route::get('member-loans/{memberloan}/payments',              [MemberLoanPaymentController::class, 'index']);
    Route::post('member-loans/{memberloan}/pay',                  [MemberLoanPaymentController::class, 'store']);
    Route::delete('member-loans/{memberloan}/payments/{payment}', [MemberLoanPaymentController::class, 'destroy']);

    // SHG Loans
    Route::post('shg-loans/bulk',  [ShgLoanController::class, 'bulkUpload']);
    Route::get('shg-loans/export', [ShgLoanController::class, 'export']);
    Route::apiResource('shg-loans', ShgLoanController::class, ['parameters' => ['shg-loans' => 'shgloan']]);
    Route::get('shg-loans/{shgloan}/payments',              [ShgLoanPaymentController::class, 'index']);
    Route::post('shg-loans/{shgloan}/pay',                  [ShgLoanPaymentController::class, 'store']);
    Route::delete('shg-loans/{shgloan}/payments/{payment}', [ShgLoanPaymentController::class, 'destroy']);
});

Route::prefix('mis')->middleware('auth:sanctum')->group(function () {
    Route::get('/districts',  [MISController::class, 'districts']);
    Route::get('/blocks',     [MISController::class, 'blocks']);
    Route::get('/gps',        [MISController::class, 'gps']);
    Route::get('/villages',   [MISController::class, 'villages']);
    Route::get('/shgs',       [MISController::class, 'shgs']);
    Route::get('/members',    [MISController::class, 'members']);
    Route::patch('/members/{memCode}',        [MISController::class, 'updateMember'])->where('memCode', '[^/]+');
    Route::patch('/shgs/{shgCode}/toggle',    [MISController::class, 'toggleShg'])->where('shgCode', '[^/]+');
    Route::patch('/members/{memCode}/toggle', [MISController::class, 'toggleMember'])->where('memCode', '[^/]+');
});

Route::prefix('mapping')->middleware('auth:sanctum')->group(function () {
    Route::post('/districts', [MappingController::class, 'storeDistrict']);
    Route::post('/blocks',   [MappingController::class, 'storeBlock']);
    Route::post('/gps',      [MappingController::class, 'storeGp']);
    Route::post('/villages', [MappingController::class, 'storeVillage']);
    Route::post('/shgs',     [MappingController::class, 'storeShg']);
    Route::post('/members',  [MappingController::class, 'storeMember']);
    Route::patch('/blocks/{blockId}/remap',  [MappingController::class, 'remapBlock']);
    Route::patch('/gps/{gpId}/remap',        [MappingController::class, 'remapGp']);
    Route::patch('/villages/{villId}/remap', [MappingController::class, 'remapVillage']);
    Route::patch('/shgs/{shgCode}/remap',    [MappingController::class, 'remapShg'])->where('shgCode', '[^/]+');
    Route::patch('/members/{memCode}/remap', [MappingController::class, 'remapMember'])->where('memCode', '[^/]+');
});
