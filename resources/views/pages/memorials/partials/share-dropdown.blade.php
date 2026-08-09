{{-- Reusable share dropdown with branded icons.

     The label's hover colour is a darkened brand shade, not the brand colour itself.
     WhatsApp green on its own hover tint is 1.89:1 and Facebook blue is 3.89:1 — hovering
     the row made its label *harder* to read than leaving it alone, which is backwards. The
     darker shades below clear 4.5:1 on both the tint and white, and still read as the
     brand. The icons keep the true brand colours; a logo is a logo. --}}
<a href="#" data-share="whatsapp" data-share-url="{{ $shareUrl }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition hover:bg-green-50 dark:hover:bg-green-950/30 text-gray-700 dark:text-gray-300 group">
    <svg class="h-5 w-5 text-[#25D366] shrink-0" viewBox="0 0 24 24" fill="currentColor">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
    <span class="group-hover:text-[#0B6B5F] dark:group-hover:text-[#25D366] transition">WhatsApp</span>
</a>
<a href="#" data-share="facebook" data-share-url="{{ $shareUrl }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition hover:bg-blue-50 dark:hover:bg-blue-950/30 text-gray-700 dark:text-gray-300 group">
    <svg class="h-5 w-5 text-[#1877F2] shrink-0" viewBox="0 0 24 24" fill="currentColor">
        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
    </svg>
    <span class="group-hover:text-[#1554B3] dark:group-hover:text-[#4A9BFF] transition">Facebook</span>
</a>
<a href="#" data-share="linkedin" data-share-url="{{ $shareUrl }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition hover:bg-sky-50 dark:hover:bg-sky-950/30 text-gray-700 dark:text-gray-300 group">
    <svg class="h-5 w-5 text-[#0A66C2] shrink-0" viewBox="0 0 24 24" fill="currentColor">
        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
    </svg>
    <span class="group-hover:text-[#0A66C2] dark:group-hover:text-[#70B5F9] transition">LinkedIn</span>
</a>
<div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
<button type="button" data-share="copy" data-share-url="{{ $shareUrl }}" class="flex w-full items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition hover:bg-gray-100 dark:hover:bg-white/10 text-gray-700 dark:text-gray-300 group">
    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500 group-hover:text-brand-500 shrink-0 transition" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
    </svg>
    <span class="group-hover:text-brand-500 transition">Copy link</span>
</button>
