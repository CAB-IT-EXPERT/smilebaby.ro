# SmileBaby project guidance

## Public identity

- Canonical production URL: `https://smilebaby.ro/`
- Primary language: Romanian (`ro-RO`)
- Currency: Romanian leu (`RON`)
- Brand name: `SmileBaby`
- Public catalog: `https://smilebaby.ro/magazin`

## SEO and machine-readable content

- Keep product meta titles and meta descriptions editable from the product form in admin.
- Preserve manually written metadata. Generate metadata only when a field is empty or clearly outside useful display lengths.
- Keep canonical URLs on the apex HTTPS domain without `www`.
- Product prices, availability, reviews, shipping and return details in Schema.org must match the visible page and database.
- Public discovery files are `/robots.txt`, `/sitemap.xml`, `/llms.txt`, `/llms-full.txt` and `/agents.md`.
- Checkout, customer accounts, order tracking, admin and payment-result URLs must remain `noindex` and outside public sitemaps.

## Validation

- Lint changed PHP files before deployment.
- Validate generated XML and JSON-LD after SEO changes.
- Do not claim guaranteed rankings or guaranteed inclusion in AI-generated answers.
