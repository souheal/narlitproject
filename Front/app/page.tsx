import PublicNav from "@/components/PublicNav";
import PublicFooter from "@/components/PublicFooter";
import { categoryIcon } from "@/lib/categories";

const STEPS = [
  { icon: "💳", title: "Subscribe", body: "Choose monthly or yearly. Your subscription funds every nonprofit whose stories you read." },
  { icon: "📖", title: "Read stories", body: "Discover journalism written by nonprofits about the causes they know best." },
  { icon: "🤝", title: "Fund their mission", body: "Each completed read redirects part of your subscription to that nonprofit." },
];

const CAUSES = [
  { icon: "⚖️", label: "Civil Rights" },
  { icon: "🍽️", label: "Food Security" },
  { icon: "🏥", label: "Healthcare" },
  { icon: "🎓", label: "Education" },
  { icon: "🌍", label: "Environment" },
  { icon: "🏠", label: "Housing" },
  { icon: "🕊️", label: "Refugees" },
  { icon: "👨‍👩‍👧", label: "Women & Children" },
];

const DEFAULT_IMPACT_SPLIT = {
  nonprofit_percentage: 33,
  operations_percentage: 33,
  growth_percentage: 34,
};

const DEFAULT_MONTHLY_PRICE = 7;

interface Plan {
  key: string;
  name: string;
  billing_interval: "monthly" | "yearly";
  display_price: string;
  stripe_price_id: string | null;
  enabled?: boolean;
}

interface ImpactSplit {
  nonprofit_percentage: number;
  operations_percentage: number;
  growth_percentage: number;
}

interface FeaturedArticle {
  public_id: string;
  title: string;
  excerpt: string | null;
  category: string | null;
  read_time_minutes: number | null;
  organization: { name: string | null };
}

const FALLBACK_FEATURED: FeaturedArticle[] = [
  {
    public_id: "sample-1",
    title: "How a coastal town rebuilt its wetlands from scratch",
    excerpt: "A grassroots effort transformed 12 acres of degraded shoreline into a living barrier against storm surges — and brought the birds back.",
    category: "Environment",
    read_time_minutes: 3,
    organization: { name: "Habitat Voices" },
  },
  {
    public_id: "sample-2",
    title: "The kitchen that feeds 900 kids a week — for less than $0.60 a meal",
    excerpt: "Inside a co-op kitchen that turned cafeteria surplus into a summer lifeline for families across three counties.",
    category: "Food Security",
    read_time_minutes: 4,
    organization: { name: "Meals For All" },
  },
  {
    public_id: "sample-3",
    title: "What we learned funding 40 first-generation college students",
    excerpt: "The application asked one unusual question — and it changed how we picked scholarship winners. Completion rate jumped from 68% to 91%.",
    category: "Education",
    read_time_minutes: 3,
    organization: { name: "Bright Futures Fund" },
  },
];

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

async function fetchPlans(): Promise<Plan[] | null> {
  try {
    const res = await fetch(`${API_BASE_URL}/subscription/plans`, {
      next: { revalidate: 300 },
    });
    if (!res.ok) return null;
    const data = await res.json();
    const plans = data?.data?.plans;
    return Array.isArray(plans) ? plans : null;
  } catch {
    return null;
  }
}

async function fetchFeaturedArticles(): Promise<FeaturedArticle[]> {
  // GET /articles/featured?limit=3 — admin-featured articles first, newest published
  // after that. The sample content stays as a fallback for when the API is unreachable
  // or no article has been published yet, so the section never renders empty.
  try {
    const res = await fetch(`${API_BASE_URL}/articles/featured?limit=3`, {
      next: { revalidate: 300 },
    });
    if (!res.ok) return FALLBACK_FEATURED;
    const data = await res.json();
    const articles = data?.data?.articles;
    if (Array.isArray(articles) && articles.length > 0) {
      return articles.slice(0, 3);
    }
    return FALLBACK_FEATURED;
  } catch {
    return FALLBACK_FEATURED;
  }
}

