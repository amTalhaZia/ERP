# OpenAPI generator

Regenerates `docs/api/openapi.yaml` from the code itself — the live router, FormRequest `rules()`,
API Resources, enums, model casts and migrations. No database needed.

```bash
cd backend
php tools/openapi/gen.php      # prints: routes=… operations=… paths=…
npx --yes @redocly/cli@1.25.0 lint ../docs/api/openapi.yaml
```

Run it after adding or changing routes, requests or resources. Ad-hoc JSON responses (reports,
summaries) are traced from controller/service code and marked `x-schema-source: inferred-from-code`.
`x-permissions` lists route-middleware permission slugs plus slugs checked by the policies a handler calls.
