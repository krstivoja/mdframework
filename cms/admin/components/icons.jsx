// Sidebar icons — 16×16, stroke-1.5 (matches Lucide / dsystem assets/icons.svg style).

const stroke = { width: 16, height: 16, viewBox: '0 0 16 16', fill: 'none', stroke: 'currentColor', strokeWidth: 1.5, strokeLinecap: 'round', strokeLinejoin: 'round' };

export const IconGrid = (
  <svg {...stroke}>
    <rect x="2"   y="2"   width="5" height="5" rx="1" />
    <rect x="9"   y="2"   width="5" height="5" rx="1" />
    <rect x="2"   y="9"   width="5" height="5" rx="1" />
    <rect x="9"   y="9"   width="5" height="5" rx="1" />
  </svg>
);

export const IconFolder = (
  <svg {...stroke}>
    <path d="M2 4a1 1 0 0 1 1-1h3.6l1.4 1.5H13a1 1 0 0 1 1 1V12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V4z" />
  </svg>
);

export const IconPlus = (
  <svg {...stroke}>
    <path d="M8 3v10M3 8h10" />
  </svg>
);

export const IconImage = (
  <svg {...stroke}>
    <rect x="2" y="2" width="12" height="12" rx="1.5" />
    <circle cx="6" cy="6" r="1.2" />
    <path d="M2.5 11.5l3-3 2.5 2.5L11 7l3 4" />
  </svg>
);

export const IconBook = (
  <svg {...stroke}>
    <path d="M2.5 3a1 1 0 0 1 1-1H7v11H3.5a1 1 0 0 1-1-1V3z" />
    <path d="M9 2h3.5a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H9V2z" />
  </svg>
);

export const IconBackup = (
  <svg {...stroke}>
    <path d="M2 4h12v3H2z" />
    <path d="M2 7h12v6a.5.5 0 0 1-.5.5h-11A.5.5 0 0 1 2 13V7z" />
    <path d="M8 9v3M6.5 10.5L8 12l1.5-1.5" />
  </svg>
);

export const IconCog = (
  <svg {...stroke}>
    <circle cx="8" cy="8" r="2" />
    <path d="M8 1.5v2M8 12.5v2M1.5 8h2M12.5 8h2M3.5 3.5l1.4 1.4M11.1 11.1l1.4 1.4M3.5 12.5l1.4-1.4M11.1 4.9l1.4-1.4" />
  </svg>
);

export const IconLogout = (
  <svg {...stroke}>
    <path d="M6 2H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3" />
    <path d="M10 11l3-3-3-3" />
    <path d="M13 8H6" />
  </svg>
);

export const IconSearch = (
  <svg {...stroke}>
    <circle cx="7" cy="7" r="4.5" />
    <path d="M10.5 10.5L14 14" />
  </svg>
);
