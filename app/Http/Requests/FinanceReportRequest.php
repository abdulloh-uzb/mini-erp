<?php

namespace App\Http\Requests;

/**
 * The date range of the Foyda va zarar and Pul oqimi reports: the month so far by default.
 */
class FinanceReportRequest extends SalesReportRequest
{
    /**
     * Get the first day of the range, the first of the month when none was asked for.
     */
    public function from(): string
    {
        return $this->validated('from') ?? today()->startOfMonth()->toDateString();
    }
}