async function fetchImpactSplit(): Promise<ImpactSplit> {
  // GET /settings/public returns { data: { impact_split: {...} } } from the platform
  // settings. Defaults are kept as a fallback for when the API is unreachable.
  try {
    const res = await fetch(`${API_BASE_URL}/settings/public`, {
      next: { revalidate: 300 },
    });
    if (!res.ok) return DEFAULT_IMPACT_SPLIT;
    const data = await res.json();
    const split = data?.data?.impact_split;
    if (
      split &&
      typeof split.nonprofit_percentage === "number" &&
      typeof split.operations_percentage === "number" &&
      typeof split.growth_percentage === "number"
    ) {
      return split;
    }
    return DEFAULT_IMPACT_SPLIT;
  } catch {
    return DEFAULT_IMPACT_SPLIT;
  }
}

function centsToDollars(value: string): number {
  const parsed = parseFloat(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

export default async function HomePage() {
  const [plans, impactSplit, featured] = await Promise.all([
    fetchPlans(),
    fetchImpactSplit(),
    fetchFeaturedArticles(),
  ]);

  const enabledPlans = (plans ?? []).filter((p) => p.enabled !== false);
  const monthly = enabledPlans.find((p) => p.billing_interval === "monthly");
  const yearly = enabledPlans.find((p) => p.billing_interval === "yearly");

  const monthlyPrice = monthly ? centsToDollars(monthly.display_price) : DEFAULT_MONTHLY_PRICE;
  const yearlyPrice = yearly ? centsToDollars(yearly.display_price) : monthlyPrice * 12;
  const yearlyMonthly = yearlyPrice / 12;
  const yearlySavings = Math.max(0, Math.round(((monthlyPrice * 12 - yearlyPrice) / (monthlyPrice * 12)) * 100));

  const nonprofitShare = ((impactSplit.nonprofit_percentage / 100) * monthlyPrice).toFixed(2);

  return (
    <div className="hm-shell">
      <PublicNav />

      <main>
        {/* Hero */}
        <section
          style={{
            padding: "80px 24px 60px",
            textAlign: "center",
            background: "linear-gradient(135deg, rgba(255,138,71,0.15), rgba(17,182,200,0.15))",
          }}
        >
          <div style={{ maxWidth: 780, margin: "0 auto" }}>
            <p style={{ color: "var(--orange)", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.1em", fontSize: "0.8rem", marginBottom: 12 }}>
              Reading with purpose
            </p>
            <h1 style={{ fontSize: "3rem", lineHeight: 1.15, margin: 0, fontWeight: 900 }}>
              Every story you read{" "}
              <span style={{ color: "var(--orange)" }}>funds</span>{" "}
              <span style={{ color: "var(--teal)" }}>a nonprofit</span>.
            </h1>
            <p style={{ fontSize: "1.15rem", color: "var(--muted)", marginTop: 20, lineHeight: 1.6 }}>
              NarLit lets you subscribe once and support nonprofits every time you finish an article.
              No extra donations — just your reading, turning stories into impact.
            </p>
            <div style={{ display: "flex", justifyContent: "center", gap: 12, marginTop: 32, flexWrap: "wrap" }}>
              <a href="/signup" className="hm-article-btn" style={{ padding: "14px 28px", fontSize: "0.95rem" }}>
                Start reading →
              </a>
              <a href="/signup/organizer" className="hm-pager-btn" style={{ padding: "14px 28px", textDecoration: "none" }}>
                I&apos;m a nonprofit
              </a>
            </div>
            <p style={{ marginTop: 20, color: "var(--muted)", fontSize: "0.88rem" }}>
              From <strong style={{ color: "var(--foreground)" }}>${monthlyPrice}/month</strong>
              {" · "}Cancel anytime{" · "}No hidden fees
            </p>
          </div>
        </section>

        {/* How it works */}
        <section id="how-it-works" style={{ padding: "60px 24px" }}>
          <div style={{ maxWidth: 1000, margin: "0 auto" }}>
            <h2 style={{ textAlign: "center", fontSize: "2rem", marginBottom: 8 }}>How it works</h2>
            <p style={{ textAlign: "center", color: "var(--muted)", marginBottom: 40 }}>Three steps. That&apos;s it.</p>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(240px, 1fr))", gap: 20 }}>
              {STEPS.map((s, i) => (
                <div
                  key={s.title}
                  style={{
                    padding: 28,
                    background: "var(--panel)",
                    border: "1px solid var(--line)",
                    borderRadius: 16,
                    textAlign: "center",
                  }}
                >
                  <div style={{ fontSize: "3rem", marginBottom: 12 }}>{s.icon}</div>
                  <div style={{ fontSize: "0.72rem", color: "var(--muted)", fontWeight: 800, letterSpacing: "0.1em" }}>
                    STEP {i + 1}
                  </div>
                  <h3 style={{ margin: "6px 0 10px", fontSize: "1.15rem" }}>{s.title}</h3>
                  <p style={{ margin: 0, color: "var(--muted)", fontSize: "0.9rem", lineHeight: 1.55 }}>{s.body}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Featured stories preview */}
        {featured.length > 0 && (
          <section className="hm-featured">
            <div className="hm-featured-inner">
              <div className="hm-featured-head">
                <p className="hm-featured-eyebrow">Featured stories</p>
                <h2 className="hm-featured-title">A taste of what nonprofits publish here</h2>
                <p className="hm-featured-sub">
                  Real work by real organizations. Preview any story — reading the full piece is one subscription away.
                </p>
              </div>

              <div className="hm-featured-grid">
                {featured.map((a) => (
                  <a
                    key={a.public_id}
                    href={`/articles/${a.public_id}`}
                    className="hm-featured-card"
                  >
                    <div className="hm-featured-card-top">
                      <span className="hm-featured-category">
                        {a.category ? `${categoryIcon(a.category)} ${a.category}` : "📰 Story"}
                      </span>
                      <span className="hm-featured-org">
                        {a.organization?.name ?? "NarLit"}
                      </span>
                    </div>
                    <h3 className="hm-featured-card-title">{a.title}</h3>
                    <p className="hm-featured-card-excerpt">{a.excerpt}</p>
                    <div className="hm-featured-card-foot">
                      <span className="hm-featured-time">
                        ⏱️ {a.read_time_minutes ?? 3} min read
                      </span>
                      <span className="hm-featured-cta">Read preview →</span>
                    </div>
                  </a>
                ))}
              </div>

              <div className="hm-featured-explore">
                <a href="/signup" className="hm-featured-explore-btn">
                  Explore all stories →
                </a>
              </div>
            </div>
          </section>
        )}

        {/* Impact stats (live from settings) */}
        <section style={{ padding: "60px 24px", background: "var(--panel)" }}>
          <div style={{ maxWidth: 900, margin: "0 auto", textAlign: "center" }}>
            <p style={{ color: "var(--teal)", fontWeight: 800, textTransform: "uppercase", letterSpacing: "0.1em", fontSize: "0.8rem" }}>
              Where your subscription goes
            </p>
            <h2 style={{ fontSize: "1.7rem", marginTop: 8 }}>
              About ${nonprofitShare} of every ${monthlyPrice.toFixed(2)} goes directly to nonprofits.
            </h2>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 20, marginTop: 30 }}>
              <Slice pct={impactSplit.nonprofit_percentage} color="var(--orange)" label="Nonprofits" />
              <Slice pct={impactSplit.operations_percentage} color="var(--teal)" label="Operations" />
              <Slice pct={impactSplit.growth_percentage} color="#7c5cbf" label="Growth" />
            </div>
          </div>
        </section>

        {/* Pricing */}
        <section id="pricing" className="hm-pricing">
          <div className="hm-pricing-inner">
            <div className="hm-pricing-head">
              <p className="hm-pricing-eyebrow">Pricing</p>
              <h2 className="hm-pricing-title">One subscription. Every story counts.</h2>
              <p className="hm-pricing-sub">Choose monthly to try it out, or yearly to save and lock in your impact.</p>
            </div>

            <div className="hm-pricing-grid">
              <PlanCard
                name={monthly?.name ?? "Monthly"}
                price={monthlyPrice}
                unit="/month"
                features={[
                  "Unlimited access to every published story",
                  `About $${nonprofitShare} of every $${monthlyPrice.toFixed(2)} funds nonprofits directly`,
                  "Bookmark, track, and share what you read",
                  "Cancel anytime — no lock-in",
                ]}
                ctaHref="/signup"
                ctaLabel="Start monthly"
                variant="secondary"
              />
              <PlanCard
                name={yearly?.name ?? "Yearly"}
                price={yearlyMonthly}
                unit="/month, billed yearly"
                subLabel={`$${yearlyPrice.toFixed(2)} once a year`}
                saveLabel={yearlySavings > 0 ? `Save ${yearlySavings}% vs monthly` : undefined}
                features={[
                  "Everything in Monthly",
                  "Support your favorite nonprofits year-round",
                  "Founding-member badge on your profile",
                  "Priority access to new features",
                ]}
                ctaHref="/signup"
                ctaLabel="Start yearly"
                variant="primary"
                featured
              />
            </div>

            <p className="hm-pricing-fine">
              Secure checkout by Stripe · Prices in USD · Cancel from your account at any time.
            </p>
          </div>
        </section>

        {/* Causes */}
        <section style={{ padding: "60px 24px" }}>
          <div style={{ maxWidth: 1000, margin: "0 auto" }}>
            <h2 style={{ textAlign: "center", fontSize: "2rem" }}>Stories from causes that matter</h2>
            <p style={{ textAlign: "center", color: "var(--muted)", marginBottom: 30 }}>
              NarLit publishes work by vetted nonprofits across the issues shaping our world.
            </p>
            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(140px, 1fr))", gap: 12 }}>
              {CAUSES.map((c) => (
                <div
                  key={c.label}
                  style={{
                    padding: "20px 12px",
                    background: "var(--panel)",
                    border: "1px solid var(--line)",
                    borderRadius: 14,
                    textAlign: "center",
                  }}
                >
                  <div style={{ fontSize: "1.8rem" }}>{c.icon}</div>
                  <div style={{ fontSize: "0.82rem", fontWeight: 700, marginTop: 6 }}>{c.label}</div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Final CTA */}
        <section
          style={{
            padding: "80px 24px",
            textAlign: "center",
            background: "linear-gradient(135deg, var(--orange), var(--teal))",
            color: "white",
          }}
        >
          <div style={{ maxWidth: 640, margin: "0 auto" }}>
            <h2 style={{ fontSize: "2rem", margin: 0 }}>Reading changes something.</h2>
            <p style={{ marginTop: 12, opacity: 0.9, fontSize: "1.05rem" }}>
              Start today. Cancel anytime. Every article makes a real difference.
            </p>
            <a
              href="/signup"
              style={{
                display: "inline-block",
                marginTop: 24,
                padding: "14px 28px",
                background: "white",
                color: "var(--orange)",
                fontWeight: 800,
                borderRadius: 999,
                textDecoration: "none",
              }}
            >
              Start reading →
            </a>
          </div>
        </section>
      </main>

      <PublicFooter />
    </div>
  );
}

function Slice({ pct, color, label }: { pct: number; color: string; label: string }) {
  return (
    <div>
      <div style={{ height: 10, background: "var(--line)", borderRadius: 5, overflow: "hidden" }}>
        <div style={{ width: `${pct}%`, height: "100%", background: color }} />
      </div>
      <div style={{ marginTop: 10, fontSize: "1.4rem", fontWeight: 900 }}>{pct}%</div>
      <div style={{ fontSize: "0.85rem", color: "var(--muted)" }}>{label}</div>
    </div>
  );
}

function PlanCard({
  name,
  price,
  unit,
  subLabel,
  saveLabel,
  features,
  ctaHref,
  ctaLabel,
  variant,
  featured,
}: {
  name: string;
  price: number;
  unit: string;
  subLabel?: string;
  saveLabel?: string;
  features: string[];
  ctaHref: string;
  ctaLabel: string;
  variant: "primary" | "secondary";
  featured?: boolean;
}) {
  return (
    <div className={`hm-plan-card${featured ? " hm-plan-card-featured" : ""}`}>
      {featured && <span className="hm-plan-badge">Best value</span>}
      <p className="hm-plan-name">{name}</p>
      <div className="hm-plan-price">
        <span className="hm-plan-price-value">${price.toFixed(price % 1 === 0 ? 0 : 2)}</span>
        <span className="hm-plan-price-unit">{unit}</span>
      </div>
      {subLabel && <p className="hm-plan-save" style={{ color: "var(--muted)", fontWeight: 500 }}>{subLabel}</p>}
      {saveLabel && <p className="hm-plan-save">{saveLabel}</p>}
      <ul className="hm-plan-features">
        {features.map((f) => (
          <li key={f}>{f}</li>
        ))}
      </ul>
      <a href={ctaHref} className={`hm-plan-cta hm-plan-cta-${variant}`}>
        {ctaLabel} →
      </a>
    </div>
  );
}
