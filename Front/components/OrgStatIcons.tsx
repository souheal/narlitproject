/* Line icons for the organization stat cards. Drawn in currentColor so each
   takes its card's tone (hm-stat-icon-orange / hm-stat-icon-teal). */

const ICON_PROPS = {
  viewBox: "0 0 24 24",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: 2,
  strokeLinecap: "round",
  strokeLinejoin: "round",
  "aria-hidden": true,
} as const;

export const EarnedIcon = (
  <svg {...ICON_PROPS}>
    <circle cx="12" cy="12" r="9" />
    <path d="M14.8 9.2c-.5-1-1.6-1.6-2.8-1.6-1.6 0-2.8.9-2.8 2.2 0 3 5.6 1.6 5.6 4.5 0 1.3-1.2 2.2-2.8 2.2-1.3 0-2.4-.6-2.9-1.6M12 6v1.6M12 16.4V18" />
  </svg>
);

export const ReadsIcon = (
  <svg {...ICON_PROPS}>
    <path d="M12 6.5C10.3 5.3 8 4.8 4 5v13c4-.2 6.3.3 8 1.5 1.7-1.2 4-1.7 8-1.5V5c-4-.2-6.3.3-8 1.5Z" />
    <path d="M12 6.5v13" />
  </svg>
);

export const PublishedIcon = (
  <svg {...ICON_PROPS}>
    <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z" />
    <path d="M14 3v5h5" />
    <path d="m9 14 2 2 4-4" />
  </svg>
);

export const PayoutIcon = (
  <svg {...ICON_PROPS}>
    <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H18v4" />
    <path d="M4 7.5V17a2 2 0 0 0 2 2h13a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1H6.5A2.5 2.5 0 0 1 4 7.5Z" />
    <circle cx="16" cy="14" r="1.2" fill="currentColor" stroke="none" />
  </svg>
);

export const PendingIcon = (
  <svg {...ICON_PROPS}>
    <circle cx="12" cy="12" r="9" />
    <path d="M12 7v5l3 2" />
  </svg>
);

export const HistoryIcon = (
  <svg {...ICON_PROPS}>
    <path d="M4 20h16" />
    <path d="M7 16v-5M12 16V7M17 16v-8" />
  </svg>
);
