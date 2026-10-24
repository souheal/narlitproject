/**
 * Left-hand panel of the login page: says what NarLit is in one glance. The headline is the site's
 * own promise, the three steps are how the money moves, and the story card shows the mechanism live
 * (a reader's progress bar reaching the 80% mark that counts as a completed read, then a small
 * "funded" chip appearing). Hidden on narrow screens; the animation is off for reduced motion.
 */

const STEPS = [
  {
    title: "Subscribe once",
    body: "One monthly plan covers every story. No extra donations.",
    icon: (
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <rect x="3" y="6" width="18" height="12" rx="3" />
        <path d="M3 10h18M7 15h4" />
      </svg>
    ),
  },
  {
    title: "Read nonprofit journalism",
    body: "Stories by verified 501(c)(3) organizations about their causes.",
    icon: (
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12 6.5C10 5 7 4.6 4 5v13c3-.4 6 0 8 1.5 2-1.500 5-1.900 8-1.500V5c-3-.4-6 0-8 1.500Z" />
        <path d="M12 6.5V19.500" />
      </svg>
    ),
  },
  {
    title: "Finish a story, fund the author",
    body: "Part of your subscription goes to the nonprofit behind it.",
    icon: (
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12 20s-7-4.400-7-10a4 4 0 0 1 7-2.600A4 4 0 0 1 19 10c0 5.600-7 10-7 10Z" />
      </svg>
    ),
  },
];

const STATS = [
  { value: "~$2.31", label: "of every $7 goes to nonprofits" },
  { value: "80%", label: "read counts as a finished story" },
  { value: "100%", label: "verified U.S. nonprofits" },
];

export default function LoginHero() {
  return (
    <aside className="lh-root" aria-label="About NarLit">
      <div className="lh-glow lh-glow-a" aria-hidden="true" />
      <div className="lh-glow lh-glow-b" aria-hidden="true" />
      <span className="lh-place">
        <span className="lh-pin" aria-hidden="true" />
        San Francisco, California
      </span>

      <div className="lh-inner">
        <p className="lh-eyebrow">Reading with purpose</p>
        <h2 className="lh-headline">
          Every story you read <span className="lh-orange">funds</span> <span className="lh-teal">a nonprofit.</span>
        </h2>
        <p className="lh-lede">
          Read journalism written by the nonprofits who know their causes best, and the organization behind
          each article you finish gets paid for it.
        </p>

        <ol className="lh-steps">
          {STEPS.map((s, i) => (
            <li className="lh-step" key={s.title}>
              <span className="lh-step-icon">{s.icon}</span>
              <span className="lh-step-text">
                <strong>{s.title}</strong>
                <span>{s.body}</span>
              </span>
              <span className="lh-step-n" aria-hidden="true">{i + 1}</span>
            </li>
          ))}
        </ol>

        <div className="lh-story" aria-hidden="true">
          <div className="lh-story-top">
            <span className="lh-chip">Environment</span>
            <span className="lh-story-meta">3 min read</span>
          </div>
          <p className="lh-story-title">How a coastal town rebuilt its wetlands from scratch</p>
          <p className="lh-story-org">Habitat Voices</p>
          <div className="lh-meter">
            <span className="lh-meter-fill" />
            <span className="lh-meter-mark" />
          </div>
          <div className="lh-meter-row">
            <span>Reader progress</span>
            <span className="lh-funded">Funded Habitat Voices ✓</span>
          </div>
        </div>

        <ul className="lh-stats">
          {STATS.map((s) => (
            <li key={s.label}>
              <strong>{s.value}</strong>
              <span>{s.label}</span>
            </li>
          ))}
        </ul>
      </div>
      <p className="lh-credit">Photo: Golden Gate Bridge in fog · Unsplash, CC0, via Wikimedia Commons</p>
    </aside>
  );
}
