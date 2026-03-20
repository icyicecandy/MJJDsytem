@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-xl border-slate-300 bg-white px-4 py-3 text-slate-900 shadow-sm transition focus:border-slate-500 focus:ring focus:ring-slate-200']) }}>
