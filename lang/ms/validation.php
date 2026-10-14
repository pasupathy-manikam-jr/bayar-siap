<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Malay messages for the validation rules used by this application.
    | Keys mirror Laravel's English validation.php; rules missing here fall
    | back to the English file.
    |
    */

    'accepted' => 'Medan :attribute mesti diterima.',
    'array' => 'Medan :attribute mesti berupa tatasusunan.',
    'boolean' => 'Medan :attribute mesti benar atau palsu.',
    'confirmed' => 'Pengesahan medan :attribute tidak sepadan.',
    'current_password' => 'Kata laluan tidak betul.',
    'date' => 'Medan :attribute mesti tarikh yang sah.',
    'decimal' => 'Medan :attribute mesti mempunyai :decimal tempat perpuluhan.',
    'different' => 'Medan :attribute dan :other mesti berbeza.',
    'email' => 'Medan :attribute mesti alamat e-mel yang sah.',
    'exists' => ':attribute yang dipilih tidak sah.',
    'in' => ':attribute yang dipilih tidak sah.',
    'integer' => 'Medan :attribute mesti integer.',
    'lowercase' => 'Medan :attribute mesti dalam huruf kecil.',
    'max' => [
        'array' => 'Medan :attribute tidak boleh mempunyai lebih daripada :max item.',
        'file' => 'Medan :attribute tidak boleh melebihi :max kilobait.',
        'numeric' => 'Medan :attribute tidak boleh melebihi :max.',
        'string' => 'Medan :attribute tidak boleh melebihi :max aksara.',
    ],
    'min' => [
        'array' => 'Medan :attribute mesti mempunyai sekurang-kurangnya :min item.',
        'file' => 'Medan :attribute mesti sekurang-kurangnya :min kilobait.',
        'numeric' => 'Medan :attribute mesti sekurang-kurangnya :min.',
        'string' => 'Medan :attribute mesti sekurang-kurangnya :min aksara.',
    ],
    'numeric' => 'Medan :attribute mesti nombor.',
    'password' => [
        'letters' => 'Medan :attribute mesti mengandungi sekurang-kurangnya satu huruf.',
        'mixed' => 'Medan :attribute mesti mengandungi sekurang-kurangnya satu huruf besar dan satu huruf kecil.',
        'numbers' => 'Medan :attribute mesti mengandungi sekurang-kurangnya satu nombor.',
        'symbols' => 'Medan :attribute mesti mengandungi sekurang-kurangnya satu simbol.',
        'uncompromised' => ':attribute yang diberikan telah muncul dalam kebocoran data. Sila pilih :attribute yang lain.',
    ],
    'regex' => 'Format medan :attribute tidak sah.',
    'required' => 'Medan :attribute diperlukan.',
    'same' => 'Medan :attribute mesti sepadan dengan :other.',
    'string' => 'Medan :attribute mesti rentetan teks.',
    'unique' => ':attribute telah pun digunakan.',
    'url' => 'Medan :attribute mesti URL yang sah.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'category' => 'kategori',
        'code' => 'kod',
        'current_password' => 'kata laluan semasa',
        'email' => 'e-mel',
        'level' => 'tahap',
        'locale' => 'bahasa',
        'name' => 'nama',
        'password' => 'kata laluan',
        'password_confirmation' => 'pengesahan kata laluan',
        'price' => 'harga',
        'recovery_code' => 'kod pemulihan',
        'search' => 'carian',
        'sort' => 'susunan',
    ],

];
