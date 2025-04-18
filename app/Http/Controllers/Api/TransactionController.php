<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Invoice;
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

        if ($httpRequest->has('date_from')) {
            $query->whereDate('payment_date', '>=', $httpRequest->date_from);
        }

        if ($httpRequest->has('date_to')) {
            $query->whereDate('payment_date', '<=', $httpRequest->date_to);
        }

        $transactions = $query->orderBy('payment_date', 'desc')->paginate(15);

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
            $input['transaction_code'] = 'TXN-' . Str::random(10) . '-' . time();
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
