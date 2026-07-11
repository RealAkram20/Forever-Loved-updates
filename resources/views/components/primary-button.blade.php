{{-- Breeze-generated auth/profile views still use this. Delegates to the app button system. --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-primary btn-md']) }}>
    {{ $slot }}
</button>
