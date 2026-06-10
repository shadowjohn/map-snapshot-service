# Map Snapshot API Reference Design

## Purpose

Create a single AI-friendly API reference page for Map Snapshot Service so future systems and AI agents can generate static PNG map images for reports, documents, notifications, tickets, and README output through the existing HTTP API.

The goal is convenience of integration. The page should be easy for an agent to read, quote, and turn into code. It is not a marketing page and should not require the agent to inspect PHP internals before calling the service.

## Primary Deliverable

Add one static reference page:

`ai-agent-api.html`

Expected public URL after deployment:

`https://3wa.tw/demo/php/map/map-snapshot-service/ai-agent-api.html`

The page should be linked from the catalog page or README so users and agents can find it.

## Optional Thin Skill

A future `map-snapshot-api` skill can stay intentionally small. It should trigger when an agent needs static map PNG generation for reports or API integrations, then point the agent to the AI reference page as the source of truth.

The skill should not duplicate the full API documentation. It should only explain when to use the service and require the agent to read the reference page before generating integration code.

## Reference Page Audience

The page is written for:

- AI coding agents building report generation features.
- Backend agents wiring an application to an image-producing HTTP API.
- Document-generation agents that need a local file path or public URL for a static map image.
- Human developers who want a compact copy-paste API reference.

## Content Structure

The page should contain these sections in this order:

1. Service summary
   - Explain that the service returns static PNG map snapshots from WGS84 coordinates.
   - Mention report, document, notification, ticket, and README use cases.

2. Base URL
   - Show the public base URL.
   - Show that endpoints live under `/api/`.

3. Recipe chooser
   - `single-point`: one marker and label.
   - `two-point`: start and end markers with labels.
   - `multi-point`: several markers with labels or auto numbering.
   - `line`: polyline or route with endpoint labels and optional segment labels.
   - `polygon`: area boundary with a label.

4. Shared request rules
   - GET and POST are both supported.
   - Prefer POST for application integration, long geometry strings, report generation, and generated code.
   - Use GET only for short examples and copyable URLs.
   - Coordinates are WGS84 `lat,lon`.
   - Multi-coordinate values use `lat,lon;lat,lon;...`.
   - Output is `image/png`.
   - Suggested report default size is `416 x 416`.
   - `width` is clamped from `320` to `1024`.
   - `height` is clamped from `240` to `1024`.
   - `padding` defaults to `40`.

5. Basemap guidance
   - Recommend `osm` for general use.
   - Recommend `emap5` for Taiwan government-style map output.
   - Describe Google basemaps as demo/compatibility options, not the default for production.
   - State that callers cannot provide arbitrary tile URLs.

6. Endpoint reference
   - One compact block per endpoint.
   - Include path, required params, optional params, and one POST curl example.
   - Include endpoint-specific examples for:
     - `api/single-point.php`
     - `api/two-point.php`
     - `api/multi-point.php`
     - `api/line.php`
     - `api/polygon.php`

7. Report integration checklist
   - Choose recipe.
   - Normalize source coordinates to WGS84.
   - Build POST form data.
   - Call the endpoint.
   - Confirm HTTP success.
   - Confirm PNG signature or content type.
   - Save the image into the report output directory.
   - Embed the saved relative path or public URL in the report.

8. Agent safety rules
   - Do not bulk-render many uncached maps against the public demo.
   - Do not send sensitive coordinates to the public demo unless that is acceptable for the system.
   - Do not assume custom tile URLs are supported.
   - Do not scrape cache files directly.
   - Do not remove attribution from generated PNGs.
   - Prefer fixture or local/test services for automated tests.

9. Minimal integration snippets
   - Include one curl POST example.
   - Include concise pseudo-code for `fetch -> validate PNG -> write file`.
   - Keep snippets generic enough that agents can translate them into PHP, Python, C#, Node.js, or report-generation code.

## Visual and Interaction Design

The page should be documentation-first:

- Plain HTML/PHP is acceptable; no framework needed.
- Use the existing project style enough to feel native.
- Prioritize scannable tables, code blocks, and anchored headings.
- Avoid decorative hero treatment.
- Avoid dynamic behavior unless copy buttons are simple and reliable.
- Keep the first viewport useful: title, one-line purpose, base URL, and recipe chooser should appear near the top.

## Integration Contract

The page documents the existing API. It should not require renderer changes.

The initial implementation should not add new API behavior. If a future requirement needs JSON payloads, WKT, GeoJSON, API keys, or client SDKs, those should be separate follow-up features.

## Testing and Verification

Implementation should be verified with:

- `php tests/geometry_recipes_test.php`
- `php tests/two_point_renderer_test.php`
- PHP syntax check for the new page if it is PHP.
- Browser check of the new reference page.
- At least one curl or browser request that returns a PNG from an endpoint documented on the page.

The page should also be reviewed manually for AI usability:

- Can an agent pick the right endpoint without reading README?
- Are POST examples copyable?
- Are coordinate format and geometry separators unambiguous?
- Are basemap and safety constraints visible before examples?
- Is there a clear report-generation checklist?

## Out of Scope

- Building client SDKs.
- Adding MCP tools.
- Adding authentication or API keys.
- Adding JSON, CSV, WKT, or GeoJSON support.
- Changing tile provider behavior.
- Redesigning the catalog page beyond a link to the new reference page.

## Resolved Decisions

- Use `ai-agent-api.html` for the first version because the page is static documentation.
- Do not add copy buttons in the first version; copyable code blocks are enough.
- Implement the single AI reference page first. Create the thin `map-snapshot-api` skill later only if agent usage shows that a local trigger is still helpful.
