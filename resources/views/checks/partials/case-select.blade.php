{{-- | KB @CerberRus00 - Nexus Invest Team --}}
<div>
    <x-input-label for="case_id" :value="__('aml.optional_case')" />
    <select id="case_id" name="case_id" class="ui-select mt-1 w-full">
        <option value="">{{ __('aml.no_case') }}</option>
        @foreach ($cases as $case)
            <option value="{{ $case->id }}" @selected((string) old('case_id') === (string) $case->id)>{{ $case->name }}</option>
        @endforeach
    </select>
</div>
