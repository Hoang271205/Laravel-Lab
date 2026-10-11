<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['search' => 'sometimes|string|max:255', 'page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100']);

        return Category::query()->when($data['search'] ?? null, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->orderBy('id')->simplePaginate($data['per_page'] ?? 15)->withQueryString();
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:categories,name', 'description' => 'required|string|max:255']);

        return response()->json(Category::create($data), 201);
    }

    public function show(Category $category)
    {
        return $category;
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category)], 'description' => 'sometimes|required|string|max:255']);
        $category->update($data);

        return $category;
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return response()->json(['message' => 'Category still contains products. Delete or reassign them first.'], 409);
        }
        $category->delete();

        return response()->noContent();
    }
}
