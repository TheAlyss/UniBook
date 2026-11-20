{{-- UniBook theme, taken from the design mockup (docs/mockup/index.html).
     Mockup-only parts (.ub-page, .ub-theme-toggle) are left out. --}}
<style>
:root,
[data-theme="purple"] {
  --ub-primary: #4b2c83;
  --ub-primary-rgb: 75, 44, 131;
  --ub-primary-dark: #3a2166;
  --ub-primary-soft: #efe9f8;
  --ub-accent: #f5b301;
  --ub-accent-soft: #fff4d1;
  --ub-price: #8a5a00;
  --ub-bg: #f1eff7;
  --ub-surface: #ffffff;
  --ub-text: #1f1a2e;
  --ub-muted: #6b6780;
  --ub-border: #dedaea;
  --ub-autofill: #fff8db;
}
[data-theme="lavender"] {
  --ub-primary: #23306b;
  --ub-primary-rgb: 35, 48, 107;
  --ub-primary-dark: #18224f;
  --ub-primary-soft: #ebeafb;
  --ub-accent: #9b87f5;
  --ub-accent-soft: #f0ecff;
  --ub-price: #5a3fc0;
  --ub-bg: #efeefa;
  --ub-surface: #ffffff;
  --ub-text: #1b2140;
  --ub-muted: #646a85;
  --ub-border: #dcdbee;
  --ub-autofill: #fff8db;
}
:root {
  --ub-radius: 16px;
  --ub-radius-sm: 12px;
  --ub-shadow: 0 1px 2px rgba(31, 26, 46, .06), 0 6px 24px rgba(31, 26, 46, .08);
  --ub-shadow-hover: 0 12px 32px rgba(31, 26, 46, .12);
  --ub-success: #1f8a5b;
  --ub-warning: #b7791f;
}

