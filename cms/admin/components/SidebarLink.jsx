import { NavLink } from 'react-router-dom';

// Mirrors dsystem `.sidebar-link` — 13px/500, padding 8px 12px, radius-md, 16px icons.
export default function SidebarLink({ to, children, icon, end }) {
  return (
    <NavLink
      to={to}
      end={end}
      className={({ isActive }) =>
        `flex items-center gap-2 rounded-md px-3 py-2 text-[13px] font-medium transition-colors ${
          isActive
            ? 'bg-zinc-900 text-white'
            : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900'
        }`
      }
    >
      {icon && <span className="text-current opacity-80">{icon}</span>}
      {children}
    </NavLink>
  );
}
