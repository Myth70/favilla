<!-- Thanks for contributing to Favilla! -->

## What & why

<!-- What does this change do, and what problem does it solve? -->

## How it was tested

<!-- Commands run, scenarios exercised. -->

## Checklist

- [ ] `composer test` passes
- [ ] `composer stan` passes
- [ ] No new `composer cs` violations in changed files
- [ ] Follows the layering contract (Controller → Service → Repository → View)
- [ ] Mutating forms include `csrf_field()`; untrusted output uses `e()`
- [ ] User-facing copy goes through `t()` (Italian canonical + en/fr/de/es); `php favilla lang:check` passes
- [ ] Ran `php favilla context:generate` if routes/permissions/schema changed
- [ ] Updated `FEATURES.md` if user-facing features were added or removed
- [ ] Added/updated tests for the change
