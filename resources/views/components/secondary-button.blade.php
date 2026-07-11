{{-- Breeze-generated auth/profile views still use this. Delegates to the app button system. --}}
<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn btn-secondary btn-md']) }}>
    {{ $slot }}
</button>
