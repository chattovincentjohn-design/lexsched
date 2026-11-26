@props(['name', 'label', 'hint' => null, 'class' => ''])
<div @class(['field', $class, 'has-error' => $errors->has($name)])>
    <label for="{{ $name }}">{{ $label }}</label>
    {{ $slot }}
    @error($name)<span class="error">{{ $message }}</span>@enderror
    @if ($hint && ! $errors->has($name))<span class="hint">{{ $hint }}</span>@endif
</div>
