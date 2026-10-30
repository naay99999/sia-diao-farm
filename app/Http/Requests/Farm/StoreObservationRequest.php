<?php

namespace App\Http\Requests\Farm;

use App\Enums\FindingKind;
use App\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'finding_id' => ['nullable', 'required_without:finding_name', 'integer', 'exists:findings,id'],
            'finding_name' => ['nullable', 'required_without:finding_id', 'string', 'max:255'],
            'kind' => ['nullable', 'required_with:finding_name', Rule::enum(FindingKind::class)],
            'observed_on' => ['required', 'date'],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'follow_up_on' => ['nullable', 'date', 'after_or_equal:observed_on'],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'finding_id' => 'สิ่งที่พบ',
            'finding_name' => 'ชื่อสิ่งที่พบ',
            'kind' => 'ประเภท',
            'observed_on' => 'วันที่พบ',
            'severity' => 'ความรุนแรง',
            'notes' => 'หมายเหตุ',
            'follow_up_on' => 'วันดูซ้ำ',
            'photos' => 'รูปภาพ',
            'photos.*' => 'รูปภาพ',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'finding_id.required_without' => 'เลือกสิ่งที่พบ',
            'finding_name.required_without' => 'เลือกสิ่งที่พบ',
            'kind.required_with' => 'เลือกว่าเป็นปัญหาหรือระยะการเติบโต',
            'follow_up_on.after_or_equal' => 'วันดูซ้ำต้องไม่ก่อนวันที่พบ',
            'photos.max' => 'แนบรูปได้ไม่เกิน 3 รูป',
            'photos.*.image' => 'ไฟล์ที่แนบต้องเป็นรูปภาพ',
            'photos.*.max' => 'รูปแต่ละรูปต้องมีขนาดไม่เกิน 8 MB',
            'photos.*.uploaded' => 'อัปโหลดรูปไม่สำเร็จ ลองใหม่อีกครั้ง',
        ];
    }
}
