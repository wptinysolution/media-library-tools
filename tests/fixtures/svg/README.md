# SVG security fixtures

Proof-of-concept inputs for the `enshrined/svg-sanitize` advisories, used only by
`tests/Integration/SvgSanitizeTest.php`. The `tests/` directory is not part of the
release package (see `build.js`).

Copied unchanged from the upstream project's own test suite at tag `1.0.0`
(https://github.com/darylldoyle/svg-sanitizer/tree/1.0.0/tests/data),
licensed GPL-2.0-or-later:

| File | Advisory | Upstream fix commit |
|---|---|---|
| `attlistFixedDosTest.svg` | GHSA-v383-3rw5-q8rf (CVE-2026-107379) | `23877db7e76f1e1df5c3e65ab30239219c3d2867` |
| `entityHrefBypassTest.svg` | GHSA-9rjx-3jch-6vjf (CVE-2026-107380) | `23877db7e76f1e1df5c3e65ab30239219c3d2867` |
| `useDosTest.svg` | GHSA-m9xh-6747-9r6f (CVE-2026-107381) when its `xlink:href` attributes are rewritten with mixed case | `2dff6628314de8519155b7feb218bbe132785757` |
