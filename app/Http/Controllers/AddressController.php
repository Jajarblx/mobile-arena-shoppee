<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Services\AddressBook;
use App\Support\AddressData;
use App\Support\MobileNumber;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return view('account.addresses', [
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->latest()->get(),
        ]);
    }

    public function create()
    {
        return view('account.address-form', ['address' => null]);
    }

    public function store(Request $request, AddressBook $addressBook)
    {
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => MobileNumber::normalize($request->input('phone'))]);
        }
        $data = $request->validate([
            ...AddressData::rules(),
            'is_default' => ['nullable', 'boolean'],
        ]);
        unset($data['is_default']);

        $addressBook->create($request->user(), $data, $request->boolean('is_default'));

        return redirect()->route('account.addresses')->with('success', 'Address saved.');
    }

    public function edit(Request $request, Address $address)
    {
        $this->ensureOwner($request, $address);

        return view('account.address-form', compact('address'));
    }

    public function update(Request $request, Address $address, AddressBook $addressBook)
    {
        $this->ensureOwner($request, $address);
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => MobileNumber::normalize($request->input('phone'))]);
        }
        $data = $request->validate([
            ...AddressData::rules(),
            'is_default' => ['nullable', 'boolean'],
        ]);
        unset($data['is_default']);
        $address->update($data);
        if ($request->boolean('is_default')) {
            $addressBook->setDefault($request->user(), $address);
        }

        return redirect()->route('account.addresses')->with('success', 'Address updated.');
    }

    public function setDefault(Request $request, Address $address, AddressBook $addressBook)
    {
        $this->ensureOwner($request, $address);
        $addressBook->setDefault($request->user(), $address);

        return back()->with('success', 'Default address updated.');
    }

    public function destroy(Request $request, Address $address, AddressBook $addressBook)
    {
        $this->ensureOwner($request, $address);
        $addressBook->delete($request->user(), $address);

        return redirect()->route('account.addresses')->with('success', 'Address removed.');
    }

    private function ensureOwner(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }
}
