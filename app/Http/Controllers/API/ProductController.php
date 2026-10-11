<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['search' => 'sometimes|string|max:255', 'with' => 'sometimes|in:category', 'sort' => 'sometimes|in:price,-price', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100']);
        $query = Product::query()->when($data['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'));
        if (isset($data['with'])) {
            $query->with('category');
        }
        if (isset($data['sort'])) {
            $query->orderBy('price', $data['sort'] === '-price' ? 'desc' : 'asc');
        }

        return $query->orderBy('id')->simplePaginate($data['per_page'] ?? 15)->withQueryString();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:products,name', 'description' => 'required|string|max:255',
            'price' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2', 'currency' => 'required|string|size:3|regex:/^[A-Z]{3}$/',
            'display_image_url' => 'required|url:http,https|max:2048', 'category_id' => 'required|integer|exists:categories,id',
        ]);

        return response()->json(Product::create($data), 201);
    }

    public function show(Request $request, Product $product)
    {
        $data = $request->validate(['with' => 'sometimes|in:category']);
        if (isset($data['with'])) {
            $product->load('category');
        }

        return $product;
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('products', 'name')->ignore($product)],
            'description' => 'sometimes|required|string|max:255', 'price' => 'sometimes|required|numeric|min:0|max:9999999999.99|decimal:0,2',
            'currency' => 'sometimes|required|string|size:3|regex:/^[A-Z]{3}$/', 'display_image_url' => 'sometimes|required|url:http,https|max:2048',
            'category_id' => 'sometimes|required|integer|exists:categories,id',
        ]);
        $product->update($data);

        return $product;
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->noContent();
    }
}
