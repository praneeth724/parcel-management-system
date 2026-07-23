@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'help' => null,
    'placeholder' => null,
    'options' => null,
    'rows' => 3,
    'prefix' => null,
])

@php
    // Support dotted names like `address.city` for the error bag lookup.
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($errorKey);
    $id = $attributes->get('id', 'field-'.Str::slug(str_replace(['[', ']', '.'], '-', $name)));
    $current = old($errorKey, $value);
    $control = $attributes->except(['id'])->merge([
        'class' => 'form-'.($options !== null ? 'select' : 'control').($hasError ? ' is-invalid' : ''),
    ]);
@endphp

<div class="mb-3">
    @if ($label)
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif

    @if ($prefix)
        <div class="input-group">
            <span class="input-group-text">{{ $prefix }}</span>
    @endif

    @if ($options !== null)
        <select name="{{ $name }}" id="{{ $id }}" {{ $control }} @required($required)>
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif

            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>
                    {{ $optionLabel }}
                </option>
            @endforeach
        </select>
    @elseif ($type === 'textarea')
        <textarea name="{{ $name }}"
                  id="{{ $id }}"
                  rows="{{ $rows }}"
                  placeholder="{{ $placeholder }}"
                  {{ $control }}
                  @required($required)>{{ $current }}</textarea>
    @else
        <input type="{{ $type }}"
               name="{{ $name }}"
               id="{{ $id }}"
               @if ($type !== 'file') value="{{ $current }}" @endif
               placeholder="{{ $placeholder }}"
               {{ $control }}
               @required($required)>
    @endif

    @if ($prefix)
        </div>
    @endif

    @if ($hasError)
        <div class="invalid-feedback d-block">{{ $errors->first($errorKey) }}</div>
    @elseif ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
