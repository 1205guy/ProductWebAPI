<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateProductRequest;
use App\Http\Requests\Api\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    /**
     * 許可されているソートカラムのリスト
     */
    private const ALLOWED_SORT_COLUMNS = [
        'id',
        'name',
        'price',
        'stock',
        'created_at',
        'updated_at',
    ];

    /**
     * 商品一覧を取得
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        try {
            $query = Product::query();

            // 検索条件の追加
            if ($request->has('name')) {
                $query->where('name', 'like', '%'.$request->input('name').'%');
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // ソート条件の追加
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            // 不正なソートカラムのチェック
            if (! in_array($sortBy, self::ALLOWED_SORT_COLUMNS, true)) {
                throw new \InvalidArgumentException("Invalid sort column: {$sortBy}");
            }

            $query->orderBy($sortBy, $sortOrder);

            // ページネーション
            $products = $query->paginate($request->input('per_page', 15));

            return ProductResource::collection($products);
        } catch (\InvalidArgumentException $e) {
            abort(Response::HTTP_BAD_REQUEST, $e->getMessage());
        } catch (\Exception $e) {
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'Failed to fetch products.');
        }
    }

    /**
     * 商品を作成
     */
    public function store(CreateProductRequest $request): JsonResponse
    {
        try {
            $product = Product::create($request->validated());

            return response()->json([
                'message' => 'Product created successfully',
                'data' => new ProductResource($product),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 商品を更新
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);

            $product->update($request->validated());

            return response()->json([
                'message' => 'Product updated successfully',
                'data' => new ProductResource($product),
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 商品を取得
     */
    public function show(int $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);

            return response()->json([
                'data' => new ProductResource($product),
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch product',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 商品を削除
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            // withTrashedを追加して、削除済みのレコードも検索対象に含める
            $product = Product::withTrashed()->findOrFail($id);

            if ($product->trashed()) {
                return response()->json([
                    'message' => 'Product is already deleted',
                ], Response::HTTP_BAD_REQUEST);
            }

            $product->delete();

            return response()->json([
                'message' => 'Product deleted successfully',
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete product',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 商品を強制的に削除
     */
    public function forceDestroy(int $id): JsonResponse
    {
        try {
            $product = Product::withTrashed()->findOrFail($id);

            $product->forceDelete();

            return response()->json([
                'message' => 'Product permanently deleted successfully',
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to permanently delete product',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 削除済み商品の復元
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $product = Product::withTrashed()->findOrFail($id);

            if (! $product->trashed()) {
                return response()->json([
                    'message' => 'Product is not deleted',
                ], Response::HTTP_BAD_REQUEST);
            }

            $product->restore();

            return response()->json([
                'message' => 'Product restored successfully',
                'data' => new ProductResource($product),
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to restore product',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
