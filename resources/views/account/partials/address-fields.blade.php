@php
    $prefix = $prefix ?? '';
    $address = $address ?? null;
    $fieldName = fn (string $field) => $prefix ? $prefix.'['.$field.']' : $field;
    $fieldKey = fn (string $field) => $prefix ? $prefix.'.'.$field : $field;
    $fieldValue = fn (string $field) => old($fieldKey($field), data_get($address, $field));
    $fieldId = fn (string $field) => ($prefix ? $prefix.'_' : '').$field;
@endphp
<div class="address-grid">
    <div><label for="{{ $fieldId('recipient_name') }}">Recipient name</label><input id="{{ $fieldId('recipient_name') }}" name="{{ $fieldName('recipient_name') }}" value="{{ $fieldValue('recipient_name') }}" autocomplete="name" required>@error($fieldKey('recipient_name'))<small class="field-error">{{ $message }}</small>@enderror</div>
    <div><label for="{{ $fieldId('phone') }}">Mobile number</label><input id="{{ $fieldId('phone') }}" name="{{ $fieldName('phone') }}" type="tel" inputmode="tel" value="{{ $fieldValue('phone') }}" autocomplete="tel" required>@error($fieldKey('phone'))<small class="field-error">{{ $message }}</small>@enderror</div>
    <div><label for="{{ $fieldId('region') }}">Region</label><input id="{{ $fieldId('region') }}" name="{{ $fieldName('region') }}" value="{{ $fieldValue('region') }}" placeholder="e.g. MIMAROPA" required>@error($fieldKey('region'))<small class="field-error">{{ $message }}</small>@enderror</div>
    <div><label for="{{ $fieldId('province') }}">Province</label><input id="{{ $fieldId('province') }}" name="{{ $fieldName('province') }}" value="{{ $fieldValue('province') }}" placeholder="e.g. Oriental Mindoro" required>@error($fieldKey('province'))<small class="field-error">{{ $message }}</small>@enderror</div>
    <div><label for="{{ $fieldId('city') }}">City / Municipality</label><input id="{{ $fieldId('city') }}" name="{{ $fieldName('city') }}" value="{{ $fieldValue('city') }}" placeholder="e.g. Calapan City" required>@error($fieldKey('city'))<small class="field-error">{{ $message }}</small>@enderror</div>
    <div><label for="{{ $fieldId('barangay') }}">Barangay</label><input id="{{ $fieldId('barangay') }}" name="{{ $fieldName('barangay') }}" value="{{ $fieldValue('barangay') }}" required>@error($fieldKey('barangay'))<small class="field-error">{{ $message }}</small>@enderror</div>
    <div><label for="{{ $fieldId('postal_code') }}">Postal code</label><input id="{{ $fieldId('postal_code') }}" name="{{ $fieldName('postal_code') }}" inputmode="numeric" maxlength="4" value="{{ $fieldValue('postal_code') }}" placeholder="5200" required>@error($fieldKey('postal_code'))<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="span-2"><label for="{{ $fieldId('street_address') }}">House / Unit, street, building</label><input id="{{ $fieldId('street_address') }}" name="{{ $fieldName('street_address') }}" value="{{ $fieldValue('street_address') }}" autocomplete="street-address" required>@error($fieldKey('street_address'))<small class="field-error">{{ $message }}</small>@enderror</div>
    <div class="span-2"><label for="{{ $fieldId('landmark') }}">Landmark <span class="optional">(optional)</span></label><input id="{{ $fieldId('landmark') }}" name="{{ $fieldName('landmark') }}" value="{{ $fieldValue('landmark') }}" placeholder="A nearby place that helps find your address">@error($fieldKey('landmark'))<small class="field-error">{{ $message }}</small>@enderror</div>
</div>
