<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\PermissionController;

/*
|--------------------------------------------------------------------------
| Permission Routes
|--------------------------------------------------------------------------
|
| Here is where you can register permission routes for your application.
|
*/

Route::middleware(['auth', 'web'])->prefix('admin/permissions')->name('admin.permissions.')->group(function () {
    // Trang quản lý phân quyền - redirect to users
    Route::get('/', function() {
        return redirect()->route('admin.users.index');
    })->name('index');
    
    // API routes
    Route::prefix('api')->name('api.')->group(function () {
        // Lấy quyền của user
        Route::get('/user/{userId}/permissions', [PermissionController::class, 'getUserPermissions'])->name('user.permissions');
        
        // Phân quyền chức năng
        Route::post('/assign', [PermissionController::class, 'assignUserPermissions'])->name('assign');
        Route::put('/user/{userId}/permissions', [PermissionController::class, 'updateUserPermissions'])->name('update');
        Route::delete('/user/{userId}/permissions', [PermissionController::class, 'removeUserPermissions'])->name('remove');
        
        // Lấy dữ liệu hỗ trợ
        Route::get('/assignable-functions', [PermissionController::class, 'getAssignableFunctions'])->name('assignable.functions');
        Route::get('/assignable-roles', [PermissionController::class, 'getAssignableRoles'])->name('assignable.roles');
        Route::get('/stats', [PermissionController::class, 'getStats'])->name('stats');
        
        // Tạo user với phân quyền
        Route::post('/create-user', [PermissionController::class, 'createUserWithPermissions'])->name('create.user');
    });
    
    // Quản lý chức năng hệ thống
    Route::get('/functions', [PermissionController::class, 'functions'])->name('functions');
    Route::get('/functions/create', [PermissionController::class, 'createFunctionForm'])->name('functions.create');
    Route::post('/functions', [PermissionController::class, 'storeFunction'])->name('functions.store');
    Route::get('/functions/{function}/edit', [PermissionController::class, 'editFunctionForm'])->name('functions.edit');
    Route::put('/functions/{function}', [PermissionController::class, 'updateFunctionForm'])->name('functions.update');
    Route::delete('/functions/{function}', [PermissionController::class, 'destroyFunctionForm'])->name('functions.destroy');
    
    // Quản lý danh mục chức năng
    Route::get('/categories', [PermissionController::class, 'categories'])->name('categories');
    Route::get('/categories/create', [PermissionController::class, 'createCategoryForm'])->name('categories.create');
    Route::post('/categories', [PermissionController::class, 'storeCategory'])->name('categories.store');
    Route::get('/categories/{category}/edit', [PermissionController::class, 'editCategoryForm'])->name('categories.edit');
    Route::put('/categories/{category}', [PermissionController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{category}', [PermissionController::class, 'destroyCategory'])->name('categories.destroy');
    
    // Phân quyền cho user cụ thể
    Route::get('/user/{user}', [PermissionController::class, 'userPermissions'])->name('user');
    Route::get('/user/{user}/view', [PermissionController::class, 'viewPermissions'])->name('user.view');
    Route::post('/user/{user}/assign', [PermissionController::class, 'assignUserPermissionsForm'])->name('user.assign');
    
    // API cho chức năng
    Route::prefix('api')->name('api.')->group(function () {
        Route::post('/functions/create', [PermissionController::class, 'createFunction'])->name('functions.create');
        Route::get('/functions/{id}', [PermissionController::class, 'showFunction'])->name('functions.show');
        Route::post('/functions/{id}/update', [PermissionController::class, 'updateFunction'])->name('functions.update');
        Route::delete('/functions/{id}', [PermissionController::class, 'destroyFunction'])->name('functions.destroy');
    });
});
