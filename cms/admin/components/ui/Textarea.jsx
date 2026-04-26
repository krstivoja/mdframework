import { forwardRef } from 'react';

// Textarea diverges from Input — auto-height, padding-y, mono is the default
// per dsystem `.form-input` textarea override (used for code/JSON-ish fields).
const Textarea = forwardRef(function Textarea(
  { className = '', mono = true, rows = 4, ...rest },
  ref
) {
  return (
    <textarea
      ref={ref}
      rows={rows}
      className={`block w-full resize-y rounded-md border border-zinc-200 bg-white px-3 py-2.5 leading-relaxed text-zinc-900 placeholder:text-zinc-400 transition-colors focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/15 disabled:cursor-not-allowed disabled:bg-zinc-50 disabled:text-zinc-500 ${
        mono ? 'font-mono text-xs' : 'text-[13px]'
      } ${className}`}
      {...rest}
    />
  );
});

export default Textarea;
