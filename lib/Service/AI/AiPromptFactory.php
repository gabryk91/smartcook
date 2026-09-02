<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\AI;

final class AiPromptFactory {
    /** @param array<string, mixed> $recipe */
    public function refinement(array $recipe, string $language): string {
        $recipeJson = json_encode($recipe, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $schema = <<<'JSON'
{"title":"string","subtitle":"string|null","description":"string|null","author":"string|null","sourceName":"string|null","sourceUrl":"string|null","cuisine":"string|null","mealType":"string|null","cookingMethod":"string|null","season":"string|null","calories":null,"nutrition":{},"tools":[{"name":"string"}],"tags":[{"name":"string"}],"categories":[{"name":"string"}],"addCover":false,"coverSuggestion":"string|null"}
JSON;
        return "Review this existing recipe and propose only high-confidence editorial and organizational improvements. Return only valid JSON, no Markdown or commentary. You may read ingredients and procedure to understand the recipe, but NEVER propose or modify them, their quantities, times, servings, yield, steps, media, notes, status, visibility, or planner settings. Do not invent facts; leave a field unchanged by returning its current value or null when no useful improvement can be made. Normalize classifications concisely. Estimate calories per serving only when it can be reasonably inferred. Set addCover true only when the recipe has no cover and a representative food photo is appropriate; coverSuggestion must be a short search description, never a URL. Output language: {$language}. Use exactly this shape:\n{$schema}\n\nRECIPE:\n{$recipeJson}";
    }

    public function recipe(string $text, string $language): string {
        $schema = <<<'JSON'
{
  "title": "string",
  "subtitle": "string|null",
  "description": "string|null",
  "language": "BCP-47 language code",
  "author": "string|null",
  "sourceName": "string|null",
  "sourceUrl": "string|null",
  "servings": 1,
  "yieldText": "string|null",
  "prepTime": 0,
  "restTime": 0,
  "cookTime": 0,
  "totalTime": 0,
  "difficulty": "string|null",
  "cuisine": "string|null",
  "mealType": "string|null",
  "cookingMethod": "string|null",
  "season": "string|null",
  "calories": null,
  "nutrition": {},
  "coverSuggestion": "string|null",
  "notes": "string|null",
  "ingredients": [{"name":"string","originalText":"string","quantity":"string|null","amount":null,"unit":"string|null","notes":"string|null","optional":false,"alternatives":[{"name":"string","quantity":"string|null","amount":null,"unit":"string|null","notes":"string|null"}],"group":"string|null","category":"string|null","allergens":[],"substitutes":[]}],
  "steps": [{"text":"string","timerSeconds":null,"temperature":null,"temperatureUnit":null,"notes":null}],
  "tools": [{"name":"string"}],
  "tags": [{"name":"string"}],
  "categories": [{"name":"string"}]
}
JSON;
        return "Extract one cooking recipe from the source below. Return only one valid JSON object, with no Markdown and no commentary. Preserve facts for ingredients, quantities, times, source and tools; never invent those details. When the source explicitly offers an ingredient replacement, place it in that ingredient's alternatives array instead of making it a separate main ingredient. Also enrich the recipe with useful editorial suggestions when the source does not state them: choose a concise category, cuisine, cooking method, meal type and 2-5 useful tags; estimate calories per serving from the ingredients when possible. For coverSuggestion, provide a short, specific photo-search description of the finished dish (for example, 'overhead photo of creamy mushroom risotto in a white bowl'); do not provide a URL. These are suggestions to review, so prefer null only when a meaningful suggestion is impossible. The description must be a brief description of the finished recipe result, not a repetition or list of ingredients or procedure; return null if no suitable description can be derived. Times are integer minutes, timerSeconds are seconds. Split ingredients and procedure into ordered arrays. Normalize units while retaining originalText. Output language should be {$language}. Use this exact shape:\n{$schema}\n\nSOURCE:\n{$text}";
    }

    /** @param list<array<string, mixed>> $recipes @param array<string, mixed> $preferences */
    public function mealPlan(array $recipes, string $from, string $to, array $preferences): string {
        $schema = '{"meals":[{"date":"YYYY-MM-DD","slot":"breakfast|lunch|dinner|snack","recipeId":1,"servings":2,"notes":"string|null"}]}';
        $catalog = json_encode($recipes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $custom = trim((string)($preferences['prompt'] ?? ''));
        $instruction = $custom !== '' ? $custom : 'Organizza una settimana varia e realistica usando esclusivamente le ricette disponibili.';
        return "{$instruction}\nCrea un piano pasti dal {$from} al {$to}. Usa esclusivamente recipeId presenti nel catalogo. Rispetta preferenze alimentari, allergie, tempo massimo e porzioni indicati. Inserisci colazione, pranzo, cena o snack quando appropriato; non aggiungere pasti se non esiste una ricetta adatta. Evita ripetizioni ravvicinate quando possibile. Restituisci solo JSON valido senza Markdown con questa forma: {$schema}\n\nPREFERENZE:\n" . (string)($preferences['dietary'] ?? '') . "\nTEMPO MASSIMO DI CUCINA (MINUTI): " . (int)($preferences['cookingTime'] ?? 60) . "\nPORZIONI: " . (int)($preferences['servings'] ?? 2) . "\nISTRUZIONI SETTIMANALI:\n" . (string)($preferences['instruction'] ?? '') . "\n\nCATALOGO RICETTE:\n{$catalog}";
    }
}
