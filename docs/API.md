# SmartCook HTTP API

All paths are relative to `/apps/smartcook`. Private endpoints require an authenticated Nextcloud session. Mutating requests require the normal Nextcloud CSRF request token. JSON bodies use UTF-8 and `Content-Type: application/json`.

## Recipes

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/recipes` | List accessible recipes. Filters: `search`, `favorite`, `status`, `difficulty`, `maxTime`, `maxCalories`, `tags`, `categories`, `tools`, `ingredients`, `excludeAllergens`, `sort`, `direction`, `limit`. |
| `GET` | `/recipes/{id}` | Get one detailed recipe. |
| `POST` | `/recipes` | Create from `{ "recipe": RecipeInput }`. |
| `PUT` | `/recipes/{id}` | Update from `{ "recipe": Partial<RecipeInput> }`. |
| `DELETE` | `/recipes/{id}` | Delete an owned recipe. |
| `POST` | `/recipes/{id}/favorite` | Set `{ "favorite": true }`. |
| `POST` | `/recipes/{id}/cooked` | Increment preparation history. |
| `GET` | `/recipes/{id}/versions` | List revisions. |
| `POST` | `/recipes/{id}/restore/{revision}` | Restore a revision as a new revision. |
| `POST` | `/duplicates/check` | Find similar recipes for `{ "recipe": RecipeInput }`. |
| `POST` | `/recipes/{id}/merge` | Merge an inline recipe or `incomingRecipeId`. |

A recipe includes metadata plus these arrays:

```json
{
  "title": "Pasta al pomodoro",
  "language": "it",
  "status": "draft",
  "visibility": "private",
  "favorite": false,
  "servings": 4,
  "prepTime": 10,
  "restTime": 0,
  "cookTime": 20,
  "totalTime": 30,
  "nutrition": {},
  "ingredients": [
    {
      "name": "spaghetti",
      "originalText": "320 g di spaghetti",
      "quantity": "320",
      "amount": 320,
      "unit": "g",
      "optional": false,
      "allergens": ["glutine"],
      "substitutes": []
    }
  ],
  "steps": [{ "text": "Cuocere la pasta", "timerSeconds": 600 }],
  "tags": [{ "name": "veloce" }],
  "categories": [{ "name": "Primi" }],
  "tools": [{ "name": "Pentola" }],
  "media": []
}
```

## Import and extraction

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/import/preview` | Parse URL, text, HTML, Markdown or JSON and return an editable preview. |
| `POST` | `/import/file` | Parse a multipart upload. Images/PDFs use the configured document extractor. |
| `POST` | `/import/queue` | Queue a heavier import for background processing. |
| `GET` | `/import/jobs` | List the current user's import jobs. |
| `GET` | `/import/jobs/{id}` | Read one import job. |

Example URL import:

```json
{
  "kind": "url",
  "payload": {
    "url": "https://example.test/recipe",
    "language": "it"
  },
  "useAi": false,
  "provider": null
}
```

The response contains `recipe`, `strategy`, `warnings` and `duplicates`. SmartCook never saves the preview automatically.

## Taxonomy and dashboard

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/taxonomy` | Return tags, categories, tools and normalized ingredients. |
| `GET` | `/stats` | Return dashboard counters, recent recipes and frequent tags/ingredients. |

## Planner

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/planner?from=YYYY-MM-DD&to=YYYY-MM-DD` | List meals in a date range. |
| `POST` | `/planner` | Create from `{ "meal": { "recipeId", "date", "slot", "servings", "notes" } }`. |
| `PUT` | `/planner/{id}` | Update one meal. |
| `DELETE` | `/planner/{id}` | Delete one meal. |

## Shopping lists

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/shopping` | List shopping lists. |
| `GET` | `/shopping/{id}` | Get a list and its items. |
| `POST` | `/shopping` | Aggregate recipe ingredients from `{ "name", "recipes": [{ "recipeId", "servings" }] }`. |
| `POST` | `/shopping/{id}/items` | Add a manual item. |
| `PUT` | `/shopping/{listId}/items/{itemId}` | Update/check an item. |
| `DELETE` | `/shopping/{id}` | Delete a list. |

## Sharing and public links

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/recipes/{recipeId}/shares` | List shares for an owned recipe. |
| `POST` | `/recipes/{recipeId}/shares` | Create a `user`, `group` or `link` share. |
| `DELETE` | `/recipes/{recipeId}/shares/{shareId}` | Remove a share. |
| `GET` | `/public/{token}` | Render the guest page. |
| `POST` | `/public/{token}/data` | Read the public recipe; send an optional `{ "password": "..." }` JSON body. |

Permissions are a bit mask: `1` is read, `3` is read and update. Only the owner can manage shares and delete a recipe.

## Media, exports and settings

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/recipes/{recipeId}/media` | Upload multipart field `file` plus optional `kind` and `altText`. |
| `GET` | `/media/{id}` | Stream an authorized attachment. |
| `GET` | `/recipes/{id}/export/{format}` | Export `json`, `markdown` or `html`. |
| `GET` | `/settings` | Read non-secret user settings and secret-presence flags. |
| `PUT` | `/settings` | Save `{ "settings": ... }`; blank API-key fields preserve stored keys. |

Set `clearAiApiKey` or `clearOcrApiKey` to `true` to delete an encrypted credential.

## Error format

```json
{
  "error": "Recipe validation failed",
  "errors": {
    "title": "A title is required"
  }
}
```

The common status codes are `400` for validation/import errors, `403` for access failures, `404` for missing objects and `500` for unexpected server errors.
