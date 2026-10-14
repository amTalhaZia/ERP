<?php

use App\Http\Controllers\Api\V1\Procurement\GoodsReceiptController;
use App\Http\Controllers\Api\V1\Procurement\ProcurementReportController;
use App\Http\Controllers\Api\V1\Procurement\PurchaseOrderController;
use App\Http\Controllers\Api\V1\Procurement\PurchaseRequisitionController;
use App\Http\Controllers\Api\V1\Procurement\PurchaseReturnController;
use App\Http\Controllers\Api\V1\Procurement\SupplierContactController;
use App\Http\Controllers\Api\V1\Procurement\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Procurement
|--------------------------------------------------------------------------
| Loaded inside auth:sanctum + active + tenant. Policies carry the fine-grained
| checks; `permission:` middleware is the coarse gate on approval-type verbs.
*/

// Suppliers
Route::get('suppliers/all', [SupplierController::class, 'all'])->name('suppliers.all');
Route::get('suppliers/map', [SupplierController::class, 'map'])->name('suppliers.map');
Route::get('suppliers/{supplier}/summary', [SupplierController::class, 'summary'])->name('suppliers.summary');
Route::post('suppliers/{supplier}/approve', [SupplierController::class, 'approve'])
    ->middleware('permission:suppliers.manage')->name('suppliers.approve');
Route::apiResource('suppliers', SupplierController::class);
Route::apiResource('suppliers.contacts', SupplierContactController::class)->scoped()->except(['show']);

// Purchase requisitions
Route::post('requisitions/{requisition}/submit', [PurchaseRequisitionController::class, 'submit'])->name('requisitions.submit');
Route::post('requisitions/{requisition}/approve', [PurchaseRequisitionController::class, 'approve'])
    ->middleware('permission:requisitions.approve')->name('requisitions.approve');
Route::post('requisitions/{requisition}/reject', [PurchaseRequisitionController::class, 'reject'])
    ->middleware('permission:requisitions.approve')->name('requisitions.reject');
Route::post('requisitions/{requisition}/cancel', [PurchaseRequisitionController::class, 'cancel'])->name('requisitions.cancel');
Route::post('requisitions/{requisition}/create-purchase-order', [PurchaseRequisitionController::class, 'createPurchaseOrder'])
    ->middleware('permission:purchase_orders.manage')->name('requisitions.create-purchase-order');
Route::apiResource('requisitions', PurchaseRequisitionController::class);

// Purchase orders
Route::post('purchase-orders/{purchase_order}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
Route::post('purchase-orders/{purchase_order}/approve', [PurchaseOrderController::class, 'approve'])
    ->middleware('permission:purchase_orders.approve')->name('purchase-orders.approve');
Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
Route::post('purchase-orders/{purchase_order}/close', [PurchaseOrderController::class, 'close'])->name('purchase-orders.close');
Route::post('purchase-orders/{purchase_order}/send', [PurchaseOrderController::class, 'send'])->name('purchase-orders.send');
Route::post('purchase-orders/{purchase_order}/goods-receipts', [GoodsReceiptController::class, 'storeForOrder'])
    ->middleware('permission:goods_receipts.manage')->name('purchase-orders.goods-receipts.store');
Route::apiResource('purchase-orders', PurchaseOrderController::class);

// Goods receipts (created via the purchase order above)
Route::post('goods-receipts/{goods_receipt}/confirm', [GoodsReceiptController::class, 'confirm'])
    ->middleware('permission:goods_receipts.manage')->name('goods-receipts.confirm');
Route::post('goods-receipts/{goods_receipt}/cancel', [GoodsReceiptController::class, 'cancel'])->name('goods-receipts.cancel');
Route::apiResource('goods-receipts', GoodsReceiptController::class)->only(['index', 'show', 'update', 'destroy']);

// Purchase returns
Route::post('purchase-returns/{purchase_return}/confirm', [PurchaseReturnController::class, 'confirm'])
    ->middleware('permission:goods_receipts.manage')->name('purchase-returns.confirm');
Route::post('purchase-returns/{purchase_return}/cancel', [PurchaseReturnController::class, 'cancel'])->name('purchase-returns.cancel');
Route::apiResource('purchase-returns', PurchaseReturnController::class);

// Reports
Route::prefix('procurement/reports')->name('procurement.reports.')->middleware('permission:reports.view')->group(function (): void {
    Route::get('spend', [ProcurementReportController::class, 'spend'])->name('spend');
    Route::get('status-summary', [ProcurementReportController::class, 'statusSummary'])->name('status-summary');
    Route::get('supplier-performance', [ProcurementReportController::class, 'supplierPerformance'])->name('supplier-performance');
});
