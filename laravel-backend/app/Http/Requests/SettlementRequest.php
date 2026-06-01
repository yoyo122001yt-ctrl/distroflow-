<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actual_cash' => 'required|numeric|min:0',
            'inventory' => 'required|array',
            'inventory.*.batch_id' => 'required|exists:batches,id',
            'inventory.*.quantity' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ];
    }
}
