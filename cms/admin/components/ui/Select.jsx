import { forwardRef } from 'react';
import { baseControlCls } from './Input.jsx';

// Match dsystem `select.form-input` — appearance-none + chevron SVG bg.
const chevronCls =
  "appearance-none bg-[url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2371717a' stroke-width='2'><polyline points='6 9 12 15 18 9'/></svg>\")] bg-[length:16px_16px] bg-[right_10px_center] bg-no-repeat pr-8";

const Select = forwardRef(function Select(
  { className = '', children, ...rest },
  ref
) {
  return (
    <select ref={ref} className={`${baseControlCls} ${chevronCls} ${className}`} {...rest}>
      {children}
    </select>
  );
});

export default Select;
