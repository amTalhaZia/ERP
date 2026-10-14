<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Payroll\PayslipResource;
use App\Models\Payroll\Payslip;
use App\Services\Payroll\PayslipDocuments;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Payslip detail for payroll staff (payroll.view). An employee may also open
 * their own released payslip here; anyone else's is 403.
 */
class PayslipController extends ApiController
{
    public function show(Payslip $payslip): PayslipResource
    {
        Gate::authorize('view', $payslip);

        return new PayslipResource($payslip->load(['lines', 'run']));
    }

    public function pdf(Payslip $payslip, PayslipDocuments $documents): Response
    {
        Gate::authorize('view', $payslip);

        return $documents->pdf($payslip);
    }
}
