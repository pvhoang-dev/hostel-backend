<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(HttpRequest $httpRequest): JsonResponse
    {
        $user = Auth::user();
        $query = PaymentMethod::query();

        // For regular users, only show active payment methods
        if ($user->role->code === 'tenant') {
            $query->where('status', 'active');
        }

        // Apply filters
        if ($httpRequest->has('status') && in_array($user->role->code, ['admin', 'manager'])) {
            $query->where('status', $httpRequest->status);
        }

        if ($httpRequest->has('name')) {
            $query->where('name', 'like', '%' . $httpRequest->name . '%');
        }

        $paymentMethods = $query->orderBy('created_at', 'desc')->paginate(15);

        return $this->sendResponse(
            PaymentMethodResource::collection($paymentMethods)->response()->getData(true),
            'Payment methods retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(HttpRequest $httpRequest): JsonResponse
    {
        $user = Auth::user();
        $input = $httpRequest->all();

        // Only admins can create payment methods
        if ($user->role->code !== 'admin') {
            return $this->sendError('Unauthorized', ['error' => 'Only administrators can create payment methods'], 403);
        }

        $validator = Validator::make($input, [
            'name' => 'required|string|max:255|unique:payment_methods,name',
            'status' => 'required|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $paymentMethod = PaymentMethod::create($input);

        return $this->sendResponse(
            new PaymentMethodResource($paymentMethod),
            'Payment method created successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        $paymentMethod = PaymentMethod::find($id);

        if (is_null($paymentMethod)) {
            return $this->sendError('Payment method not found.');
        }

        // Tenants can only view active payment methods
        if ($user->role->code === 'tenant' && $paymentMethod->status !== 'active') {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to view this payment method'], 403);
        }

        return $this->sendResponse(
            new PaymentMethodResource($paymentMethod),
            'Payment method retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(HttpRequest $httpRequest, string $id): JsonResponse
    {
        $user = Auth::user();
        $input = $httpRequest->all();
        $paymentMethod = PaymentMethod::find($id);

        if (is_null($paymentMethod)) {
            return $this->sendError('Payment method not found.');
        }

        // Only admins and managers can update payment methods
        if (!in_array($user->role->code, ['admin', 'manager'])) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to update payment methods'], 403);
        }

        // Managers can only update the status
        if ($user->role->code === 'manager' && array_diff(array_keys($input), ['status'])) {
            return $this->sendError('Unauthorized', ['error' => 'Managers can only update the status'], 403);
        }

        $validationRules = [
            'name' => 'sometimes|string|max:255|unique:payment_methods,name,' . $id,
            'status' => 'sometimes|string|in:active,inactive',
        ];

        $validator = Validator::make($input, $validationRules);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $paymentMethod->update($input);

        return $this->sendResponse(
            new PaymentMethodResource($paymentMethod),
            'Payment method updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();
        $paymentMethod = PaymentMethod::find($id);

        if (is_null($paymentMethod)) {
            return $this->sendError('Payment method not found.');
        }

        // Only admins can delete payment methods
        if ($user->role->code !== 'admin') {
            return $this->sendError('Unauthorized', ['error' => 'Only administrators can delete payment methods'], 403);
        }

        // Check if payment method is in use
        if ($paymentMethod->transactions()->count() > 0) {
            return $this->sendError('Validation Error.', ['error' => 'Cannot delete payment method that is in use']);
        }

        $paymentMethod->delete();

        return $this->sendResponse([], 'Payment method deleted successfully.');
    }
}
