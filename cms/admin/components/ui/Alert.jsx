// Mirrors dsystem alert tones — soft bg + border, neutral typography.
const tones = {
  error:   'border-red-200 bg-red-50 text-red-700',
  success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  warning: 'border-amber-200 bg-amber-50 text-amber-800',
  info:    'border-zinc-200 bg-zinc-50 text-zinc-700',
};

export default function Alert({ tone = 'info', children, className = '' }) {
  return (
    <div className={`rounded-md border px-3 py-2.5 text-[13px] ${tones[tone] || tones.info} ${className}`}>
      {children}
    </div>
  );
}
