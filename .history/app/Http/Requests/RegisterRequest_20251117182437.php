<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => [
                'required',
                Rule::in([
                    'super_admin',       
                    'event_organizer',    // Added this
                    'participant',
                    'author',
                    'scientific_committee',
                    'guest_speaker',
                    'workshop_facilitator'
                ])
            ],
            'phone' => 'nullable|string|max:20',
            'institution' => 'nullable|string|max:255',
            'research_field' => 'nullable|string|max:255',
            'biography' => 'nullable|string|max:1000',
            'country' => 'nullable|string|max:100',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'الاسم مطلوب',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'البريد الإلكتروني غير صالح',
            'email.unique' => 'البريد الإلكتروني مسجل مسبقاً',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            'role.required' => 'يجب اختيار الدور',
            'role.in' => 'الدور المختار غير صالح',
            'phone.max' => 'رقم الهاتف طويل جداً',
            'biography.max' => 'السيرة الذاتية يجب ألا تتجاوز 1000 حرف',
        ];
    }
}