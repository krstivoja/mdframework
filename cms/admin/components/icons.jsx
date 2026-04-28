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

export const IconTrash = (
  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2-icon lucide-trash-2">
    <path d="M10 11v6"/>
    <path d="M14 11v6"/>
    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
    <path d="M3 6h18"/>
    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
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
  <svg {...stroke} viewBox="0 0 24 24">
    <path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"/><circle cx="12" cy="12" r="3"/>
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

