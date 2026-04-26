import { forwardRef } from 'react';

// Mirrors dsystem/ui_kits/admin/admin.css `.btn` — h-36/32/40, all variants
// have a border to keep optical alignment consistent.

const variants = {
  primary:   'border-zinc-900 bg-zinc-900 text-white hover:bg-zinc-800 hover:border-zinc-800',
  secondary: 'border-zinc-200 bg-white text-zinc-900 hover:bg-zinc-100',
  ghost:     'border-transparent bg-transparent text-zinc-700 hover:bg-zinc-100 hover:text-zinc-900',
  danger:    'border-red-600 bg-red-600 text-white hover:bg-red-700 hover:border-red-700',
  // Text-only variants — no border, no padding box.
  link:        'text-zinc-700 hover:underline',
  'link-danger': 'text-red-600 hover:underline',
};

const sizes = {
  sm: 'h-8 px-2.5 text-xs',
  md: 'h-9 px-3.5 text-[13px]',
  lg: 'h-10 px-4 text-sm',
};

const Button = forwardRef(function Button(
  {
    variant = 'primary',
    size = 'md',
    className = '',
    type = 'button',
    disabled,
    children,
    ...rest
  },
  ref
) {
  const isLink = variant === 'link' || variant === 'link-danger';
  const base = isLink
    ? 'inline-flex items-center gap-1 text-[13px] font-medium disabled:opacity-50'
    : 'inline-flex items-center justify-center gap-2 rounded-md border font-medium whitespace-nowrap transition-colors disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900/15';

  return (
    <button
      ref={ref}
      type={type}
      disabled={disabled}
      className={`${base} ${variants[variant]} ${isLink ? '' : sizes[size]} ${className}`}
      {...rest}
    >
      {children}
    </button>
  );
});

export default Button;
