<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesReportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * Get the first day of the range, today when none was asked for.
     */
    public function from(): string
    {
        return $this->validated('from') ?? $this->to();
    }

    /**
     * Get the last day of the range, today when none was asked for.
     */
    public function to(): string
    {
        return $this->validated('to') ?? today()->toDateString();
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.date_format' => __('Sanani to\'g\'ri kiriting.'),
            'to.date_format' => __('Sanani to\'g\'ri kiriting.'),
            'to.after_or_equal' => __('Tugash sanasi boshlanish sanasidan oldin bo\'lmasin.'),
        ];
    }
}
