<?php

/*
|--------------------------------------------------------------------------
| Validation Language Lines (uk)
|--------------------------------------------------------------------------
|
| Only the rules the admin area actually uses are translated; any other
| key falls back to lang/en/validation.php. When a new rule starts being
| used, copy its line from the English file and translate it here.
|
*/

return [

    'array' => 'Поле :attribute має бути масивом.',
    'boolean' => 'Поле :attribute має бути true або false.',
    'confirmed' => 'Підтвердження поля :attribute не збігається.',
    'email' => 'Поле :attribute має бути дійсною адресою електронної пошти.',
    'exists' => 'Вибране значення для :attribute недійсне.',
    'in' => 'Вибране значення для :attribute недійсне.',
    'integer' => 'Поле :attribute має бути цілим числом.',
    'max' => [
        'array' => 'Поле :attribute не має містити більше ніж :max елементів.',
        'file' => 'Поле :attribute не має перевищувати :max КБ.',
        'numeric' => 'Поле :attribute не має бути більшим за :max.',
        'string' => 'Поле :attribute не має перевищувати :max символів.',
    ],
    'min' => [
        'array' => 'Поле :attribute має містити щонайменше :min елементів.',
        'file' => 'Поле :attribute має бути не меншим за :min КБ.',
        'numeric' => 'Поле :attribute має бути не меншим за :min.',
        'string' => 'Поле :attribute має містити щонайменше :min символів.',
    ],
    'not_in' => 'Вибране значення для :attribute недійсне.',
    'regex' => 'Поле :attribute має недопустимий формат.',
    'required' => 'Поле :attribute обов’язкове.',
    'string' => 'Поле :attribute має бути рядком.',
    'unique' => 'Таке значення :attribute вже використовується.',

    'attributes' => [
        'name' => 'назва',
        'key' => 'ключ',
        'slug' => 'slug',
        'parent_type' => 'батьківський тип',
        'description' => 'опис',
        'email' => 'електронна пошта',
        'password' => 'пароль',
        'locale' => 'мова',
    ],

];
