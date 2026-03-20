<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold uppercase tracking-[0.08em] text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
