<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Simplified Chinese messages for the validation rules used by this
    | application. Keys mirror Laravel's English validation.php; rules missing
    | here fall back to the English file.
    |
    */

    'accepted' => '必须接受 :attribute。',
    'array' => ':attribute 必须是数组。',
    'boolean' => ':attribute 必须为真或假。',
    'confirmed' => ':attribute 两次输入不一致。',
    'current_password' => '密码不正确。',
    'date' => ':attribute 必须是有效的日期。',
    'decimal' => ':attribute 必须有 :decimal 位小数。',
    'different' => ':attribute 和 :other 必须不同。',
    'email' => ':attribute 必须是有效的电子邮箱地址。',
    'exists' => '所选的 :attribute 无效。',
    'in' => '所选的 :attribute 无效。',
    'integer' => ':attribute 必须是整数。',
    'lowercase' => ':attribute 必须为小写。',
    'max' => [
        'array' => ':attribute 不能超过 :max 项。',
        'file' => ':attribute 不能大于 :max KB。',
        'numeric' => ':attribute 不能大于 :max。',
        'string' => ':attribute 不能超过 :max 个字符。',
    ],
    'min' => [
        'array' => ':attribute 至少需要 :min 项。',
        'file' => ':attribute 至少需要 :min KB。',
        'numeric' => ':attribute 不能小于 :min。',
        'string' => ':attribute 至少需要 :min 个字符。',
    ],
    'numeric' => ':attribute 必须是数字。',
    'password' => [
        'letters' => ':attribute 必须至少包含一个字母。',
        'mixed' => ':attribute 必须至少包含一个大写字母和一个小写字母。',
        'numbers' => ':attribute 必须至少包含一个数字。',
        'symbols' => ':attribute 必须至少包含一个符号。',
        'uncompromised' => '提供的 :attribute 已出现在数据泄露中，请更换其他 :attribute。',
    ],
    'regex' => ':attribute 格式无效。',
    'required' => ':attribute 为必填项。',
    'same' => ':attribute 必须与 :other 一致。',
    'string' => ':attribute 必须是字符串。',
    'unique' => ':attribute 已被占用。',
    'url' => ':attribute 必须是有效的 URL。',

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
        'category' => '分类',
        'code' => '验证码',
        'current_password' => '当前密码',
        'email' => '电子邮箱',
        'level' => '级别',
        'locale' => '语言',
        'name' => '名称',
        'password' => '密码',
        'password_confirmation' => '确认密码',
        'price' => '价格',
        'recovery_code' => '恢复代码',
        'search' => '搜索',
        'sort' => '排序',
    ],

];
