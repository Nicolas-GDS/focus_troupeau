{{-- Pied de page : première mention complète, signature, GDS France réduit, version (§ 8.1, ENF-EXP-03). --}}
<footer class="mt-16 border-t border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-6 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p><span class="font-medium text-slate-800">{{ config('brand.nom') }}</span>, {{ config('brand.baseline') }}</p>
            <p class="font-serif italic text-violet-sante">{{ config('brand.signature') }}</p>
        </div>
        <div class="flex items-center gap-4 sm:flex-row-reverse sm:text-right">
            <img src="{{ asset('images/logos/gds-france.png') }}" alt="Réseau GDS France" class="h-8 w-auto">
            <p>Version {{ config('app.version') }}</p>
        </div>
    </div>
</footer>
