<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * 商品APIのテスト
 * 
 */
class ProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テストデータの作成
     */
    public function setUp(): void
    {
        parent::setUp();
        // テストデータの作成
        Product::factory(30)->create();
    }

    /**
     * 商品一覧を取得できるかのテスト
     */
    public function test_can_fetch_products_list(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'price',
                        'stock',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    /**
     * 商品名で検索できるかのテスト
     */
    public function test_can_fetch_products_with_search_params(): void
    {
        // 特定の名前を持つ商品を作成
        $searchName = 'テスト商品';
        Product::factory()->create(['name' => $searchName]);

        $response = $this->getJson("/api/products?name={$searchName}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $searchName]);
    }

    /**
     * アクティブな商品のみ取得できるかのテスト
     */
    public function test_can_fetch_active_products_only(): void
    {
        // アクティブな商品を作成
        Product::factory(5)->create(['is_active' => true]);
        // 非アクティブな商品を作成
        Product::factory(5)->create(['is_active' => false]);

        $response = $this->getJson('/api/products?is_active=true');

        $response->assertStatus(200);
        $products = $response->json('data');
        collect($products)->each(function ($product) {
            $this->assertTrue($product['is_active']);
        });
    }

    /**
     * 商品を価格でソートできるかのテスト
     */
    public function test_can_sort_products(): void
    {
        // 価格の異なる商品を作成
        Product::factory()->create(['price' => 1000]);
        Product::factory()->create(['price' => 2000]);
        Product::factory()->create(['price' => 3000]);

        $response = $this->getJson('/api/products?sort_by=price&sort_order=desc');

        $response->assertStatus(200);
        $products = $response->json('data');
        $this->assertTrue($products[0]['price'] > $products[1]['price']);
    }

    /**
     * ページネーションができるかのテスト
     */
    public function test_can_paginate_products(): void
    {
        $perPage = 5;
        $response = $this->getJson("/api/products?per_page={$perPage}");

        $response->assertStatus(200)
            ->assertJsonCount($perPage, 'data')
            ->assertJsonStructure([
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);
    }

    /**
     * 無効なソートパラメータのテスト
     */
    public function test_handles_invalid_sort_parameter(): void
    {
        $response = $this->getJson('/api/products?sort_by=invalid_column');

        $response->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonFragment([
                'message' => 'Invalid sort column: invalid_column'
            ]);
    }

    /**
     * 商品を作成できるかのテスト
     */
    public function test_can_create_product(): void
    {
        $productData = [
            'name' => 'テスト商品',
            'description' => '商品の説明文です',
            'price' => 1000,
            'stock' => 10,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/products', $productData);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'price',
                    'stock',
                    'is_active',
                    'created_at',
                    'updated_at',
                ]
            ]);

        $this->assertDatabaseHas('products', $productData);
    }

    /**
     * 無効なデータで商品を作成できないかのテスト
     */
    public function test_cannot_create_product_with_invalid_data(): void
    {
        $invalidData = [
            'name' => '', // 必須項目を空にする
            'price' => -1, // 負の値は不許可
            'stock' => 'invalid', // 数値以外は不許可
        ];

        $response = $this->postJson('/api/products', $invalidData);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'name',
                    'price',
                    'stock',
                ]
            ]);
    }

    /**
     * 商品を更新できるかのテスト
     */
    public function test_can_update_product(): void
    {
        $product = Product::factory()->create();

        $updateData = [
            'name' => '更新後の商品名',
            'price' => 2000,
        ];

        $response = $this->putJson("/api/products/{$product->id}", $updateData);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'price',
                    'stock',
                    'is_active',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJsonFragment([
                'name' => '更新後の商品名',
                'price' => 2000,
            ]);

        $this->assertDatabaseHas('products', $updateData);
    }

    /**
     * 無効なデータで商品を更新できないかのテスト
     */
    public function test_cannot_update_product_with_invalid_data(): void
    {
        $product = Product::factory()->create();

        $invalidData = [
            'name' => '', // 空の名前は不許可
            'price' => -1, // 負の価格は不許可
        ];

        $response = $this->putJson("/api/products/{$product->id}", $invalidData);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure([
                'message',
                'errors' => [
                    'name',
                    'price',
                ]
            ]);
    }

    /**
     * 存在しない商品を更新しようとした場合のテスト
     */
    public function test_cannot_update_non_existent_product(): void
    {
        $nonExistentId = 9999;

        $updateData = [
            'name' => '更新後の商品名',
            'price' => 2000,
        ];

        $response = $this->putJson("/api/products/{$nonExistentId}", $updateData);

        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJsonFragment([
                'message' => 'Product not found'
            ]);
    }

    /**
     * 商品を取得できるかのテスト
     */
    public function test_can_fetch_single_product(): void
    {
        $product = Product::factory()->create([
            'name' => 'テスト商品',
            'description' => '商品の説明文',
            'price' => 1000,
            'stock' => 10,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'price',
                    'stock',
                    'is_active',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJsonFragment([
                'name' => 'テスト商品',
                'description' => '商品の説明文',
                'price' => 1000,
                'stock' => 10,
                'is_active' => true,
            ]);
    }

    /**
     * 存在しない商品を取得しようとした場合のテスト
     */
    public function test_cannot_fetch_non_existent_product(): void
    {
        $nonExistentId = 9999;

        $response = $this->getJson("/api/products/{$nonExistentId}");

        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJsonFragment([
                'message' => 'Product not found'
            ]);
    }

    /**
     * 削除された商品を取得しようとした場合のテスト
     */
    public function test_cannot_fetch_deleted_product(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJsonFragment([
                'message' => 'Product not found'
            ]);
    }

    /**
     * 無効なID形式で商品を取得しようとした場合のテスト
     */
    public function test_returns_error_for_invalid_id_format(): void
    {
        $response = $this->getJson('/products/invalid-id');

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * 商品を論理削除できるかのテスト
     */
    public function test_can_soft_delete_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment([
                'message' => 'Product deleted successfully'
            ]);

        $this->assertSoftDeleted('products', [
            'id' => $product->id
        ]);
    }

    /**
     * 存在しない商品を削除しようとした場合のテスト
     */
    public function test_cannot_delete_non_existent_product(): void
    {
        $nonExistentId = 9999;

        $response = $this->deleteJson("/api/products/{$nonExistentId}");

        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJsonFragment([
                'message' => 'Product not found'
            ]);
    }

    /**
     * 削除済みの商品を削除しようとした場合のテスト
     */
    public function test_cannot_delete_already_deleted_product(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonFragment([
                'message' => 'Product is already deleted'
            ]);
    }

    /**
     * 商品を強制的に削除できるかのテスト
     */
    public function test_can_force_delete_product(): void
    {
        $product = Product::factory()->create();
        $product->delete(); // まずソフトデリート

        $response = $this->deleteJson("/api/products/{$product->id}/force");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment([
                'message' => 'Product permanently deleted successfully'
            ]);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id
        ]);
    }

    /**
     * 削除済みの商品を復元できるかのテスト
     */
    public function test_can_restore_deleted_product(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $response = $this->patchJson("/api/products/{$product->id}/restore");  // postJsonからpatchJsonに変更

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonFragment([
                'message' => 'Product restored successfully'
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'deleted_at' => null
        ]);
    }

    /**
     * 削除済みでない商品を復元しようとした場合のテスト
     */
    public function test_cannot_restore_non_deleted_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->patchJson("/api/products/{$product->id}/restore");  // postJsonからpatchJsonに変更

        $response->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonFragment([
                'message' => 'Product is not deleted'
            ]);
    }
}
