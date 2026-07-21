# SILAPKASAL Storyboard Content Guidelines

## 1. Purpose

The storyboard supplies navigation ideas, a proposed category structure,
article-title ideas, and mobile visual references for SILAPKASAL's future
information center. It does not supply complete or authoritative article
bodies. Every body, answer, contact record, and media asset must therefore be
prepared through the editorial process described below.

## 2. Language and Tone

All reader-facing editorial content must:

- Use natural Bahasa Indonesia with clear, formal wording.
- Maintain an empathetic and non-judgmental tone.
- Use short, readable paragraphs and clear headings.
- Avoid lorem ipsum and software-development language.
- Avoid sensational, intimidating, or blaming wording.
- Avoid assumptions about a reader's experience, identity, or decisions.
- Avoid guarantees of legal, medical, psychological, safety, or recovery outcomes.
- Explain options without pressuring a reader to report, disclose, or take a specific action.

## 3. Article Starter Draft Rules

An editorial starter draft may contain:

- A neutral introduction to the topic.
- A general educational explanation.
- Practical, non-sensitive steps that do not replace professional advice.
- A Consultation call to action only when its destination has been verified as safe.
- Editorial notes that identify facts, sources, and approvals still requiring verification.

An editorial starter draft must not contain:

- Fabricated quotations.
- Invented laws, regulations, article numbers, or institutional rules.
- Invented Quran verses, Hadith, translations, or source attributions.
- Invented statements attributed to scholars or professionals.
- Medical or psychological diagnoses.
- Emergency medical or definitive legal instructions.
- Promises of recovery, protection, legal success, or processing time.
- Fabricated first-person victim or survivor testimonies.
- Real Report, Case, Evidence, or investigation data.
- Names, NIMs, phone numbers, email addresses, or identifying details.

## 4. Sensitive Domain Rules

### Religious Content

- Verify every quoted source, translation, attribution, and contextual explanation.
- Quote only sources that an authorized reviewer can verify.
- Add a human-readable source citation before publication.
- Do not present one interpretation as absolute institutional doctrine without formal approval.
- Do not use religious framing to blame, pressure, or judge a person affected by violence.

### Legal Content

- Require legal review before publication.
- Verify that every referenced regulation is current at review time.
- Present material as general education, not definitive legal advice.
- Include an appropriate disclaimer and route readers to verified professional assistance.
- Do not invent sanctions, procedural steps, article numbers, responsible authorities, or likely outcomes.

### Psychological Content

- Require review by an appropriately qualified professional.
- Keep the content educational and non-diagnostic.
- Do not promise recovery or prescribe treatment.
- State clearly that the content does not replace professional support.
- Include a clear, non-coercive recommendation to seek verified help when appropriate.

### Testimonial Content

- Do not fabricate first-person survivor stories.
- Require explicit written consent for any real testimonial.
- Remove direct and indirect identifiers through a documented anonymization review.
- Review the risk of re-identification from combinations of dates, places, roles, and events.
- Use a composite educational narrative only when it is clearly labeled as a composite and formally approved.

### Crisis and Emergency Content

- Use only verified institutional contacts.
- Never seed fake emergency contacts, addresses, or service hours.
- Distinguish emergency support from normal Consultation operating hours.
- Do not claim continuous or immediate availability without explicit institutional approval.
- Keep emergency guidance concise, clear, and separated from ordinary informational content.

## 5. Article Structure

The recommended article model contains:

1. Category.
2. Title.
3. Excerpt.
4. Cover image.
5. Lead paragraph.
6. H2 sections.
7. H3 subsections where needed.
8. Lists for steps or grouped information.
9. A callout for an important safety or verification note.
10. A verified Consultation call to action.
11. Related articles.
12. Attachments where relevant and approved.

The page template owns the H1. Article bodies must not contain another H1.
Heading levels must not be skipped merely for visual styling.

## 6. Excerpt Rules

Each excerpt should:

- Use 120-220 characters where practical.
- Contain plain text only.
- Describe the specific topic rather than the whole category.
- Avoid clickbait and sensational language.
- Avoid unsupported legal, medical, psychological, or safety claims.
- Avoid internal workflow terminology that readers do not need.
- Remain understandable when displayed without the article body.

## 7. Cover Image Rules

- Use relevant, respectful visuals that support the educational purpose.
- Do not use graphic, exploitative, humiliating, or fear-based imagery.
- Do not show an identifiable victim or survivor.
- Do not imply that an image depicts a real Case unless its use is formally approved.
- Provide meaningful alternative text for informative images.
- Use an approved category fallback illustration when no cover image is available.
- Verify ownership, license, and permitted use before publication.
- Do not assume an external image is reusable merely because it is publicly accessible.

