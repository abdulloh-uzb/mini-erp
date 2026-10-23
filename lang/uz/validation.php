<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (Uzbek, Latin script)
    |--------------------------------------------------------------------------
    |
    | Rules missing here fall back to the framework's English lines.
    |
    */

    'accepted' => ':attribute qabul qilinishi kerak.',
    'after' => ':attribute :date dan keyingi sana bo\'lishi kerak.',
    'after_or_equal' => ':attribute :date yoki undan keyingi sana bo\'lishi kerak.',
    'alpha' => ':attribute faqat harflardan iborat bo\'lishi kerak.',
    'alpha_dash' => ':attribute faqat harf, raqam, chiziqcha va pastki chiziqdan iborat bo\'lishi kerak.',
    'alpha_num' => ':attribute faqat harf va raqamlardan iborat bo\'lishi kerak.',
    'array' => ':attribute ro\'yxat bo\'lishi kerak.',
    'before' => ':attribute :date dan oldingi sana bo\'lishi kerak.',
    'before_or_equal' => ':attribute :date yoki undan oldingi sana bo\'lishi kerak.',
    'between' => [
        'array' => ':attribute :min tadan :max tagacha elementdan iborat bo\'lishi kerak.',
        'file' => ':attribute hajmi :min dan :max kilobaytgacha bo\'lishi kerak.',
        'numeric' => ':attribute :min dan :max gacha bo\'lishi kerak.',
        'string' => ':attribute :min tadan :max tagacha belgidan iborat bo\'lishi kerak.',
    ],
    'boolean' => ':attribute ha yoki yo\'q bo\'lishi kerak.',
    'confirmed' => ':attribute tasdig\'i mos kelmadi.',
    'current_password' => 'Parol noto\'g\'ri.',
    'date' => ':attribute to\'g\'ri sana emas.',
    'date_equals' => ':attribute :date ga teng sana bo\'lishi kerak.',
    'date_format' => ':attribute :format ko\'rinishiga mos emas.',
    'decimal' => ':attribute :decimal xonali kasr son bo\'lishi kerak.',
    'different' => ':attribute va :other har xil bo\'lishi kerak.',
    'digits' => ':attribute :digits xonali bo\'lishi kerak.',
    'digits_between' => ':attribute :min dan :max xonagacha bo\'lishi kerak.',
    'distinct' => ':attribute takrorlangan qiymatga ega.',
    'email' => ':attribute to\'g\'ri elektron pochta manzili bo\'lishi kerak.',
    'enum' => 'Tanlangan :attribute noto\'g\'ri.',
    'exists' => 'Tanlangan :attribute topilmadi.',
    'file' => ':attribute fayl bo\'lishi kerak.',
    'filled' => ':attribute to\'ldirilishi kerak.',
    'gt' => [
        'array' => ':attribute :value tadan ko\'p elementga ega bo\'lishi kerak.',
        'file' => ':attribute hajmi :value kilobaytdan katta bo\'lishi kerak.',
        'numeric' => ':attribute :value dan katta bo\'lishi kerak.',
        'string' => ':attribute :value tadan ko\'p belgidan iborat bo\'lishi kerak.',
    ],
    'gte' => [
        'array' => ':attribute kamida :value ta elementga ega bo\'lishi kerak.',
        'file' => ':attribute hajmi kamida :value kilobayt bo\'lishi kerak.',
        'numeric' => ':attribute :value dan kichik bo\'lmasligi kerak.',
        'string' => ':attribute kamida :value ta belgidan iborat bo\'lishi kerak.',
    ],
    'image' => ':attribute rasm bo\'lishi kerak.',
    'in' => 'Tanlangan :attribute noto\'g\'ri.',
    'integer' => ':attribute butun son bo\'lishi kerak.',
    'json' => ':attribute JSON qatori bo\'lishi kerak.',
    'lt' => [
        'array' => ':attribute :value tadan kam elementga ega bo\'lishi kerak.',
        'file' => ':attribute hajmi :value kilobaytdan kichik bo\'lishi kerak.',
        'numeric' => ':attribute :value dan kichik bo\'lishi kerak.',
        'string' => ':attribute :value tadan kam belgidan iborat bo\'lishi kerak.',
    ],
    'lte' => [
        'array' => ':attribute ko\'pi bilan :value ta elementga ega bo\'lishi kerak.',
        'file' => ':attribute hajmi ko\'pi bilan :value kilobayt bo\'lishi kerak.',
        'numeric' => ':attribute :value dan katta bo\'lmasligi kerak.',
        'string' => ':attribute ko\'pi bilan :value ta belgidan iborat bo\'lishi kerak.',
    ],
    'max' => [
        'array' => ':attribute ko\'pi bilan :max ta elementga ega bo\'lishi kerak.',
        'file' => ':attribute hajmi ko\'pi bilan :max kilobayt bo\'lishi kerak.',
        'numeric' => ':attribute :max dan katta bo\'lmasligi kerak.',
        'string' => ':attribute ko\'pi bilan :max ta belgidan iborat bo\'lishi kerak.',
    ],
    'mimes' => ':attribute quyidagi turdagi fayl bo\'lishi kerak: :values.',
    'mimetypes' => ':attribute quyidagi turdagi fayl bo\'lishi kerak: :values.',
    'min' => [
        'array' => ':attribute kamida :min ta elementga ega bo\'lishi kerak.',
        'file' => ':attribute hajmi kamida :min kilobayt bo\'lishi kerak.',
        'numeric' => ':attribute kamida :min bo\'lishi kerak.',
        'string' => ':attribute kamida :min ta belgidan iborat bo\'lishi kerak.',
    ],
    'multiple_of' => ':attribute :value ga karrali bo\'lishi kerak.',
    'not_in' => 'Tanlangan :attribute noto\'g\'ri.',
    'not_regex' => ':attribute ko\'rinishi noto\'g\'ri.',
    'numeric' => ':attribute son bo\'lishi kerak.',
    'password' => [
        'letters' => ':attribute kamida bitta harfga ega bo\'lishi kerak.',
        'mixed' => ':attribute kamida bitta katta va bitta kichik harfga ega bo\'lishi kerak.',
        'numbers' => ':attribute kamida bitta raqamga ega bo\'lishi kerak.',
        'symbols' => ':attribute kamida bitta belgiga ega bo\'lishi kerak.',
        'uncompromised' => 'Bu :attribute ma\'lumotlar sizib chiqishida uchragan. Boshqa :attribute tanlang.',
    ],
    'present' => ':attribute mavjud bo\'lishi kerak.',
    'prohibited' => ':attribute kiritilmasligi kerak.',
    'regex' => ':attribute ko\'rinishi noto\'g\'ri.',
    'required' => ':attribute kiritilishi shart.',
    'required_if' => ':other :value bo\'lganda :attribute kiritilishi shart.',
    'required_unless' => ':other :values bo\'lmasa, :attribute kiritilishi shart.',
    'required_with' => ':values kiritilganda :attribute ham kiritilishi shart.',
    'required_with_all' => ':values kiritilganda :attribute ham kiritilishi shart.',
    'required_without' => ':values kiritilmaganda :attribute kiritilishi shart.',
    'required_without_all' => ':values kiritilmaganda :attribute kiritilishi shart.',
    'same' => ':attribute va :other mos kelishi kerak.',
    'size' => [
        'array' => ':attribute :size ta elementdan iborat bo\'lishi kerak.',
        'file' => ':attribute hajmi :size kilobayt bo\'lishi kerak.',
        'numeric' => ':attribute :size ga teng bo\'lishi kerak.',
        'string' => ':attribute :size ta belgidan iborat bo\'lishi kerak.',
    ],
    'starts_with' => ':attribute quyidagilardan biri bilan boshlanishi kerak: :values.',
    'string' => ':attribute matn bo\'lishi kerak.',
    'unique' => 'Bu :attribute allaqachon band.',
    'uploaded' => ':attribute yuklanmadi.',
    'url' => ':attribute to\'g\'ri havola bo\'lishi kerak.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'phone' => [
            'unique' => 'Bu telefon raqami allaqachon ro\'yxatdan o\'tgan.',
            'regex' => 'Telefon raqami +998 XX XXX XX XX ko\'rinishida bo\'lishi kerak.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'ism',
        'phone' => 'telefon raqami',
        'password' => 'parol',
        'password_confirmation' => 'parol tasdig\'i',
        'current_password' => 'joriy parol',
        'store_name' => 'do\'kon nomi',
        'code' => 'kod',
        'recovery_code' => 'tiklash kodi',
    ],

];
