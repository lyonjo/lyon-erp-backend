<?php

namespace App\Http\Requests\Rent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CarRentalPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        $id = $this->route('carRentalPrice');
        $id = $id instanceof \Illuminate\Database\Eloquent\Model ? $id->getKey() : $id;
        return [
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')],
            'model_id' => ['required', 'integer', Rule::exists('models', 'id')->where('brand_id', $this->input('brand_id'))],
            'model_year' => ['required', 'integer', 'between:1950,2100'],
            'client_type' => ['required', Rule::in(['customer', 'company'])],
            'company_id' => ['nullable', 'required_if:client_type,company', 'prohibited_if:client_type,customer', 'integer', Rule::exists('customer_companies', 'id')],
            'periods' => ['required', 'array', 'min:1', 'max:100'],
            'periods.*.start_date' => ['required', 'date_format:Y-m-d'],
            'periods.*.end_date' => ['required', 'date_format:Y-m-d'],
            'periods.*.intervals' => ['required', 'array', 'min:1', 'max:100'],
            'periods.*.intervals.*.start' => ['required', 'integer', 'min:1'],
            'periods.*.intervals.*.end' => ['required', 'integer', 'min:2'],
            'periods.*.intervals.*.price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'main_image' => [$this->route('carRentalPrice') ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'gallery' => ['sometimes', 'array', 'max:20'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'delete_image_ids' => ['sometimes', 'array'],
            'delete_image_ids.*' => ['integer', 'distinct'],
        ];
    }
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $record = $this->route('carRentalPrice');
            if ($record && !$this->hasFile('main_image')) {
                $record = $record instanceof \Illuminate\Database\Eloquent\Model ? $record : \App\Models\Rent\CarRentalPrice::find($record);
                if ($record) {
                    $main = $record->images()->where('type', 'main')->first();
                    if (!$main || in_array($main->id, array_map('intval', $this->input('delete_image_ids', [])))) $v->errors()->add('main_image', 'A main image is required.');
                }
            }
            $periods = $this->input('periods', []);
            if (!is_array($periods)) return;
            foreach ($periods as $i => $p) {
                if (!is_array($p)) continue;
                $from = $p['start_date'] ?? null;
                $to = $p['end_date'] ?? null;
                if ($from && $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$to) && $from > $to) $v->errors()->add("periods.$i.end_date", 'End date must not precede start date.');
                $ranges = [];
                foreach (($p['intervals'] ?? []) as $j => $r) {
                    if (!is_array($r)) continue;
                    $a = $r['start'] ?? null;
                    $b = $r['end'] ?? null;
                    if (is_numeric($a) && is_numeric($b)) {
                        if ((int)$a >= (int)$b) $v->errors()->add("periods.$i.intervals.$j.end", 'End day must be greater than start day.');
                        foreach ($ranges as [$x, $y]) if ((int)$a <= $y && (int)$b >= $x) {
                            $v->errors()->add("periods.$i.intervals.$j.start", 'Price intervals in a period must not overlap.');
                            break;
                        }
                        $ranges[] = [(int)$a, (int)$b];
                    }
                }
            }
            for ($i = 0; $i < count($periods); $i++) for ($j = $i + 1; $j < count($periods); $j++) {
                $a = $periods[$i] ?? [];
                $b = $periods[$j] ?? [];
                if (is_array($a) && is_array($b) && !empty($a['start_date']) && !empty($a['end_date']) && !empty($b['start_date']) && !empty($b['end_date']) && $a['start_date'] <= $b['end_date'] && $b['start_date'] <= $a['end_date']) $v->errors()->add("periods.$j.start_date", 'Pricing periods must not overlap.');
            }
            // Strictly reject combinations that do not exist in the vehicles catalog.
            if ($this->filled('brand_id') && $this->filled('model_id') && $this->filled('model_year')) {
                $found = DB::table('vehicles')->where('brand_id', $this->input('brand_id'))->where('model_id', $this->input('model_id'))->where('model_year', $this->input('model_year'))->exists();
                if (!$found) $v->errors()->add('model_year', 'Select a car from the vehicle catalog.');
            }
        });
    }
}
