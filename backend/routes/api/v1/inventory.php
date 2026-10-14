<?php

use App\Http\Controllers\Api\V1\Inventory\InventoryReportController;
use App\Http\Controllers\Api\V1\Inventory\ProductCategoryController;
use App\Http\Controllers\Api\V1\Inventory\ProductController;
use App\Http\Controllers\Api\V1\Inventory\StockAdjustmentController;
use App\Http\Controllers\Api\V1\Inventory\StockController;
use App\Http\Controllers\Api\V1\Inventory\StockTransferController;
use App\Http\Controllers\Api\V1\Inventory\StorageLocationController;
use App\Http\Controllers\Api\V1\Inventory\UnitOfMeasureController;
use App\Http\Controllers\Api\V1\Inventory\WarehouseController;
use Illuminate\Support\Facades\Route;

// Catalogue (products.view / products.manage)
Route::apiResource('units-of-measure', UnitOfMeasureController::class)->parameters(['units-of-measure' => 'unit_of_measure']);
Route::get('product-categories/tree', [ProductCategoryController::class, 'tree'])->name('product-categories.tree');
Route::apiResource('product-categories', ProductCategoryController::class);
Route::get('products/low-stock', [ProductController::class, 'lowStock'])->name('products.low-stock');
Route::apiResource('products', ProductController::class);
Route::post('products/{product}/image', [ProductController::class, 'uploadImage'])->name('products.image.store');
Route::delete('products/{product}/image', [ProductController::class, 'destroyImage'])->middleware('permission:products.manage')->name('products.image.destroy');
Route::get('products/{product}/batches', [ProductController::class, 'batches'])->name('products.batches');
Route::get('products/{product}/stock', [ProductController::class, 'stock'])->name('products.stock');
Route::get('products/{product}/movements', [ProductController::class, 'movements'])->name('products.movements');

// Warehouses & storage locations (warehouses.view / warehouses.manage)
Route::get('warehouses/all', [WarehouseController::class, 'all'])->name('warehouses.all');
Route::apiResource('warehouses', WarehouseController::class);
Route::apiResource('warehouses.locations', StorageLocationController::class)->shallow();

// Stock operations & ledger
Route::post('stock/receive', [StockController::class, 'receive'])->middleware('permission:inventory.receive')->name('stock.receive');
Route::post('stock/issue', [StockController::class, 'issue'])->middleware('permission:inventory.issue')->name('stock.issue');
Route::get('stock/levels', [StockController::class, 'levels'])->name('stock.levels');
Route::get('stock/movements', [StockController::class, 'movements'])->name('stock.movements');

// Transfers (inventory.transfer)
Route::apiResource('stock-transfers', StockTransferController::class);
Route::post('stock-transfers/{stock_transfer}/ship', [StockTransferController::class, 'ship'])->middleware('permission:inventory.transfer')->name('stock-transfers.ship');
Route::post('stock-transfers/{stock_transfer}/receive', [StockTransferController::class, 'receive'])->middleware('permission:inventory.transfer')->name('stock-transfers.receive');
Route::post('stock-transfers/{stock_transfer}/cancel', [StockTransferController::class, 'cancel'])->middleware('permission:inventory.transfer')->name('stock-transfers.cancel');

// Adjustments (inventory.adjust)
Route::apiResource('stock-adjustments', StockAdjustmentController::class);
Route::post('stock-adjustments/{stock_adjustment}/post', [StockAdjustmentController::class, 'post'])->middleware('permission:inventory.adjust')->name('stock-adjustments.post');

// Reports (inventory.view)
Route::get('inventory/reports/valuation', [InventoryReportController::class, 'valuation'])->name('inventory.reports.valuation');
Route::get('inventory/reports/movement-summary', [InventoryReportController::class, 'movementSummary'])->name('inventory.reports.movement-summary');
