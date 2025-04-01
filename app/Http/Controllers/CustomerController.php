<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\S3Services;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller implements HasMiddleware
{
    protected $s3Service;

    public function __construct(S3Services $s3Service)
    {
        $this->s3Service = $s3Service;
    }

    public static function middleware()
    {
        return [
            new Middleware('auth:sanctum', except: ['index', 'show'])
        ];
    }

    public function index()
    {
        $customers = Customer::all();
        return response()->json([
            'status' => true,
            'message' => 'Customer retrieved successfully!',
            'data'=> $customers
        ], 200);
    }

    public function show($id)
    {
        $customer = Customer::findOrFail($id);

        return response()->json([
            'status'=> true,
            'message'=> 'Customer found successfully!',
            'data'=> $customer
        ], 200);
    }

    public function store(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:customers|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error!',
                'data' => $validate->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $imageUrl = $this->s3Service->upload(
                $request->file('image'),
                'customers'
            );

            $customer = $request->user()->customers()->create([
                'name' => $request->name,
                'email' => $request->email,
                'imageUrl' => $imageUrl
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Customer created successfully!',
                'data' => $customer
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        Gate::authorize('modify', $customer);

        $validate = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:customers,email,' . $id,
            'image' => 'sometimes|required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error!',
                'error' => $validate->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            if ($request->hasFile('image')) {
                if ($customer->imageUrl) {
                    $imageUrl = $this->s3Service->update(
                        $request->file('image'),
                        $customer->imageUrl,
                        'customers'
                    );
                } else {
                    $imageUrl = $this->s3Service->upload(
                        $request->file('image'),
                        'customers'
                    );
                }
                $customer->imageUrl = $imageUrl;
            }

            $customer->fill($request->only(['name', 'email']));
            $customer->save();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Customer updated successfully!',
                'data' => $customer
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to update customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);
        Gate::authorize('modify', $customer);

        try {
            DB::beginTransaction();

            if ($customer->imageUrl) {
                $this->s3Service->delete($customer->imageUrl);
            }

            $customer->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Customer deleted successfully!'
            ], 204);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
