<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Cache::remember('products_all', 60, function () {
            return Product::all();
        });

        return response()->json($products);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'stock' => 'required|integer|min:0',
                'sku' => 'required|string|unique:products,sku',
                'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
                'other_images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors(),
                    'error_type' => 'validation_error'
                ]);
            }

            $imagePath = null;
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('products', 'public');
                $imagePath = asset('storage/' . $path);
            }

            $otherImagesPaths = [];
            if ($request->hasFile('other_images')) {
                foreach ($request->file('other_images') as $img) {
                    $path = $img->store('products', 'public');
                    $otherImagesPaths[] = asset('storage/' . $path);
                }
            }

            $product = Product::create([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'stock' => $request->stock,
                'sku' => $request->sku,
                'image' => $imagePath,
                'other_images' => $otherImagesPaths,
            ]);

            Cache::forget('products_all');

            return response()->json(['message' => 'Product Successfully Created', 'data' => $product]);
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $productData = Cache::remember("product_{$id}", 60, function () use ($id) {
            return Product::find($id);
        });

        if (!$productData) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $productData
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'sometimes|required|numeric|min:0',
                'stock' => 'sometimes|required|integer|min:0',
                'sku' => 'sometimes|required|string|unique:products,sku,' . $product->id,
                'image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
                'other_images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors(),
                    'error_type' => 'validation_error'
                ]);
            }

            if ($request->hasFile('image')) {
                if ($product->image) {
                    // Convert full URL to relative path
                    $oldImagePath = str_replace(asset('storage') . '/', '', $product->image);

                    if (Storage::disk('public')->exists($oldImagePath)) {
                        Storage::disk('public')->delete($oldImagePath);
                    }
                }

                $imagePath = $request->file('image')->store('products', 'public');
                $product->image = asset('storage/' . $imagePath);
            }

            if ($request->hasFile('other_images')) {
                $oldImages = is_array($product->other_images)
                    ? $product->other_images
                    : json_decode($product->other_images, true) ?? [];
                foreach ($oldImages as $img) {
                    $relativePath = str_replace(asset('storage') . '/', '', $img);

                    if (Storage::disk('public')->exists($relativePath)) {
                        Storage::disk('public')->delete($relativePath);
                    }
                }

                $otherImagesPaths = [];
                foreach ($request->file('other_images') as $img) {
                    $storedPath = $img->store('products', 'public');
                    $otherImagesPaths[] = asset('storage/' . $storedPath);
                }
                $product->other_images = $otherImagesPaths;
            }

            $product->fill($request->except(['image', 'other_images']));
            $product->save();

            Cache::forget('products_all');
            Cache::forget("product_{$product->id}");

            return response()->json([
                'message' => 'Product Successfully Updated',
                'data' => $product
            ]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $product = Cache::remember("product_{$id}", 60, function () use ($id) {
            return Product::find($id);
        });

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ]);
        }

        $product->delete();

        Cache::forget('products_all');
        Cache::forget("product_{$id}");

        return response()->json([
            'success' => true,
            'message' => 'Product successfully deleted'
        ]);
    }
}
