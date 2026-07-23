<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\AI;

final class AiPromptFactory {
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
  "course": "string|null",
  "mealType": "string|null",
  "cookingMethod": "string|null",
  "season": "string|null",
  "origin": "string|null",
  "calories": null,
  "nutrition": {},
  "notes": "string|null",
  "ingredients": [{"name":"string","originalText":"string","quantity":"string|null","amount":null,"unit":"string|null","notes":"string|null","optional":false,"group":"string|null","category":"string|null","allergens":[],"substitutes":[]}],
  "steps": [{"text":"string","timerSeconds":null,"temperature":null,"temperatureUnit":null,"notes":null}],
  "tools": [{"name":"string"}],
  "tags": [{"name":"string"}],
  "categories": [{"name":"string"}]
}
JSON;
        return "Extract one cooking recipe from the source below. Return only one valid JSON object, with no Markdown and no commentary. Preserve facts; never invent missing quantities, times, nutrition or tools. Times are integer minutes, timerSeconds are seconds. Split ingredients and procedure into ordered arrays. Normalize units while retaining originalText. Output language should be {$language}. Use this exact shape:\n{$schema}\n\nSOURCE:\n{$text}";
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
