<?php

use App\Http\Controllers\Api\V1\Sales\CustomerCommunicationController;
use App\Http\Controllers\Api\V1\Sales\CustomerContactController;
use App\Http\Controllers\Api\V1\Sales\CustomerController;
use App\Http\Controllers\Api\V1\Sales\DeliveryController;
use App\Http\Controllers\Api\V1\Sales\LeadController;
use App\Http\Controllers\Api\V1\Sales\OpportunityController;
use App\Http\Controllers\Api\V1\Sales\PipelineStageController;
use App\Http\Controllers\Api\V1\Sales\QuotationController;
use App\Http\Controllers\Api\V1\Sales\SalesOrderController;
use App\Http\Controllers\Api\V1\Sales\SalesReportController;
use Illuminate\Support\Facades\Route;

// -- Customers & CRM ---------------------------------------------------------
Route::get('customers/all', [CustomerController::class, 'all'])->name('customers.all');
Route::get('customers/map', [CustomerController::class, 'map'])->name('customers.map');
Route::apiResource('customers', CustomerController::class);
Route::get('customers/{customer}/summary', [CustomerController::class, 'summary'])->name('customers.summary');

Route::scopeBindings()->group(function (): void {
    Route::post('customers/{customer}/contacts', [CustomerContactController::class, 'store'])->name('customers.contacts.store');
    Route::put('customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'update'])->name('customers.contacts.update');
    Route::delete('customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'destroy'])->name('customers.contacts.destroy');

    Route::get('customers/{customer}/communications', [CustomerCommunicationController::class, 'index'])->name('customers.communications.index');
    Route::post('customers/{customer}/communications', [CustomerCommunicationController::class, 'store'])->name('customers.communications.store');
    Route::put('customers/{customer}/communications/{communication}', [CustomerCommunicationController::class, 'update'])->name('customers.communications.update');
    Route::delete('customers/{customer}/communications/{communication}', [CustomerCommunicationController::class, 'destroy'])->name('customers.communications.destroy');
});

// -- Pipeline ------------------------------------------------------------------
Route::post('pipeline-stages/reorder', [PipelineStageController::class, 'reorder'])->name('pipeline-stages.reorder');
Route::apiResource('pipeline-stages', PipelineStageController::class)->parameters(['pipeline-stages' => 'pipelineStage']);

Route::apiResource('leads', LeadController::class);
Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');
Route::post('leads/{lead}/assign', [LeadController::class, 'assign'])->name('leads.assign');

Route::get('opportunities/board', [OpportunityController::class, 'board'])->name('opportunities.board');
Route::apiResource('opportunities', OpportunityController::class);
Route::patch('opportunities/{opportunity}/stage', [OpportunityController::class, 'moveStage'])->name('opportunities.stage');
Route::get('sales/pipeline-summary', [OpportunityController::class, 'pipelineSummary'])->name('sales.pipeline-summary');

// -- Quotations ------------------------------------------------------------------
Route::apiResource('quotations', QuotationController::class);
Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
Route::post('quotations/{quotation}/accept', [QuotationController::class, 'accept'])->name('quotations.accept');
Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');

// -- Sales orders ---------------------------------------------------------------
Route::apiResource('sales-orders', SalesOrderController::class)->parameters(['sales-orders' => 'salesOrder']);
Route::post('sales-orders/{salesOrder}/confirm', [SalesOrderController::class, 'confirm'])->name('sales-orders.confirm');
Route::post('sales-orders/{salesOrder}/approve', [SalesOrderController::class, 'approve'])
    ->middleware('permission:sales_orders.approve')->name('sales-orders.approve');
Route::post('sales-orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])->name('sales-orders.cancel');
Route::post('sales-orders/{salesOrder}/deliveries', [DeliveryController::class, 'store'])->name('sales-orders.deliveries.store');

// -- Deliveries ------------------------------------------------------------------
Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
Route::post('deliveries/{delivery}/ship', [DeliveryController::class, 'ship'])->name('deliveries.ship');
Route::post('deliveries/{delivery}/deliver', [DeliveryController::class, 'deliver'])->name('deliveries.deliver');
Route::post('deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])->name('deliveries.cancel');

// -- Reports ----------------------------------------------------------------------
Route::prefix('sales/reports')->name('sales.reports.')->middleware('permission:reports.view')->group(function (): void {
    Route::get('performance', [SalesReportController::class, 'performance'])->name('performance');
    Route::get('pipeline', [SalesReportController::class, 'pipeline'])->name('pipeline');
});
