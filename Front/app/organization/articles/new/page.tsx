"use client";

import { useEffect, useState } from "react";
import { apiFetch, requireRole } from "@/lib/api";
import { BrandLoader } from "@/components/BrandLoader";
import OrgNav from "@/components/OrgNav";
import ArticleEditor, { OrgArticleDetail } from "@/components/ArticleEditor";
import BackLink from "@/components/BackLink";

const COVER_IMAGE_MAX_BYTES = 4 * 1024 * 1024;
const COVER_IMAGE_ACCEPT = "image/jpeg,image/png,image/webp,image/gif";

export default function NewArticlePage() {
  const [checking, setChecking] = useState(true);
  const [approvalRequired, setApprovalRequired] = useState(true);

  useEffect(() => {
    requireRole(["organization"]).then(async (user) => {
      if (!user) return;
      // Whether "Publish" goes live directly or to the admin queue is an admin setting.
      try {
        const res = await apiFetch("/organization/articles?per_page=1");
        const data = await res.json();
        if (res.ok && typeof data.data?.approval_required === "boolean") {
          setApprovalRequired(data.data.approval_required);
        }
      } catch { /* keep the safe default */ }
      setChecking(false);
    });
  }, []);

  function handleSaved(saved: OrgArticleDetail) {
    // Jump back to the listing; a dismissible banner reflects the new status.
    window.location.replace(`/organization/articles?submitted=${encodeURIComponent(saved.status)}`);
  }

  if (checking) {
    return <BrandLoader />;
  }

  return (
    <div className="hm-shell" suppressHydrationWarning>
      <OrgNav active="articles" />

      <main className="hm-main">
        <div className="oa-wrap">
          <BackLink href="/organization/articles" className="su-link">← All articles</BackLink>
          <header className="oa-header">
            <h1 className="oa-title">New article</h1>
            <p className="oa-meta">Write your story, add a cover and photos, then publish it or save it as a draft.</p>
          </header>

          <ArticleEditor approvalRequired={approvalRequired} onSaved={handleSaved} />
        </div>
      </main>
    </div>
  );
}
