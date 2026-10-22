<?php

namespace App\Http\Requests\Farm;

use App\Enums\FollowUpOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFollowUpRequest extends FormRequest
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
            'observed_on' => ['required', 'date'],
            'outcome' => ['required', Rule::enum(FollowUpOutcome::class)],
            'notes' => ['nullable', 'string'],
            'next_follow_up_on' => ['nullable', 'date', 'after_or_equal:observed_on'],
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
            'observed_on' => 'วันที่ดูซ้ำ',
            'outcome' => 'ผลการดูซ้ำ',
            'notes' => 'หมายเหตุ',
            'next_follow_up_on' => 'วันดูซ้ำครั้งถัดไป',
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
            'outcome.required' => 'เลือกผลการดูซ้ำ',
            'next_follow_up_on.after_or_equal' => 'วันดูซ้ำครั้งถัดไปต้องไม่ก่อนวันที่ดูซ้ำ',
            'photos.max' => 'แนบรูปได้ไม่เกิน 3 รูป',
            'photos.*.image' => 'ไฟล์ที่แนบต้องเป็นรูปภาพ',
            'photos.*.max' => 'รูปแต่ละรูปต้องมีขนาดไม่เกิน 8 MB',
            'photos.*.uploaded' => 'อัปโหลดรูปไม่สำเร็จ ลองใหม่อีกครั้ง',
        ];
    }
}
