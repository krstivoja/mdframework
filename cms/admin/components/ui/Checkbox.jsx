// Mirrors dsystem checkbox — 16px square, primary on check.
export default function Checkbox({ label, className = '', ...rest }) {
  const input = (
    <input
      type="checkbox"
      className={`h-4 w-4 rounded border-zinc-300 text-zinc-900 transition-colors focus:ring-2 focus:ring-zinc-900/15 focus:ring-offset-0 ${className}`}
      {...rest}
    />
  );
  if (!label) return input;
  return (
    <label className="flex items-center gap-2 text-[13px] text-zinc-900">
      {input}
      <span>{label}</span>
    </label>
  );
}
