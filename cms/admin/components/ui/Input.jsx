import { forwardRef } from 'react';

// Mirrors dsystem `.form-input` — 36px tall, 13px text, 6px radius, soft black
// focus ring (rgba(9,9,11,.12) ≈ ring-zinc-900/15).
export const baseControlCls =
  'flex h-9 w-full rounded-md border border-zinc-200 bg-white px-3 text-[13px] text-zinc-900 placeholder:text-zinc-400 transition-colors focus:border-zinc-900 focus:outline-none focus:ring-2 focus:ring-zinc-900/15 disabled:cursor-not-allowed disabled:bg-zinc-50 disabled:text-zinc-500';

const Input = forwardRef(function Input(
  { className = '', mono = false, ...rest },
  ref
) {
  return (
    <input
      ref={ref}
      className={`${baseControlCls} ${mono ? 'font-mono text-xs' : ''} ${className}`}
      {...rest}
    />
  );
});

export default Input;
