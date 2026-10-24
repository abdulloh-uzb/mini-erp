<?php

namespace App\Http\Requests;

use App\Enums\DashboardPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request: a period, and the days of a custom one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::enum(DashboardPeriod::class)],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * Get the asked-for period, today when none was asked for.
     */
    public function period(): DashboardPeriod
    {
        return DashboardPeriod::tryFrom((string) $this->validated('period')) ?? DashboardPeriod::Day;
    }

    /**
     * Get the period's first and last day ("Y-m-d").
     *
     * @return array{0: string, 1: string}
     */
    public function range(): array
    {
        return $this->period()->range($this->validated('from'), $this->validated('to'));
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from.required_if' => __('Boshlanish sanasini tanlang.'),
            'to.required_if' => __('Tugash sanasini tanlang.'),
            'from.date_format' => __('Sanani to\'g\'ri kiriting.'),
            'to.date_format' => __('Sanani to\'g\'ri kiriting.'),
            'to.after_or_equal' => __('Tugash sanasi boshlanish sanasidan oldin bo\'lmasin.'),
        ];
    }
}
