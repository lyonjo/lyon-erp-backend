<?php

namespace App\Http\Controllers\Rent;

use App\Models\Rent\Refund;

class RefundController extends FinanceAdjustmentController
{
    protected function model(): string
    {
        return Refund::class;
    }
    protected function kind(): string
    {
        return 'refund';
    }
}
