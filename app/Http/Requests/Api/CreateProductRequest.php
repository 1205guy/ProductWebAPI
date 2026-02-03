<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 認可
        return true;
    }

    // バリデーションルール
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    // エラーメッセージの設定
    public function messages(): array
    {
        return [
            'name.required' => '商品名は必須です',
            'name.max' => '商品名は255文字以内で入力してください',
            'description.max' => '商品説明は1000文字以内で入力してください',
            'price.required' => '価格は必須です',
            'price.integer' => '価格は整数で入力してください',
            'price.min' => '価格は0以上で入力してください',
            'stock.required' => '在庫数は必須です',
            'stock.integer' => '在庫数は整数で入力してください',
            'stock.min' => '在庫数は0以上で入力してください',
            'is_active.boolean' => '商品状態は真偽値で入力してください',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}