/* Bootstrap overrides (purple instead of default blue) */
[data-theme] {
  --bs-body-font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", sans-serif;
  --bs-body-bg: var(--ub-bg);
  --bs-body-color: var(--ub-text);
  --bs-primary-rgb: var(--ub-primary-rgb);
  --bs-link-color-rgb: var(--ub-primary-rgb);
  --bs-link-hover-color-rgb: var(--ub-primary-rgb);
  --bs-link-color: var(--ub-primary);
  --bs-link-hover-color: var(--ub-primary-dark);
  --bs-border-color: var(--ub-border);
  --bs-secondary-color: var(--ub-muted);
}
.btn { border-radius: var(--ub-radius-sm); font-weight: 600; }
/* Mockup padding, but small/large buttons keep their own size. */
.btn:not(.btn-sm):not(.btn-lg) { padding: .6rem 1.1rem; }
.btn-primary {
  --bs-btn-bg: var(--ub-primary); --bs-btn-border-color: var(--ub-primary);
  --bs-btn-hover-bg: var(--ub-primary-dark); --bs-btn-hover-border-color: var(--ub-primary-dark);
  --bs-btn-active-bg: var(--ub-primary-dark); --bs-btn-active-border-color: var(--ub-primary-dark);
  --bs-btn-disabled-bg: var(--ub-primary); --bs-btn-disabled-border-color: var(--ub-primary);
}
.btn-outline-primary {
  --bs-btn-color: var(--ub-primary); --bs-btn-border-color: var(--ub-primary);
  --bs-btn-hover-bg: var(--ub-primary); --bs-btn-hover-border-color: var(--ub-primary);
  --bs-btn-active-bg: var(--ub-primary-dark); --bs-btn-active-border-color: var(--ub-primary-dark);
}
.btn-link { --bs-btn-color: var(--ub-primary); --bs-btn-hover-color: var(--ub-primary-dark); --bs-btn-active-color: var(--ub-primary-dark); font-weight: 600; }
.btn-soft { background: var(--ub-primary-soft); color: var(--ub-primary); border: 0; }
.btn-soft:hover { background: var(--ub-primary); color: #fff; }
.btn-whatsapp { background: #e8f8ee; color: #137a3f; border: 0; }
.btn-whatsapp:hover { background: #1fa855; color: #fff; }
.form-control, .form-select { border-radius: var(--ub-radius-sm); padding: .65rem .9rem; border-color: var(--ub-border); background-color: var(--ub-surface); }
.form-control:focus, .form-select:focus {
  border-color: var(--ub-primary);
  box-shadow: 0 0 0 .25rem rgba(var(--ub-primary-rgb), .15);
}
.form-label { font-weight: 600; font-size: .9rem; margin-bottom: .35rem; }
.text-primary { color: var(--ub-primary) !important; }

/* Layout */
body { padding-bottom: 0; }
.ub-card { background: var(--ub-surface); border: 1px solid var(--ub-border); border-radius: var(--ub-radius); box-shadow: var(--ub-shadow); }
.ub-section-title { font-weight: 700; font-size: 1.15rem; margin: 0; }
.ub-muted { color: var(--ub-muted); }

/* Navbar */
.ub-navbar { background: var(--ub-surface); border-bottom: 1px solid var(--ub-border); }
.ub-logo { font-weight: 800; font-size: 1.35rem; color: var(--ub-primary); text-decoration: none; display: flex; align-items: center; gap: .5rem; }
.ub-logo-mark { width: 34px; height: 34px; border-radius: 10px; background: var(--ub-primary); color: var(--ub-accent); display: grid; place-items: center; font-size: 1.1rem; }
.ub-nav-link { color: var(--ub-muted); font-weight: 600; text-decoration: none; padding: .5rem .9rem; border-radius: 999px; }
.ub-nav-link:hover { color: var(--ub-primary); }
.ub-nav-link.active { color: var(--ub-primary); background: var(--ub-primary-soft); }
.ub-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--ub-accent-soft); color: var(--ub-price); display: grid; place-items: center; font-weight: 700; font-size: .85rem; }

/* Hero */
.ub-hero { background: linear-gradient(135deg, var(--ub-primary) 0%, var(--ub-primary-dark) 100%); color: #fff; border-radius: 0 0 28px 28px; padding: 2.5rem 0 3.5rem; position: relative; overflow: hidden; }
.ub-hero::after { content: ""; position: absolute; right: -80px; top: -80px; width: 260px; height: 260px; border-radius: 50%; background: var(--ub-accent); opacity: .15; }
.ub-hero h1 { font-weight: 800; font-size: clamp(1.6rem, 4vw, 2.4rem); }
.ub-hero h1 span { color: var(--ub-accent); }
.ub-search { background: #fff; border-radius: 999px; padding: .35rem .35rem .35rem 1.2rem; display: flex; align-items: center; gap: .5rem; box-shadow: 0 10px 30px rgba(0,0,0,.18); }
.ub-search input { border: 0; outline: 0; flex: 1; min-width: 0; font-size: 1rem; color: var(--ub-text); background: transparent; }
.ub-search .btn { border-radius: 999px; }
.ub-chips { display: flex; gap: .5rem; overflow-x: auto; padding-bottom: .25rem; scrollbar-width: none; }
.ub-chip { flex: 0 0 auto; border: 1px solid rgba(255,255,255,.35); background: rgba(255,255,255,.1); color: #fff; border-radius: 999px; padding: .45rem .95rem; font-weight: 600; font-size: .88rem; display: inline-flex; align-items: center; gap: .4rem; text-decoration: none; }
.ub-chip:hover { background: rgba(255,255,255,.2); color: #fff; }
.ub-chip.active { background: var(--ub-accent); border-color: var(--ub-accent); color: #1f1a2e; }

/* Book card */
.ub-book-card { background: var(--ub-surface); border: 1px solid var(--ub-border); border-radius: var(--ub-radius); overflow: hidden; height: 100%; display: flex; flex-direction: column; transition: transform .15s ease, box-shadow .15s ease; cursor: pointer; text-decoration: none; color: inherit; }
.ub-book-card:hover { transform: translateY(-3px); box-shadow: var(--ub-shadow-hover); color: inherit; }
.ub-book-card .ub-cover-wrap { padding: .75rem .75rem 0; position: relative; }
.ub-book-card .ub-body { padding: .8rem .9rem 1rem; display: flex; flex-direction: column; gap: .3rem; flex: 1; }
.ub-book-title { font-weight: 700; font-size: .98rem; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.ub-book-author { color: var(--ub-muted); font-size: .84rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-height: 1.2em; }
.ub-price { color: var(--ub-price); font-weight: 800; font-size: 1.25rem; }
.ub-price small { font-size: .7em; font-weight: 700; margin-right: 2px; }
.ub-seller { font-size: .8rem; color: var(--ub-muted); display: flex; align-items: center; gap: .35rem; margin-top: auto; padding-top: .4rem; border-top: 1px dashed var(--ub-border); }
.ub-seller .bi-star-fill { color: var(--ub-accent); }

/* Covers (2:3). Real covers use <img class="ub-cover">; .ub-cover-empty when there is none */
.ub-cover { aspect-ratio: 2 / 3; width: 100%; border-radius: 10px; object-fit: cover; display: block; }
.ub-cover-art { position: relative; overflow: hidden; color: #fff; padding: 2.6rem 10% 12%; display: flex; flex-direction: column; justify-content: space-between; box-shadow: inset 6px 0 0 rgba(0,0,0,.18); }
.ub-cover-art .t { font-weight: 800; font-size: clamp(.8rem, 1.6vw, 1.05rem); line-height: 1.2; text-transform: uppercase; letter-spacing: .02em; }
.ub-cover-art .a { font-size: .7rem; opacity: .85; font-weight: 600; }
.ub-cover-art::after { content: ""; position: absolute; left: 10%; right: 10%; top: 48%; height: 3px; background: rgba(255,255,255,.55); }
.ub-result .ub-cover, .ub-mini-card .ub-cover, .ub-cover-preview .ub-cover { align-self: flex-start; min-width: 0; }
.ub-result .ub-cover { width: 64px !important; max-width: 64px; flex: 0 0 64px; }
.ub-mini-card .ub-cover { width: 70px !important; max-width: 70px; flex: 0 0 70px; }
.ub-cover-preview .ub-cover { width: 54px !important; max-width: 54px; flex: 0 0 54px; }
.ub-result .ub-cover-art, .ub-mini-card .ub-cover-art, .ub-cover-preview .ub-cover-art { padding: 10px 6px; border-radius: 6px; }
.ub-result .ub-cover-art .t, .ub-mini-card .ub-cover-art .t, .ub-cover-preview .ub-cover-art .t { font-size: .42rem; }
.ub-result .ub-cover-art .a, .ub-mini-card .ub-cover-art .a, .ub-cover-preview .ub-cover-art .a { display: none; }
.ub-result .ub-cover-empty i, .ub-mini-card .ub-cover-empty i, .ub-cover-preview .ub-cover-empty i { font-size: 1.2rem; }
.ub-result .ub-cover-art::after, .ub-mini-card .ub-cover-art::after, .ub-cover-preview .ub-cover-art::after { display: none; }
.ub-uppercase { text-transform: uppercase; }
.ub-uppercase::placeholder { text-transform: none; }
.ub-cover-empty { background: repeating-linear-gradient(45deg, var(--ub-primary-soft), var(--ub-primary-soft) 10px, #f6f3fb 10px, #f6f3fb 20px); color: var(--ub-primary); display: grid; place-items: center; text-align: center; font-size: .78rem; font-weight: 600; }
.ub-cover-empty i { font-size: 2rem; display: block; margin-bottom: .3rem; }

/* Badges */
.ub-badge { display: inline-flex; align-items: center; gap: .3rem; border-radius: 999px; padding: .2rem .6rem; font-size: .72rem; font-weight: 700; line-height: 1.4; }
.ub-badge-course { background: var(--ub-primary-soft); color: var(--ub-primary); }
.ub-badge-cat { background: #f1f0f5; color: var(--ub-muted); }
.ub-badge-cond { position: absolute; top: 1.2rem; left: 1.2rem; background: rgba(255,255,255,.95); color: var(--ub-text); box-shadow: 0 2px 8px rgba(0,0,0,.12); }
.ub-badge-cond.like-new i { color: var(--ub-success); }
.ub-badge-cond.good i { color: #2f74c0; }
.ub-badge-cond.fair i { color: var(--ub-warning); }
.ub-badge-source { background: #f1f0f5; color: var(--ub-muted); }
.ub-badge-source.catalogue { background: var(--ub-primary-soft); color: var(--ub-primary); }
.ub-match-high { background: #e3f5ec; color: var(--ub-success); }
.ub-match-medium { background: #fdf1dc; color: var(--ub-warning); }
.ub-match-low { background: #efeff2; color: #6f6f7a; }
.ub-badge-ai { background: linear-gradient(90deg, var(--ub-primary-soft), var(--ub-accent-soft)); color: var(--ub-primary); }

/* Empty state */
.ub-empty { text-align: center; padding: 3rem 1rem; }
.ub-empty-icon { width: 84px; height: 84px; border-radius: 50%; background: var(--ub-primary-soft); color: var(--ub-primary); display: grid; place-items: center; font-size: 2.2rem; margin: 0 auto 1rem; }

/* Steps */
.ub-steps { display: flex; align-items: center; gap: .5rem; }
.ub-step { display: flex; align-items: center; gap: .5rem; font-weight: 600; font-size: .9rem; color: var(--ub-muted); white-space: nowrap; }
.ub-step .n { width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; background: #fff; border: 2px solid var(--ub-border); font-size: .85rem; }
.ub-step.active { color: var(--ub-primary); }
.ub-step.active .n { border-color: var(--ub-primary); background: var(--ub-primary); color: #fff; }
.ub-step.done .n { border-color: var(--ub-primary); color: var(--ub-primary); background: var(--ub-primary-soft); }
.ub-step-line { flex: 1; height: 2px; background: var(--ub-border); min-width: 16px; }

/* Scan / drop zone */
.ub-dropzone { border: 2px dashed rgba(var(--ub-primary-rgb), .35); background: var(--ub-primary-soft); border-radius: var(--ub-radius); padding: 1.6rem 1rem; text-align: center; cursor: pointer; transition: background .15s, border-color .15s; }
.ub-dropzone:hover, .ub-dropzone.drag { border-color: var(--ub-primary); background: #e8e0f6; }
[data-theme="lavender"] .ub-dropzone:hover, [data-theme="lavender"] .ub-dropzone.drag { background: #e1e0f7; }
.ub-dropzone-icon { width: 64px; height: 64px; border-radius: 18px; background: var(--ub-primary); color: #fff; display: grid; place-items: center; font-size: 1.7rem; margin: 0 auto .8rem; }
.ub-scan-preview { width: 72px; border-radius: 8px; box-shadow: var(--ub-shadow); }

/* Loading steps */
.ub-progress-list { list-style: none; padding: 0; margin: 0; display: grid; gap: .55rem; }
.ub-progress-list li { display: flex; align-items: center; gap: .6rem; color: var(--ub-muted); font-weight: 500; }
.ub-progress-list li .ic { width: 22px; display: grid; place-items: center; }
.ub-progress-list li.doing { color: var(--ub-primary); font-weight: 700; }
.ub-progress-list li.done { color: var(--ub-success); }

/* Result cards */
.ub-result { display: flex; gap: .9rem; padding: .8rem; border: 2px solid var(--ub-border); border-radius: var(--ub-radius-sm); background: var(--ub-surface); cursor: pointer; position: relative; transition: border-color .15s, box-shadow .15s; text-align: left; width: 100%; }
.ub-result:hover { border-color: rgba(var(--ub-primary-rgb), .45); box-shadow: var(--ub-shadow); }
.ub-result.selected { border-color: var(--ub-primary); box-shadow: 0 0 0 4px rgba(var(--ub-primary-rgb), .12); }
.ub-result .ub-cover { width: 64px; flex: 0 0 64px; }
.ub-result-check { position: absolute; top: .6rem; right: .6rem; width: 26px; height: 26px; border-radius: 50%; border: 2px solid var(--ub-border); display: grid; place-items: center; color: transparent; background: #fff; }
.ub-result.selected .ub-result-check { background: var(--ub-primary); border-color: var(--ub-primary); color: #fff; }

/* AI read panel */
.ub-ai-panel { border: 1px solid var(--ub-border); border-radius: var(--ub-radius-sm); background: linear-gradient(180deg, #fff, var(--ub-bg)); padding: 1rem; }
.ub-ai-panel dl { display: grid; grid-template-columns: 92px 1fr; gap: .35rem .75rem; margin: 0 0 .9rem; font-size: .9rem; }
.ub-ai-panel dt { color: var(--ub-muted); font-weight: 600; }
.ub-ai-panel dd { margin: 0; font-weight: 600; }
.ub-ai-panel dd.empty { color: var(--ub-muted); font-weight: 400; font-style: italic; }

/* Listing form */
.ub-sticky { position: sticky; top: 84px; }
.ub-autofilled { background-color: var(--ub-autofill) !important; border-color: #f0d77a !important; }
.ub-autofill-note { font-size: .76rem; color: #8a6d00; display: none; margin-top: .25rem; }
.ub-autofill-note.show { display: block; }
.ub-cover-preview { display: flex; gap: .8rem; align-items: center; padding: .7rem; background: var(--ub-bg); border-radius: var(--ub-radius-sm); }
.ub-cover-preview .ub-cover { width: 54px; }
.ub-mini-card { display: flex; gap: .8rem; padding: .8rem; border: 1px solid var(--ub-border); border-radius: var(--ub-radius-sm); background: #fff; }
.ub-mini-card .ub-cover { width: 70px; flex: 0 0 70px; }

/* Detail page */
.ub-detail-cover { max-width: 340px; margin: 0 auto; }
.ub-detail-cover .ub-cover-art .t { font-size: 1.6rem; }
.ub-spec { display: grid; grid-template-columns: 120px 1fr; gap: .5rem 1rem; font-size: .92rem; }
.ub-spec dt { color: var(--ub-muted); font-weight: 500; }
.ub-spec dd { margin: 0; font-weight: 600; }
.ub-price-box .ub-price { font-size: 2rem; }
.ub-pay-note { display: flex; align-items: center; gap: .5rem; background: var(--ub-accent-soft); color: var(--ub-price); border-radius: var(--ub-radius-sm); padding: .6rem .8rem; font-weight: 600; font-size: .88rem; }
.ub-map { border-radius: var(--ub-radius-sm); overflow: hidden; border: 1px solid var(--ub-border); background: #eef2ea; }
.ub-map > svg { display: block; width: 100%; height: auto; } /* static map only, not Leaflet's own SVGs */
.ub-meetup { display: flex; align-items: center; gap: .8rem; padding: .7rem .8rem; border: 2px solid var(--ub-border); border-radius: var(--ub-radius-sm); cursor: pointer; background: #fff; width: 100%; text-align: left; }
.ub-meetup:hover { border-color: rgba(var(--ub-primary-rgb), .45); }
.ub-meetup.selected { border-color: var(--ub-primary); background: var(--ub-primary-soft); }
.ub-meetup-ic { width: 38px; height: 38px; border-radius: 10px; background: var(--ub-primary-soft); color: var(--ub-primary); display: grid; place-items: center; flex: 0 0 38px; }
.ub-meetup.selected .ub-meetup-ic { background: var(--ub-primary); color: #fff; }

/* Order timeline */
.ub-timeline { display: flex; align-items: flex-start; }
.ub-tl-step { flex: 1; text-align: center; position: relative; font-size: .82rem; font-weight: 600; color: var(--ub-muted); }
.ub-tl-step::before { content: ""; position: absolute; top: 17px; left: -50%; right: 50%; height: 3px; background: var(--ub-border); z-index: 0; }
.ub-tl-step:first-child::before { display: none; }
.ub-tl-dot { width: 36px; height: 36px; border-radius: 50%; background: #fff; border: 3px solid var(--ub-border); display: grid; place-items: center; margin: 0 auto .4rem; position: relative; z-index: 1; color: var(--ub-muted); }
.ub-tl-step.done { color: var(--ub-text); }
.ub-tl-step.done::before { background: var(--ub-primary); }
.ub-tl-step.done .ub-tl-dot { background: var(--ub-primary); border-color: var(--ub-primary); color: #fff; }
.ub-tl-step.current .ub-tl-dot { border-color: var(--ub-primary); color: var(--ub-primary); box-shadow: 0 0 0 5px rgba(var(--ub-primary-rgb), .12); }
.ub-stars { display: inline-flex; gap: .2rem; font-size: 1.6rem; }
.ub-stars button { border: 0; background: none; padding: 0; color: #d6d3df; line-height: 1; }
.ub-stars button.on { color: var(--ub-accent); }

/* Horizontal row */
.ub-row-scroll { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(170px, 210px); gap: 1rem; overflow-x: auto; padding-bottom: .5rem; }

/* Bottom nav (phone) */
.ub-bottom-nav { position: fixed; left: 0; right: 0; bottom: 0; background: var(--ub-surface); border-top: 1px solid var(--ub-border); display: flex; z-index: 1030; padding: .35rem .25rem calc(.35rem + env(safe-area-inset-bottom)); box-shadow: 0 -4px 20px rgba(0,0,0,.05); }
.ub-bottom-nav a { flex: 1; text-align: center; text-decoration: none; color: var(--ub-muted); font-size: .7rem; font-weight: 600; padding: .3rem 0; }
.ub-bottom-nav a i { display: block; font-size: 1.25rem; margin-bottom: 1px; }
.ub-bottom-nav a.active { color: var(--ub-primary); }
.ub-bottom-nav .sell i { width: 42px; height: 42px; border-radius: 14px; background: var(--ub-primary); color: #fff; display: grid; place-items: center; margin: -18px auto 2px; box-shadow: 0 6px 16px rgba(var(--ub-primary-rgb), .35); }
@media (max-width: 767.98px) {
  body { padding-bottom: 76px; }
  .ub-sticky { position: static; }
  .ub-hero { padding: 1.6rem 0 2.4rem; border-radius: 0 0 22px 22px; }
  .ub-steps .ub-step .lbl { display: none; }
  .ub-steps .ub-step.active .lbl { display: inline; }
}

.min-w-0 { min-width: 0; }

/* Pages not yet moved to the new design still use Bootstrap .card (often .border-0):
   give them the same visible edge and shadow as .ub-card so they don't blend into the background. */
.card { --bs-card-bg: var(--ub-surface); --bs-card-border-color: var(--ub-border); --bs-card-border-radius: var(--ub-radius); --bs-card-inner-border-radius: calc(var(--ub-radius) - 1px); }
.card.border-0 { border: 1px solid var(--ub-border) !important; }
.card.shadow-sm { box-shadow: var(--ub-shadow) !important; }
</style>
