<?php

use App\Http\Controllers\Api\V1\Company\BranchController;
use App\Http\Controllers\Api\V1\Company\CompanyController;
use App\Http\Controllers\Api\V1\Company\DepartmentController;
use App\Http\Controllers\Api\V1\Company\MemberController;
use Illuminate\Support\Facades\Route;

// Current company profile & settings
Route::get('company', [CompanyController::class, 'show'])->name('company.show');
Route::put('company', [CompanyController::class, 'update'])->name('company.update');
Route::post('company/logo', [CompanyController::class, 'uploadLogo'])->name('company.logo.store');
Route::delete('company/logo', [CompanyController::class, 'deleteLogo'])->name('company.logo.destroy');

// Branches
Route::get('branches/all', [BranchController::class, 'all'])->name('branches.all');
Route::apiResource('branches', BranchController::class);

// Departments
Route::get('departments/tree', [DepartmentController::class, 'tree'])->name('departments.tree');
Route::apiResource('departments', DepartmentController::class);

// Member picker: any active member of the company (no users.view); optional ?permission= filter
Route::get('members', [MemberController::class, 'index'])->name('members.index');
