<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AddressController extends Controller
{
    use Responds;

    public function index(Request $request)
    {
        return $this->ok(
            Address::where('user_id', $request->user()->id)
                ->orderByDesc('is_default')
                ->latest('id')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:50',
            'full_address' => 'nullable|string|max:1000',
            'formatted_address' => 'nullable|string|max:1000',
            'street' => 'nullable|string|max:255',
            'barangay' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'is_default' => 'nullable|boolean',
            'notes' => 'nullable|string|max:255',
        ]);

        $readable = $this->readableAddress($data);
        if ($readable === '') {
            throw ValidationException::withMessages([
                'full_address' => 'Enter a full address or at least one readable address detail.',
            ]);
        }

        $data['formatted_address'] = trim((string) ($data['formatted_address'] ?? '')) ?: $readable;
        $data['full_address'] = trim((string) ($data['full_address'] ?? '')) ?: $data['formatted_address'];

        $uid = $request->user()->id;
        $isDefault = $request->boolean('is_default')
            || ! Address::where('user_id', $uid)->where('is_default', true)->exists();
        $data['is_default'] = $isDefault;
        if ($isDefault) {
            Address::where('user_id', $uid)->update(['is_default' => false]);
        }

        $address = Address::create($data + [
            'user_id' => $uid,
            'label' => $data['label'] ?? 'Home',
            'is_default' => $isDefault,
        ]);

        return $this->ok($address, 'Na-save ang address', 201);
    }

    public function show(Request $request, int $id)
    {
        return $this->ok(
            Address::where('user_id', $request->user()->id)->findOrFail($id)
        );
    }

    public function update(Request $request, int $id)
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);
        $data = $request->validate([
            'label' => 'sometimes|nullable|string|max:50',
            'full_address' => 'sometimes|nullable|string|max:1000',
            'formatted_address' => 'sometimes|nullable|string|max:1000',
            'street' => 'sometimes|nullable|string|max:255',
            'barangay' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:255',
            'province' => 'sometimes|nullable|string|max:255',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'is_default' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string|max:255',
        ]);

        $structuredKeys = ['street', 'barangay', 'city', 'province', 'postal_code'];
        $structuredChanged = count(array_intersect($structuredKeys, array_keys($data))) > 0;
        if ($structuredChanged
            && !array_key_exists('full_address', $data)
            && !array_key_exists('formatted_address', $data)) {
            $data['formatted_address'] = $this->readableAddress(
                array_merge($address->only($structuredKeys), $data)
            );
            $data['full_address'] = $data['formatted_address'];
        } elseif (array_key_exists('formatted_address', $data)
            && !array_key_exists('full_address', $data)
            && trim((string) $data['formatted_address']) !== '') {
            $data['full_address'] = $data['formatted_address'];
        } elseif (array_key_exists('full_address', $data)
            && !array_key_exists('formatted_address', $data)
            && trim((string) $data['full_address']) !== '') {
            $data['formatted_address'] = $data['full_address'];
        }

        if (array_key_exists('is_default', $data) && $request->boolean('is_default')) {
            Address::where('user_id', $request->user()->id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        } elseif (array_key_exists('is_default', $data)
            && ! $request->boolean('is_default')
            && $address->is_default
            && ! Address::where('user_id', $request->user()->id)
                ->where('id', '!=', $address->id)
                ->where('is_default', true)
                ->exists()) {
            // Keep one usable default instead of leaving the customer without one.
            $data['is_default'] = true;
        }

        $address->fill($data)->save();

        return $this->ok($address->fresh(), 'Na-update ang address');
    }

    public function destroy(Request $request, int $id)
    {
        $uid = $request->user()->id;
        \DB::transaction(function () use ($uid, $id) {
            $address = Address::where('user_id', $uid)->lockForUpdate()->findOrFail($id);
            $address->delete();

            if (! Address::where('user_id', $uid)->where('is_default', true)->exists()) {
                $replacement = Address::where('user_id', $uid)->latest('id')->first();
                if ($replacement) {
                    $replacement->update(['is_default' => true]);
                }
            }
        });

        return $this->ok(null, 'Nabura');
    }

    private function readableAddress(array $data): string
    {
        $parts = [];
        foreach (['street', 'barangay', 'city', 'province', 'postal_code'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value !== '' && !in_array($value, $parts, true)) {
                $parts[] = $value;
            }
        }

        return implode(', ', $parts);
    }
}
