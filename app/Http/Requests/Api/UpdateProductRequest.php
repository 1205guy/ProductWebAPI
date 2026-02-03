<?php

namespace App\Http\Requests\Api;

class UpdateProductRequest extends CreateProductRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        // 親クラスのバリデーションルールに'somtetimes'を追加する
        return array_map(function ($rules) {
            return array_merge(['sometimes'], $rules);
        }, $rules);
    }
}
