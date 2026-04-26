// Mirrors dsystem `.badge` — pill (border-radius: 999px), 11px/600 letters.
// Tones map to dsystem variants: live → primary; draft → surface-2.
const tones = {
  live:      'bg-zinc-900 text-white',
  published: 'bg-zinc-900 text-white',
  active:    'bg-zinc-900 text-white',
  draft:     'border border-zinc-200 bg-zinc-100 text-zinc-700',
  neutral:   'border border-zinc-200 bg-zinc-100 text-zinc-700',
  success:   'bg-emerald-100 text-emerald-700',
  warning:   'bg-amber-100 text-amber-700',
  danger:    'bg-red-100 text-red-700',
};

export default function Badge({ tone = 'neutral', children, className = '' }) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold tracking-wide ${
        tones[tone] || tones.neutral
      } ${className}`}
    >
      {children}
    </span>
  );
}
