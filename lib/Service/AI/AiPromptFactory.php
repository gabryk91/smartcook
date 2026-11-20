<?php

declare(strict_types=1);

namespace OCA\SmartCook\Service\AI;

final class AiPromptFactory {
    /** @param list<array<string, mixed>> $recipes */
    public function assistant(string $question, array $recipes, string $language): string {
        $catalog = json_encode($recipes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $schema = '{"answer":"string","recommendations":[{"recipeId":1,"reason":"string"}]}';
        return "You are SmartCook's cookbook assistant. Answer the user's question using only the supplied recipe catalog. You may give practical cooking advice, but never claim that a recipe, ingredient, quantity, allergen, time, or dietary property exists unless it is present in the catalog. When ingredients are available, recommend recipes that use the greatest useful overlap; clearly mention important missing ingredients. Do not invent recipe IDs. Keep the answer concise, helpful, and in {$language}. Return only valid JSON without Markdown using exactly this shape: {$schema}. Include at most 8 recommendations and only recipeId values from the catalog.\n\nUSER QUESTION:\n{$question}\n\nRECIPE CATALOG:\n{$catalog}";
    }

    /** @param array<string, mixed> $recipe */
    public function refinement(array $recipe, string $language, array $taxonomy = []): string {
        $recipeJson = json_encode($recipe, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $schema = <<<'JSON'
{"title":"string","subtitle":"string|null","description":"string|null","author":"string|null","sourceName":"string|null","sourceUrl":"string|null","difficulty":"integer 1 through 5|null","cuisine":"string|null","mealType":"string|null","cookingMethod":"string|null","season":"string|null","calories":null,"nutrition":{},"steps":[{"text":"string","timerSeconds":null,"temperature":null,"temperatureUnit":null,"notes":null}]|null,"tools":[{"name":"string"}],"tags":[{"name":"string"}],"categories":[{"name":"string"}],"clearFields":["tags|categories|tools|cuisine|mealType|cookingMethod|season"],"addCover":false,"coverSuggestion":"string|null"}
JSON;
        return "Review this existing recipe and propose only high-confidence editorial and organizational improvements. Return only valid JSON, no Markdown or commentary. You may read ingredients and procedure to understand the recipe, but NEVER modify ingredients, their quantities, times, servings, yield, an existing procedure, media, notes, status, visibility, or planner settings. Only when the recipe has no non-empty procedure, you may propose steps: write a concise, practical procedure based solely on the listed ingredients and reliable common cooking methods. Do not introduce unlisted ingredients, exact times, temperatures, or equipment; use null for unavailable step metadata. Return steps as null when a safe, useful procedure cannot be inferred. Do not invent facts; leave a field unchanged by returning its current value or null when no useful improvement can be made. To remove a superfluous field, list its name in clearFields and leave its normal value unchanged. Use clearFields only when a current value adds no distinct way to find the recipe, such as a tag that duplicates a category, meal type, cuisine or cooking method. For difficulty, use only an integer from 1 to 5: 1 very easy, 2 easy, 3 medium, 4 difficult, 5 very difficult. " . $this->classificationInstruction($taxonomy) . " Estimate calories per serving only when it can be reasonably inferred. Set addCover true only when the recipe has no cover and a representative food photo is appropriate; coverSuggestion must be a short search description, never a URL. Output language: {$language}. Use exactly this shape:\n{$schema}\n\nRECIPE:\n{$recipeJson}";
    }

    /**
     * Editorial pass for an already parsed import. Ingredients and steps stay deterministic;
     * a small model only has to name, describe, time and classify the dish.
     *
     * @param array<string, mixed> $recipe
     * @param array<string, mixed> $taxonomy
     */
    public function importRefinement(array $recipe, string $language, array $taxonomy = []): string {
        $source = trim((string)($recipe['source'] ?? ''));
        $recipe = is_array($recipe['recipe'] ?? null) ? $recipe['recipe'] : $recipe;
        $ingredients = [];
        foreach ((array)($recipe['ingredients'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $line = trim(implode(' ', array_filter([(string)($item['quantity'] ?? ''), (string)($item['unit'] ?? '') === 'to_taste' ? '' : (string)($item['unit'] ?? ''), (string)($item['name'] ?? '')], static fn (string $part): bool => trim($part) !== '')));
            if ($line !== '') {
                $ingredients[] = '- ' . $line;
            }
        }
        $steps = [];
        foreach ((array)($recipe['steps'] ?? []) as $index => $step) {
            $text = trim((string)(is_array($step) ? ($step['text'] ?? '') : $step));
            if ($text !== '') {
                $steps[] = ((int)$index + 1) . '. ' . mb_substr($text, 0, 400);
            }
        }
        $schema = '{"title":"string","description":"string","servings":1,"prepTime":0,"restTime":0,"cookTime":0,"difficulty":1,"calories":0,"cuisine":"string|null","mealType":"string|null","cookingMethod":"string|null","season":"string|null","categories":[{"name":"string"}],"tags":[{"name":"string"}],"tools":[{"name":"string"}],"coverSuggestion":"string"}';
        $knownIngredients = $ingredients === [] ? '(none extracted)' : implode("\n", array_slice($ingredients, 0, 60));
        $knownSteps = $steps === [] ? '(none extracted)' : implode("\n", array_slice($steps, 0, 40));
        $source = mb_substr($source, 0, 8000);
        return "You are the editor of a cookbook app. The SOURCE below is a social post or web page that contains ONE recipe. The ingredients and steps have already been extracted correctly and are final: do NOT output them. Your job is to fill in the editorial data with the JSON object described below, using the source AND your general culinary knowledge of the named dish.\n\n"
            . "RULES\n"
            . "1. title: the short name of the finished dish (fewer than 80 characters), taken from the source or recognized from ingredients and method. No emoji, hashtags, quantities, the words 'ingredients' or 'recipe', or promotional text. Never copy the whole caption.\n"
            . "2. description: 2 or 3 warm, discursive sentences in the output language, like the introduction of a good cookbook. Say what the dish is, its origin or tradition when you recognize it (for example that a Kürtőskalács is a Hungarian chimney cake roasted over embers), and its taste and texture. Do not list ingredients or steps, do not write generic filler like 'easy recipe', and do not invent facts about this specific recipe.\n"
            . "3. prepTime, restTime, cookTime: integer minutes. Use the times written in the source; when missing, estimate them from the steps (prepTime = hands-on work, restTime = resting, rising, cooling or marinating, cookTime = baking, frying or boiling). Use 0 only when the step does not exist. servings: integer, from the source or estimated. difficulty: integer 1 (very easy) to 5 (very difficult). calories: kcal per serving, taken from the source when stated, otherwise a reasonable estimate.\n"
            . "4. " . $this->classificationInstruction($taxonomy) . "\n"
            . "5. tools: kitchen equipment actually mentioned or clearly required (empty array if none). coverSuggestion: a short English photo-search phrase describing the finished dish, never a URL.\n"
            . "6. Output language for title, description and coverSuggestion text: {$language}. Return ONLY one valid JSON object, no Markdown, no commentary, with exactly this shape:\n{$schema}\n\n"
            . "ALREADY EXTRACTED INGREDIENTS:\n{$knownIngredients}\n\nALREADY EXTRACTED STEPS:\n{$knownSteps}\n\nSOURCE:\n{$source}";
    }

    public function recipe(string $text, string $language, array $taxonomy = []): string {
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
  "difficulty": "integer 1 through 5|null",
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
        return "Extract one cooking recipe from the source below. Return only one valid JSON object, with no Markdown and no commentary. The deterministic preview is only a clue: review its title and classifications against the original source and correct them. Ingredients are edible components with their quantities. Procedure sentences, nutrition or macro summaries, cooking settings, usernames, credits, hashtags and thanks are not ingredients. Never copy a caption fragment into an ingredient name. The title must be only the short name of the finished dish, without emoji, ingredient lists, quantities, section headings, instructions, hashtags or promotional text; aim for fewer than 100 characters. Preserve facts for ingredients, quantities, times, source and tools; never invent those details. When the source explicitly offers an ingredient replacement, place it in that ingredient's alternatives array instead of making it a separate main ingredient. Also enrich the recipe with useful editorial suggestions when the source does not state them. " . $this->classificationInstruction($taxonomy) . " For difficulty, use only an integer from 1 to 5: 1 very easy, 2 easy, 3 medium, 4 difficult, 5 very difficult; return null when it cannot be inferred. Estimate calories per serving from the ingredients when possible. For coverSuggestion, provide a short, specific photo-search description of the finished dish (for example, 'overhead photo of creamy mushroom risotto in a white bowl'); do not provide a URL. These are suggestions to review, so prefer null only when a meaningful suggestion is impossible. The description must be 2 or 3 warm, discursive sentences in the output language, like the introduction of a good cookbook: present the finished dish, its origin or tradition when you recognize it (use your general culinary knowledge, for example that a Kürtőskalács is a Hungarian chimney cake), and its taste and texture. Never list ingredients or steps in it, never write generic filler such as 'easy recipe', and never state facts about quantities or methods that the source does not give. Return null only when you cannot even recognize the dish. INGREDIENT RULES: copy each ingredient name from the source without generalizing or renaming it (never replace 'base pizza' with 'pasta'). Put only the number in quantity (for example \"385\") and the unit in unit (for example \"g\"); never repeat the unit inside quantity, and set amount to the numeric value. For 'q.b.' or 'quanto basta' use quantity \"q.b.\" and unit \"to_taste\". When one source line lists several ingredients after one shared phrase (for example 'q.b. burro, dolcificante, cannella'), create one separate ingredient for each, every one carrying that same quantity phrase. Every ingredient that has a quantity in the source MUST keep it. STEP RULES: keep every step of the source in order, rewrite each as a clear complete sentence, keep temperatures and times in the matching fields, and do not merge or drop steps. Times are integer minutes, timerSeconds are seconds. Split ingredients and procedure into ordered arrays. Normalize units while retaining originalText. Output language should be {$language}. Use this exact shape:\n{$schema}\n\nSOURCE:\n{$text}";
    }

    /** @param array<string, mixed> $taxonomy */
    private function classificationInstruction(array $taxonomy): string {
        $choices = [];
        foreach (['tags', 'categories', 'cuisine', 'mealType', 'cookingMethod'] as $field) {
            $names = array_values(array_filter(array_map(static fn (mixed $item): string => trim((string)(is_array($item) ? ($item['name'] ?? '') : $item)), (array)($taxonomy[$field] ?? []))));
            $choices[$field] = $names;
        }
        $catalog = json_encode($choices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return "For tags, categories, cuisine, mealType and cookingMethod, select ONLY exact labels from this existing SmartCook vocabulary: {$catalog}. Never invent, translate, rephrase, or create a new classification. If a recognizable dish fits a listed category, choose the single best category; use [] only when none fits. Classify the DISH ITSELF using your culinary knowledge, never the language, the social network or a default: cuisine is the real country or region of origin of the dish (a Hungarian chimney cake is Hungarian, never Italian; if that cuisine is not in the list return null rather than a wrong one); mealType is the course or occasion the dish belongs to (a sweet or dessert must never be lunch or dinner: use the dessert, snack or breakfast label that fits; if the list has no fitting label return null); categories must be the food type (sweets and desserts go in the sweets/desserts category, not in a meal-time label); cookingMethod must be stated or clearly implied by the procedure, otherwise null. A wrong classification is worse than none. Keep the organization compact: prefer the broadest accurate existing label, use the fewest labels that still help users find the recipe, and when labels are near-duplicates or aliases choose one existing label consistently. Do not duplicate the same meaning across tags, categories, cuisine, mealType or cookingMethod: for example, if 'Primo piatto' is already the category or meal type, do not also use it as a tag. Do not add a more specific label when an assigned broader label already describes the recipe. If no listed value is appropriate, return null for single-value fields and [] for tags/categories. ";
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
