<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use App\Http\Resources\InvoiceResource;
use App\Models\House;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Room;
use App\Models\ServiceUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Invoice::query();

        // Apply role-based filters
        if ($user->role->code === 'tenant') {
            // Tenants can only see invoices for rooms they occupy
            $query->whereHas('room.contracts.tenants', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        } elseif ($user->role->code === 'manager') {
            // Managers can see invoices for houses they manage
            $managedHouseIds = House::where('manager_id', $user->id)->pluck('id');
            $query->whereHas('room', function ($q) use ($managedHouseIds) {
                $q->whereIn('house_id', $managedHouseIds);
            });
        }
        // Admins can see all invoices, so no filter needed

        // Apply additional filters
        if ($request->has('room_id')) {
            $query->where('room_id', $request->room_id);
        }

        if ($request->has('invoice_type')) {
            $query->where('invoice_type', $request->invoice_type);
        }

        if ($request->has('month')) {
            $query->where('month', $request->month);
        }

        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        // Include relationships
        $with = [];
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            if (in_array('room', $includes)) $with[] = 'room';
            if (in_array('items', $includes)) $with[] = 'items';
            if (in_array('transactions', $includes)) $with[] = 'transactions';
            if (in_array('creator', $includes)) $with[] = 'creator';
            if (in_array('updater', $includes)) $with[] = 'updater';
        }

        $invoices = $query->with($with)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->paginate(15);

        return $this->sendResponse(
            InvoiceResource::collection($invoices)->response()->getData(true),
            'Invoices retrieved successfully.'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $input = $request->all();

        $validator = Validator::make($input, [
            'room_id' => 'required|exists:rooms,id',
            'invoice_type' => 'required|in:custom,service_usage',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'description' => 'sometimes|nullable|string',
            'items' => 'required|array|min:1',
            'items.*.source_type' => 'required|in:manual,service_usage',
            'items.*.service_usage_id' => 'required_if:items.*.source_type,service_usage|exists:service_usage,id|nullable',
            'items.*.amount' => 'required|integer|min:0',
            'items.*.description' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Check authorization
        $room = Room::with('house')->find($input['room_id']);
        if (!$room) {
            return $this->sendError('Room not found.');
        }

        // Only managers of the house or admins can create invoices
        if ($user->role->code === 'tenant') {
            return $this->sendError('Unauthorized', ['error' => 'Tenants cannot create invoices'], 403);
        } elseif ($user->role->code === 'manager' && $room->house->manager_id !== $user->id) {
            return $this->sendError('Unauthorized', ['error' => 'You can only create invoices for rooms in houses you manage'], 403);
        }

        // Check if an invoice already exists for this room in the specified month/year
        $existingInvoice = Invoice::where('room_id', $input['room_id'])
            ->where('month', $input['month'])
            ->where('year', $input['year'])
            ->first();

        if ($existingInvoice) {
            return $this->sendError('Validation Error.', ['invoice' => 'An invoice already exists for this room in the specified month/year']);
        }

        // Validate service_usage_id if provided
        if ($input['invoice_type'] === 'service_usage') {
            foreach ($input['items'] as $item) {
                if ($item['source_type'] === 'service_usage' && isset($item['service_usage_id'])) {
                    $serviceUsage = ServiceUsage::with('roomService')->find($item['service_usage_id']);

                    if (!$serviceUsage) {
                        return $this->sendError('Validation Error.', ['items' => 'Service usage not found']);
                    }

                    // Check if service usage belongs to a room service in the specified room
                    if ($serviceUsage->roomService->room_id !== $room->id) {
                        return $this->sendError('Validation Error.',
                            ['items' => 'Service usage must belong to the specified room']);
                    }

                    // Check if service usage is for the specified month/year
                    if ($serviceUsage->month != $input['month'] || $serviceUsage->year != $input['year']) {
                        return $this->sendError('Validation Error.',
                            ['items' => 'Service usage month/year must match invoice month/year']);
                    }
                }
            }
        }

        // Calculate total amount
        $totalAmount = array_sum(array_column($input['items'], 'amount'));

        try {
            DB::beginTransaction();

            // Create invoice
            $invoice = Invoice::create([
                'room_id' => $input['room_id'],
                'invoice_type' => $input['invoice_type'],
                'total_amount' => $totalAmount,
                'month' => $input['month'],
                'year' => $input['year'],
                'description' => $input['description'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Create invoice items
            foreach ($input['items'] as $itemData) {
                $invoice->items()->create([
                    'source_type' => $itemData['source_type'],
                    'service_usage_id' => $itemData['service_usage_id'] ?? null,
                    'amount' => $itemData['amount'],
                    'description' => $itemData['description'] ?? null,
                ]);
            }

            DB::commit();

            return $this->sendResponse(
                new InvoiceResource($invoice->load(['room', 'items', 'creator'])),
                'Invoice created successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Creation Error.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();
        $invoice = Invoice::with(['room.house', 'items.service_usage', 'transactions', 'creator', 'updater'])->find($id);

        if (is_null($invoice)) {
            return $this->sendError('Invoice not found.');
        }

        // Authorization check
        if (!$this->canAccessInvoice($user, $invoice)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to view this invoice'], 403);
        }

        return $this->sendResponse(
            new InvoiceResource($invoice),
            'Invoice retrieved successfully.'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $input = $request->all();
        $invoice = Invoice::with('room.house')->find($id);

        if (is_null($invoice)) {
            return $this->sendError('Invoice not found.');
        }

        // Authorization check
        if (!$this->canManageInvoice($user, $invoice)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to update this invoice'], 403);
        }

        $validator = Validator::make($input, [
            'invoice_type' => 'sometimes|in:custom,service_usage',
            'description' => 'sometimes|nullable|string',
            'items' => 'sometimes|array',
            'items.*.id' => 'sometimes|exists:invoice_items,id',
            'items.*.source_type' => 'required_with:items|in:manual,service_usage',
            'items.*.service_usage_id' => 'required_if:items.*.source_type,service_usage|exists:service_usage,id|nullable',
            'items.*.amount' => 'required_with:items|integer|min:0',
            'items.*.description' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        // Validate service_usage_id if provided
        if (isset($input['items'])) {
            foreach ($input['items'] as $item) {
                if (isset($item['source_type']) && $item['source_type'] === 'service_usage' && isset($item['service_usage_id'])) {
                    $serviceUsage = ServiceUsage::with('roomService')->find($item['service_usage_id']);

                    if (!$serviceUsage) {
                        return $this->sendError('Validation Error.', ['items' => 'Service usage not found']);
                    }

                    // Check if service usage belongs to a room service in the specified room
                    if ($serviceUsage->roomService->room_id !== $invoice->room_id) {
                        return $this->sendError('Validation Error.',
                            ['items' => 'Service usage must belong to the same room as the invoice']);
                    }

                    // Check if service usage is for the specified month/year
                    if ($serviceUsage->month != $invoice->month || $serviceUsage->year != $invoice->year) {
                        return $this->sendError('Validation Error.',
                            ['items' => 'Service usage month/year must match invoice month/year']);
                    }
                }
            }
        }

        try {
            DB::beginTransaction();

            // Update invoice
            $invoiceData = collect($input)->only(['invoice_type', 'description'])->toArray();
            if (!empty($invoiceData)) {
                $invoiceData['updated_by'] = $user->id;
                $invoice->update($invoiceData);
            }

            // Update invoice items if provided
            if (isset($input['items'])) {
                // Get existing item IDs
                $existingItemIds = $invoice->items->pluck('id')->toArray();
                $updatedItemIds = [];

                foreach ($input['items'] as $itemData) {
                    if (isset($itemData['id'])) {
                        // Update existing item
                        $item = InvoiceItem::find($itemData['id']);
                        if ($item && $item->invoice_id == $invoice->id) {
                            $item->update([
                                'source_type' => $itemData['source_type'],
                                'service_usage_id' => $itemData['service_usage_id'] ?? null,
                                'amount' => $itemData['amount'],
                                'description' => $itemData['description'] ?? null,
                            ]);
                            $updatedItemIds[] = $item->id;
                        }
                    } else {
                        // Create new item
                        $item = $invoice->items()->create([
                            'source_type' => $itemData['source_type'],
                            'service_usage_id' => $itemData['service_usage_id'] ?? null,
                            'amount' => $itemData['amount'],
                            'description' => $itemData['description'] ?? null,
                        ]);
                        $updatedItemIds[] = $item->id;
                    }
                }

                // Delete items not in the update list
                $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
                if (!empty($itemsToDelete)) {
                    InvoiceItem::whereIn('id', $itemsToDelete)->delete();
                }

                // Recalculate total amount
                $totalAmount = $invoice->items()->sum('amount');
                $invoice->update(['total_amount' => $totalAmount]);
            }

            DB::commit();

            return $this->sendResponse(
                new InvoiceResource($invoice->load(['room', 'items', 'updater'])),
                'Invoice updated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Update Error.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();
        $invoice = Invoice::with('room.house')->find($id);

        if (is_null($invoice)) {
            return $this->sendError('Invoice not found.');
        }

        // Authorization check
        if (!$this->canManageInvoice($user, $invoice)) {
            return $this->sendError('Unauthorized', ['error' => 'You do not have permission to delete this invoice'], 403);
        }

        // Check if invoice has completed transactions
        if ($invoice->transactions()->exists()) {
            return $this->sendError('Delete Error.', ['error' => 'Cannot delete an invoice with associated transactions']);
        }

        $invoice->delete();

        return $this->sendResponse([], 'Invoice deleted successfully.');
    }

    /**
     * Check if user can access an invoice
     */
    private function canAccessInvoice($user, $invoice): bool
    {
        // Admins can access all invoices
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants can only access invoices for rooms they occupy
        if ($user->role->code === 'tenant') {
            return $invoice->room->contracts()
                ->whereHas('tenants', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->exists();
        }

        // Managers can access invoices for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $invoice->room->house->manager_id;
        }

        return false;
    }

    /**
     * Check if user can manage an invoice (update/delete)
     */
    private function canManageInvoice($user, $invoice): bool
    {
        // Admins can manage all invoices
        if ($user->role->code === 'admin') {
            return true;
        }

        // Tenants cannot manage invoices
        if ($user->role->code === 'tenant') {
            return false;
        }

        // Managers can manage invoices for houses they manage
        if ($user->role->code === 'manager') {
            return $user->id === $invoice->room->house->manager_id;
        }

        return false;
    }
}
