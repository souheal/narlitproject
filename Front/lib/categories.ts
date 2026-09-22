/** Single source of truth for category emblems shown next to category names. */
export const CATEGORY_ICONS: Record<string, string> = {
  Environment: "🌍",
  "Food Security": "🍽️",
  Education: "🎓",
  Refugees: "🕊️",
  Health: "🏥",
  Healthcare: "🏥",
  Housing: "🏠",
  "Civil Rights": "⚖️",
  "Women & Children": "👨‍👩‍👧",
};

export const FALLBACK_CATEGORY_ICON = "📰";

/** Never returns null — the API sends no icon for a category, so we map it here. */
export function categoryIcon(category?: string | null): string {
  if (!category) return FALLBACK_CATEGORY_ICON;
  return CATEGORY_ICONS[category] ?? FALLBACK_CATEGORY_ICON;
}
