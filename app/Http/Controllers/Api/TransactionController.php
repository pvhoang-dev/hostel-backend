<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TransactionController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(HttpRequest $httpRequest): JsonResponse
    {
        $user = Auth::user();
        $query = Transaction::query();

        // Apply relationship eager loading
        $with = ['paymentMethod'];
        if ($httpRequest->has('include')) {
            $includes = explode(',', $httpRequest->include);
            if (in_array('invoice', $includes)) {
                $with[] = 'invoice';
                if (in_array('invoice.room', $includes)) {
                    $with[] = 'invoice.room';
                }
            }
        }
        $query->with($with);

        // Role-based filtering
        if ($user->role->code === 'tenant') {
            // Tenants can only see their own transactions
            $query->whereHas('invoice.room', function ($q) use ($user) {
                $q->where('tenant_id', $user->id);
            });
        } elseif ($user->role->code === 'manager') {
            // Managers can see transactions for their properties
            $query->whereHas('invoice.room.house', function ($q) use ($user) {
                $q->where('manager_id', $user->id);
            });
        }
        // Admins can see all transactions

        // Apply filters
        if ($httpRequest->has('status')) {
            $query->where('status', $httpRequest->status);
        }

        if ($httpRequest->has('invoice_id')) {
            $query->where('invoice_id', $httpRequest->invoice_id);
        }

        if ($httpRequest->has('payment_method_id')) {
            $query->where('payment_method_id', $httpRequest->payment_method_id);
        }

        if ($httpRequest->has('transaction_code')) {
            $query->where('transaction_code', 'like', '%' . $httpRequest->transaction_code . '%');
        }

        if ($httpRequest->has('amount_min')) {
            $query->where('amount', '>=', $httpRequest->amount_min);
        }

        if ($httpRequest->has('amount_max')) {
            $query->where('amount', '<=', $httpRequest->amount_max);
        }

        if ($httpRequest->has('date_from')) {
            $query->whereDate('payment_date', '>=', $httpRequest->date_from);
        }

        if ($httpRequest->has('date_to')) {
            $query->whereDate('payment_date', '<=', $httpRequest->date_to);
        }

        if ($httpRequest->has('room_id')) {
            $query->whereHas('invoice.room', function($q) use ($httpRequest) {
                $q->where('id', $httpRequest->room_id);
            });
        }

        if ($httpRequest->has('house_id')) {
            $query->whereHas('invoice.room.house', function($q) use ($httpRequest) {
                $q->where('id', $httpRequest->house_id);
            });
        }

        // Sorting
        $sortField = $httpRequest->get('sort_by', 'payment_date');
        $sortDirection = $httpRequest->get('sort_direction', 'desc');

        // Validate sort field to prevent SQL injection
        $allowedSortFields = ['id', 'transaction_code', 'amount', 'status', 'payment_date', 'created_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'payment_date';
        }

        // Validate sort direction
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $httpRequest->get('per_page', 15);
        if (!is_numeric($perPage) || $perPage < 1 || $perPage > 100) {
            $perPage = 15;
        }

        $transactions = $query->paginate($perPage);

        return $this->sendResponse(
            TransactionResource::collection($transactions)->response()->getData(true),
            'Transactions retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(HttpRequest $httpRequest): JsonResponse
    {
        $user = Auth::user();
        $input = $httpRequest->all();

        // Validation
        $validator = Validator::make($input, [
            'invoice_id' => 'required|exists:invoices,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'amount' => 'required|integer|min:1',
            'status' => 'required|string|in:pending,completed,failed,refunded',
            'payment_date' => 'required|date',
            'transaction_code' => 'sometimes|string|max:255|unique:transactions,transaction_code',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Check authorization
        $invoice = Invoice::with('room.house')->find($input['invoice_id']);
        if (!$invoice) {
            return $this->sendError('Invoice not found.');
        }

        // Verify payment method exists and is active
        $paymentMethod = PaymentMethod::find($input['payment_method_id']);
        if (!$paymentMethod) {
            return $this->sendError('Payment method not found.');
        }

        if ($paymentMethod->status !== 'active' && $user->role->code !== 'admin') {
            return $this->sendError('Payment method is not active.');
        }

        $canManage = false;

        // Admin can manage all transactions
        if ($user->role->code === 'admin') {
            $canManage = true;
        }
        // Manager can only manage transactions for their houses
        elseif ($user->role->code === 'manager' && $invoice->room->house->manager_id === $user->id) {
            $canManage = true;
        }
        // Tenant can only pay their own invoices
        elseif ($user->role->code === 'tenant' && $invoice->room->tenant_id === $user->id) {
            $canManage = true;

            // Tenants can only create pending transactions
            if ($input['status'] !== 'pending') {
                return $this->sendError('Unauthorized', ['error' => 'Tenants can only create pending transactions'], 403);
            }
        }

        if (!$canManage) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to create this transaction'], 403);
        }

        // Generate transaction code if not provided
        if (!isset($input['transaction_code'])) {
            $input['transaction_code'] = 'TXN-' . Str::random(8) . '-' . time();
        }

        $transaction = Transaction::create($input);

        return $this->sendResponse(
            new TransactionResource($transaction->load(['invoice', 'paymentMethod'])),
            'Transaction created successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        $transaction = Transaction::with(['invoice.room.house', 'paymentMethod'])->find($id);

        if (is_null($transaction)) {
            return $this->sendError('Transaction not found.');
        }

        // Check permissions based on role
        if ($user->role->code === 'tenant' && $transaction->invoice->room->tenant_id !== $user->id) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to view this transaction'], 403);
        }

        if ($user->role->code === 'manager' && $transaction->invoice->room->house->manager_id !== $user->id) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to view this transaction'], 403);
        }

        return $this->sendResponse(
            new TransactionResource($transaction),
            'Transaction retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(HttpRequest $httpRequest, string $id): JsonResponse
    {
        $user = Auth::user();
        $input = $httpRequest->all();
        $transaction = Transaction::with(['invoice.room.house'])->find($id);

        if (is_null($transaction)) {
            return $this->sendError('Transaction not found.');
        }

        // Only admins and managers can update transactions
        $canUpdate = false;
        $isAdmin = $user->role->code === 'admin';
        $isManager = $user->role->code === 'manager' && $transaction->invoice->room->house->manager_id === $user->id;

        if ($isAdmin) {
            $canUpdate = true;
        } elseif ($isManager) {
            // Managers can only update status
            if (array_diff(array_keys($input), ['status'])) {
                return $this->sendError('Unauthorized', ['error' => 'Managers can only update the status'], 403);
            }
            $canUpdate = true;
        }

        if (!$canUpdate) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to update this transaction'], 403);
        }

        // Validation rules
        $validationRules = [
            'invoice_id' => 'sometimes|exists:invoices,id',
            'payment_method_id' => 'sometimes|exists:payment_methods,id',
            'amount' => 'sometimes|integer|min:1',
            'status' => 'sometimes|string|in:pending,completed,failed,refunded',
            'payment_date' => 'sometimes|date',
            'transaction_code' => 'sometimes|string|max:255|unique:transactions,transaction_code,' . $id,
        ];

        $validator = Validator::make($input, $validationRules);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // If changing payment method, verify it exists and is active
        if (isset($input['payment_method_id'])) {
            $paymentMethod = PaymentMethod::find($input['payment_method_id']);
            if (!$paymentMethod) {
                return $this->sendError('Payment method not found.');
            }

            if ($paymentMethod->status !== 'active' && !$isAdmin) {
                return $this->sendError('Payment method is not active.');
            }
        }

        $transaction->update($input);

        return $this->sendResponse(
            new TransactionResource($transaction->load(['invoice', 'paymentMethod'])),
            'Transaction updated successfully.'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();
        $transaction = Transaction::with(['invoice.room.house'])->find($id);

        if (is_null($transaction)) {
            return $this->sendError('Transaction not found.');
        }

        // Only admins can delete transactions
        if ($user->role->code !== 'admin') {
            return $this->sendError('Unauthorized', ['error' => 'Only administrators can delete transactions'], 403);
        }

        $transaction->delete();

        return $this->sendResponse([], 'Transaction deleted successfully.');
    }
}