## 8. FAQ Rules

- Phrase each question clearly in the reader's language.
- Give a short answer first, followed by further explanation where needed.
- Do not disclose restricted investigation methods or internal operational details.
- Do not promise an outcome, response time, or completion duration.
- Ensure privacy and legal statements match executable application policy.
- Route readers to verified Consultation or reporting channels only when appropriate.
- Keep every seeded answer in `draft` until editorial and legal review is complete.

## 9. Consultation Rules

- Store Consultation entries as structured contact data rather than article prose.
- Assign a verified institutional owner to every record.
- Record the most recent verification date.
- Validate email and phone formats and verify that each destination belongs to the service.
- Accept only HTTPS appointment links.
- Do not place sensitive report details in prefilled WhatsApp messages or URLs.
- Ask for user confirmation before opening a phone or WhatsApp action.
- Do not describe a service as emergency support without explicit approval.
- Deactivate or correct records that can no longer be verified.

## 10. Approval and Publication

- All seeded articles begin with lifecycle value `draft`.
- All seeded FAQs begin with lifecycle value `draft`.
- Seeded content must never publish automatically.
- Campus content authored by an Admin requires Super Admin review before publication.
- Global content authored by a Super Admin still requires sanitization, validation, audit, and immutable publication history.
- A revision to published content must not replace the live version until that revision is approved.
- Rejection and revision reasons are mandatory wherever the editorial workflow supports those outcomes.
- Publication must identify the approved revision, approver, and publication time without exposing private reviewer data to readers.

## 11. Seeder Safety

- Use stable seed keys.
- Make reruns idempotent.
- Do not overwrite editorial changes after initial creation.
- Do not seed fake contact information.
- Do not seed fabricated authoritative body content.
- Do not seed realistic sensitive stories or Case-like narratives.
- Do not publish directly from a seeder.
- Record source metadata that identifies the storyboard or system seed.
- Set `requires_editorial_review = true` for every seeded article and FAQ.
- Treat the title catalog as planning input, not as publication approval.

## 12. Accessibility and Readability

- Maintain a correct heading hierarchy.
- Make cards, accordions, carousel controls, and actions keyboard accessible.
- Provide a visible focus indicator.
- Add meaningful alt text to informative images and empty alt text to decorative images.
- Maintain readable color contrast in light and dark themes.
- Provide mobile touch targets of at least 44 by 44 pixels.
- Allow long titles, labels, and body text to wrap responsively.
- Do not autoplay carousels, audio, or video.
- Respect reduced-motion preferences.
- Use a simple prose layout with comfortable line length and spacing.

## 13. PWA Cache Boundary

- Published information content may later use conservative caching with an explicit invalidation strategy.
- Authenticated API responses must not enter a shared cache.
- Drafts and editorial review pages must never be available through offline cache.
- Report data, Case data, Evidence, and private attachments must never be offline-cached.
- Consultation management data must never be offline-cached.
- A published public-facing Consultation projection may only be cached later if privacy and freshness controls are explicitly approved.
- Service-worker implementation is deferred to C4.

## 14. Flutter Alignment

- Keep the API and content model presentation-neutral.
- Use the mobile PWA as the first implementation reference.
- A future Flutter client should reuse the same category order, icon codes, article metadata, and approval-backed records.
- Flutter does not need to duplicate the PWA pixel for pixel.
- Platform-specific presentation must not change editorial lifecycle, access control, or publication meaning.

## 15. Editorial Checklist

- [ ] Title matches the approved topic and uses clear Bahasa Indonesia.
- [ ] Excerpt is specific, plain text, and free of unsupported claims.
- [ ] Body follows the approved structure and contains no H1.
- [ ] Sources and factual claims have been verified.
- [ ] Legal review is complete where required.
- [ ] Religious review is complete where required.
- [ ] Psychological review is complete where required.
- [ ] Testimonial consent and anonymization review are complete where required.
- [ ] Consultation contacts and verification date are current.
- [ ] Image rights and permitted use have been verified.
- [ ] Alternative text is present and meaningful.
- [ ] Content has passed sanitization and validation.
- [ ] Mobile preview has been reviewed.
- [ ] Desktop preview has been reviewed.
- [ ] Required approval has been recorded.
- [ ] The approved revision is ready for publication.
