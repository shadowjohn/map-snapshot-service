# Map Snapshot API Reference Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a single AI-friendly API reference page for report map PNG generation and link it from the top-level project entry points.

**Architecture:** Keep the feature as static documentation over the existing HTTP API. Add a lightweight PHP test that treats the reference page, catalog nav link, and README link as the integration contract. Do not change renderer or endpoint behavior.

**Tech Stack:** Plain HTML, existing PHP catalog page, Markdown README, lightweight PHP assertion test.

---

### Task 1: Contract Test

**Files:**
- Create: `tests/ai_agent_api_reference_test.php`

- [ ] **Step 1: Write the failing test**

Create `tests/ai_agent_api_reference_test.php` with assertions that:

- `ai-agent-api.html` exists.
- The page contains all five API endpoint paths.
- The page explains POST preference, WGS84 `lat,lon`, PNG validation, report saving, basemap guidance, and agent safety rules.
- `index.php` links to `ai-agent-api.html` with visible `AI API` text in the top navigation.
- `README.md` links the expected public reference URL.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/ai_agent_api_reference_test.php`

Expected before implementation: FAIL because `ai-agent-api.html` does not exist.

### Task 2: Reference Page And Links

**Files:**
- Create: `ai-agent-api.html`
- Modify: `index.php`
- Modify: `README.md`

- [ ] **Step 1: Create the static reference page**

Create `ai-agent-api.html` as a documentation-first page with:

- Service summary.
- Public base URL.
- Recipe chooser table.
- Shared request rules.
- Basemap guidance.
- Endpoint reference for single point, two point, multi point, line, and polygon.
- Report integration checklist.
- Agent safety rules.
- Minimal integration snippets.

- [ ] **Step 2: Update the top navigation link**

Modify `index.php` top navigation to include:

```html
<a href="ai-agent-api.html">AI API</a>
```

Place it near the existing API link so the top bar exposes the new page.

- [ ] **Step 3: Update README link**

Add the public reference URL to the README demo list:

```markdown
- AI Agent API Reference: https://3wa.tw/demo/php/map/map-snapshot-service/ai-agent-api.html
```

### Task 3: Verification

**Files:**
- Test: `tests/ai_agent_api_reference_test.php`

- [ ] **Step 1: Run the new test**

Run: `php tests/ai_agent_api_reference_test.php`

Expected after implementation: `PASS`.

- [ ] **Step 2: Run existing smoke tests**

Run:

```bash
php tests/geometry_recipes_test.php
php tests/two_point_renderer_test.php
```

Expected: both print `PASS`.

- [ ] **Step 3: Browser/static serving check**

Run a local server:

```bash
php -S 127.0.0.1:8080
```

Open `http://127.0.0.1:8080/ai-agent-api.html` and verify the page renders.

Fetch one documented API image:

```bash
curl -sS -o /tmp/mss-ai-agent-single-point.png \
  -w '%{http_code} %{content_type}\n' \
  --data-urlencode 'latLon=24.1782252,120.6484168' \
  --data-urlencode 'name=逢甲大學' \
  --data-urlencode 'basemap=fixture' \
  --data-urlencode 'width=416' \
  --data-urlencode 'height=416' \
  http://127.0.0.1:8080/api/single-point.php
```

Expected: `200 image/png`, and the output file starts with the PNG signature.

