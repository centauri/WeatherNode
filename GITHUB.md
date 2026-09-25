# GitHub: source control and release management

This document regroups how WeatherNode uses GitHub for source control, releases, and contributions. Use it as a single reference when preparing or maintaining the public repo.

## What’s in place

### Source control and repo layout

- **Default branch** — Assumed `main` in docs and workflows. Change in GitHub repo settings if you use another default.
- **.gitignore** — Excludes `vendor/`, `node_modules/`, `.env`, build artifacts, logs, IDE and OS cruft, and local-only paths (e.g. `telemetry-aggregator`, `docs/_archive/`). `.env.example` is tracked.
- **VERSION** — Tracked at repo root. Format: `vYYYY.MM.patch` or `vYYYY.MM.patch-dev`. See [VERSIONING_GUIDE.md](VERSIONING_GUIDE.md).

### Documentation (for users and contributors)

| File | Purpose |
|------|--------|
| [README.md](README.md) | Project overview, quick start, features, requirements |
| [DEVELOPMENT.md](DEVELOPMENT.md) | Local setup, tests, scheduler, code layout |
| [VERSIONING_GUIDE.md](VERSIONING_GUIDE.md) | Version format, release workflow, GitHub releases |
| [CONTRIBUTING.md](CONTRIBUTING.md) | How to contribute (fork, branch, test, PR) |
| [SECURITY.md](SECURITY.md) | How to report vulnerabilities |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Production deployment |
| [CHANGELOG.md](CHANGELOG.md) | Notable changes per version |
| [LICENSE.txt](LICENSE.txt) | AGPL-3.0 |

### Makefile and scripts

- **`make clean`** — Removes local build/cache/log artifacts (e.g. `public/build`, `storage/logs`, `.phpunit.result.cache`).
- **`make release`** — Interactive: suggests next `vYYYY.MM.patch`, asks for a changelog bullet (plain text/Markdown, no raw HTML), updates `VERSION` and `CHANGELOG.md`. Use before tagging a release if you want the repo and changelog in sync.
- **`make release-note`** — Alias for `make release`.
- **`make e2e-dashboard-hybrid`** — Runs Playwright hybrid dashboard regression against `http://localhost:8000/` (optional; needs Playwright set up).

Scripts used by the release workflow:

- **`scripts/next-release-version.sh`** — Prints next tag (e.g. `v2026.03.1`) from current date and existing `vYYYY.MM.*` tags.
- **`scripts/release.sh`** — Called by `make release`; updates VERSION and CHANGELOG.

### GitHub Actions: release workflow

- **File:** `.github/workflows/release.yml`
- **Triggers:**
  1. **Push a tag `v*`** — Builds the app, runs tests, produces `weathernode-deploy.zip`, and creates a GitHub Release with that asset.
  2. **Workflow dispatch** (“Run workflow” in the Actions tab) — Computes the next release tag, updates the **VERSION** file on the default branch (commit + push), then creates and pushes the tag. The tag push triggers the same build-and-release as (1).

So you can release in two ways:

- **Manual:** Run `make release`, commit `VERSION` and `CHANGELOG.md`, then `git tag vYYYY.MM.patch`, `git push origin main --tags`. The tag push runs the workflow and publishes the release.
- **From the UI:** Actions → “Release (build deploy ZIP)” → “Run workflow”. The workflow updates VERSION on the default branch, creates the tag, and the subsequent run builds and publishes the release. Optionally run `make release` beforehand to add a CHANGELOG entry; the workflow does not edit CHANGELOG.

The built ZIP includes `VERSION` and `release.json` (version, commit, build time, artifact name, sha256). The GitHub Release is created with auto-generated release notes and a link to CHANGELOG.

### Docker container (GHCR)

Version tag releases also publish a Docker image to GitHub Container Registry:

- Image: `ghcr.io/centauri/weathernode`
- Tags: release tag (for example `v2026.05.0`) and `latest`

Quick usage:

```bash
docker pull ghcr.io/centauri/weathernode:latest
docker run -d --name weathernode -p 8080:80 --env-file .env \
  -v weathernode_storage:/var/www/html/storage \
  -v weathernode_cache:/var/www/html/bootstrap/cache \
  -v weathernode_db:/var/www/html/database \
  ghcr.io/centauri/weathernode:latest
```

Then initialize once:

```bash
docker exec weathernode php artisan migrate --force
docker exec weathernode php artisan db:seed
docker exec -it weathernode php artisan admin:create
```

### Issue and PR templates

- **`.github/ISSUE_TEMPLATE/`** — Bug report, feature request, and data source suggestion. Blank issues are allowed (`config.yml`).
- **Pull requests** — No custom template; contributors can follow [CONTRIBUTING.md](CONTRIBUTING.md).

---

## Checklist: ready for deployment to GitHub

Before (or right after) pushing the repo to GitHub, confirm the following.

### Repo and branch

- [ ] Create the GitHub repository (e.g. `your-org/weathernode`).
- [ ] Set the default branch (e.g. `main`) in repo settings.
- [ ] Add the remote and push:  
  `git remote add origin https://github.com/your-org/weathernode.git`  
  `git push -u origin main`  
  (Adjust branch name if different.)

### Secrets and visibility

- [ ] No secrets in the repo: `.env` and other sensitive files are in `.gitignore` and not committed.
- [ ] Choose visibility (Public/Private) and adjust any org policies (branch protection, required status checks) if needed.

### Docs and links

- [ ] In README, replace any placeholder clone URL with the real one (e.g. `git clone https://github.com/your-org/weathernode.git`).
- [ ] If you use SECURITY.md, add a concrete contact (e.g. email or “open a private security advisory”) so reporters know where to send vulnerabilities.

### Labels (optional)

- [ ] Create labels used by issue templates if you want them: e.g. `bug`, `enhancement`, `open-data`. GitHub can also create labels when first used.

### First release (optional)

- [ ] Ensure `VERSION` is set to the first release (e.g. `v2026.03.0`) and committed.
- [ ] Either run **workflow_dispatch** once to create the tag and release, or tag manually and push:  
  `git tag v2026.03.0 && git push origin v2026.03.0`  
  so the release workflow runs and publishes `weathernode-deploy.zip`.

### After going public

- [ ] Pin important docs (e.g. README, CONTRIBUTING, VERSIONING_GUIDE) in the repo description or profile if desired.
- [ ] If others will contribute, point them to [CONTRIBUTING.md](CONTRIBUTING.md) and [DEVELOPMENT.md](DEVELOPMENT.md).

---

## Quick reference

| Task | Command or location |
|------|----------------------|
| Clean local artifacts | `make clean` |
| Prepare version + changelog | `make release` |
| Next suggested tag | `sh scripts/next-release-version.sh` |
| Show/set version | `php artisan version show` / `php artisan version set v…` |
| Run tests | `composer test` or `php artisan test` |
| Release via GitHub UI | Actions → Release (build deploy ZIP) → Run workflow |
| Release manually | Commit VERSION (+ CHANGELOG), then `git tag v…` and `git push origin main --tags` |
| Docker image | `docker pull ghcr.io/centauri/weathernode:latest` |

For version rules and more examples, see [VERSIONING_GUIDE.md](VERSIONING_GUIDE.md).
