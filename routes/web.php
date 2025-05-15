<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Plc\OEEReportController;
use App\Http\Controllers\Plc\ProductionReportController;
use App\Http\Controllers\Plc\DashboardController;
use App\Http\Controllers\Plc\MonthlyOEEController;
use App\Http\Controllers\Plc\PlannedDowntimeController;
use App\Http\Controllers\Plc\PlcController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });

Route::group(['prefix' => 'laravel-filemanager', 'middleware' => ['web', 'unisharp']], function () {
    \UniSharp\LaravelFilemanager\Lfm::routes();
});

Route::get('/login', ['as' => 'auth.get_login', 'uses' => 'Auth\AuthController@getLogin']);
Route::post('/login', ['as' => 'auth.login', 'uses' => 'Auth\AuthController@login']);
Route::get('/forget-password', ['as' => 'auth.forget_password', 'uses' => 'Auth\AuthController@forgetPassword']);

//Ckfinder
Route::any('/ckfinder/connector', '\CKSource\CKFinderBridge\Controller\CKFinderController@requestAction')
    ->name('ckfinder_connector');
Route::any('/ckfinder/browser', '\CKSource\CKFinderBridge\Controller\CKFinderController@browserAction')
    ->name('ckfinder_browser');
//Route::post('/forget-password', ['as' => 'auth.forget_password', 'uses' => 'Auth\AuthController@ForgetPassword']);

Route::prefix('plc')->name('plc.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/get-oee-data', [DashboardController::class, 'getOEEData'])->name('dashboard.get-oee-data');
    Route::get('oee', [OEEReportController::class, 'index'])->name('oee.index');
    Route::post('oee', [OEEReportController::class, 'show'])->name('oee.show');
    
    // Thêm route cho báo cáo OEE tháng
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/monthly-oee/report', [MonthlyOEEController::class, 'report'])->name('monthly-oee');
        Route::get('/monthly-oee/index', [MonthlyOEEController::class, 'index'])->name('monthly-oee.index');
        Route::get('/monthly-oee-form', [MonthlyOEEController::class, 'index'])->name('monthly-oee-form');
        Route::get('/monthly-oee/export', [MonthlyOEEController::class, 'export'])->name('monthly-oee.export');
        Route::get('/monthly-oee/create', [MonthlyOEEController::class, 'create'])->name('monthly-oee.create');
        Route::post('/monthly-oee', [MonthlyOEEController::class, 'store'])->name('monthly-oee.store');
        Route::get('/monthly-oee/{id}/edit', [MonthlyOEEController::class, 'edit'])->name('monthly-oee.edit');
        Route::put('/monthly-oee/{id}', [MonthlyOEEController::class, 'update'])->name('monthly-oee.update');
        Route::delete('/monthly-oee/{id}', [MonthlyOEEController::class, 'destroy'])->name('monthly-oee.destroy');
    });
    
    // Routes cho planned downtimes
    Route::resource('planned-downtimes', PlannedDowntimeController::class);
    
    // Routes cho báo cáo sản xuất
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/production', [ProductionReportController::class, 'index'])->name('production.index');
        Route::get('/production/export', [ProductionReportController::class, 'exportExcel'])->name('production.export');
    });
    
    // Routes cho ProductionEntry
    Route::prefix('production')->name('production.')->group(function () {
        Route::get('/entries', [\App\Http\Controllers\Plc\ProductionEntryController::class, 'index'])->name('entries.index');
        Route::get('/entries/create', [\App\Http\Controllers\Plc\ProductionEntryController::class, 'create'])->name('entries.create');
        Route::post('/entries', [\App\Http\Controllers\Plc\ProductionEntryController::class, 'store'])->name('entries.store');
        Route::get('/entries/{id}/edit', [\App\Http\Controllers\Plc\ProductionEntryController::class, 'edit'])->name('entries.edit');
        Route::put('/entries/{id}', [\App\Http\Controllers\Plc\ProductionEntryController::class, 'update'])->name('entries.update');
        Route::delete('/entries/{id}', [\App\Http\Controllers\Plc\ProductionEntryController::class, 'destroy'])->name('entries.destroy');
        Route::get('/entries/import', [\App\Http\Controllers\Plc\ProductionEntryController::class, 'showImportForm'])->name('entries.import');
        Route::post('/entries/import', [\App\Http\Controllers\Plc\ProductionEntryController::class, 'import'])->name('entries.import.process');
        Route::get('/entries/import/template', [\App\Http\Controllers\Plc\ProductionEntryController::class, 'downloadTemplate'])->name('entries.import.template');
    });

    Route::prefix('api')->name('api.')->group(function () {
        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::get('machine-oee', [DashboardController::class, 'getMachineOEEData'])->name('machine-oee');
        });
    });

    Route::get('/monitor', [App\Http\Controllers\PlcController::class, 'monitor'])->name('monitor');
});
