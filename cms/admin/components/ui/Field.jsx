// Mirrors dsystem `.form-group` / `.form-label` / `.form-hint`.
export default function Field({ label, hint, children, className = '' }) {
  return (
    <label className={`block ${className}`}>
      {label && (
        <span className="mb-2 block text-[13px] font-medium text-zinc-900">{label}</span>
      )}
      {children}
      {hint && <span className="mt-1 block text-xs text-zinc-500">{hint}</span>}
    </label>
  );
}
