<?php

declare(strict_types=1);

namespace OCA\SmartCook\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version1000Date20260719000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $this->createRecipes($schema);
        $this->createIngredients($schema);
        $this->createRecipeIngredients($schema);
        $this->createSteps($schema);
        $this->createTools($schema);
        $this->createRecipeTools($schema);
        $this->createTags($schema);
        $this->createRecipeTags($schema);
        $this->createCategories($schema);
        $this->createRecipeCategories($schema);
        $this->createMedia($schema);
        $this->createVersions($schema);
        $this->createMeals($schema);
        $this->createShoppingLists($schema);
        $this->createShoppingItems($schema);
        $this->createImports($schema);
        $this->createShares($schema);

        return $schema;
    }

    private function addId($table): void {
        $table->addColumn('id', Types::BIGINT, [
            'autoincrement' => true,
            'notnull' => true,
            'unsigned' => true,
        ]);
        $table->setPrimaryKey(['id']);
    }

    private function createRecipes(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_recipes')) {
            return;
        }

        $table = $schema->createTable('smartcook_recipes');
        $this->addId($table);
        $table->addColumn('uuid', Types::STRING, ['notnull' => true, 'length' => 36]);
        $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
        $table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('subtitle', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->addColumn('description', Types::TEXT, ['notnull' => false]);
        $table->addColumn('language', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'en']);
        $table->addColumn('author', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->addColumn('source_name', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->addColumn('source_url', Types::STRING, ['notnull' => false, 'length' => 2048]);
        $table->addColumn('license', Types::STRING, ['notnull' => false, 'length' => 128]);
        $table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'draft']);
        $table->addColumn('visibility', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'private']);
        $table->addColumn('favorite', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $table->addColumn('servings', Types::INTEGER, ['notnull' => true, 'default' => 1]);
        $table->addColumn('yield_text', Types::STRING, ['notnull' => false, 'length' => 128]);
        $table->addColumn('prep_time', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        $table->addColumn('rest_time', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        $table->addColumn('cook_time', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        $table->addColumn('total_time', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        $table->addColumn('difficulty', Types::STRING, ['notnull' => false, 'length' => 32]);
        $table->addColumn('cost_cents', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('currency', Types::STRING, ['notnull' => false, 'length' => 3]);
        $table->addColumn('cuisine', Types::STRING, ['notnull' => false, 'length' => 128]);
        $table->addColumn('course', Types::STRING, ['notnull' => false, 'length' => 128]);
        $table->addColumn('meal_type', Types::STRING, ['notnull' => false, 'length' => 128]);
        $table->addColumn('cook_method', Types::STRING, ['notnull' => false, 'length' => 128]);
        $table->addColumn('season', Types::STRING, ['notnull' => false, 'length' => 64]);
        $table->addColumn('origin', Types::STRING, ['notnull' => false, 'length' => 128]);
        $table->addColumn('calories', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('nutrition', Types::TEXT, ['notnull' => false]);
        $table->addColumn('notes', Types::TEXT, ['notnull' => false]);
        $table->addColumn('image_path', Types::STRING, ['notnull' => false, 'length' => 1024]);
        $table->addColumn('folder_path', Types::STRING, ['notnull' => false, 'length' => 1024]);
        $table->addColumn('cook_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        $table->addColumn('last_cooked', Types::BIGINT, ['notnull' => false]);
        $table->addColumn('revision', Types::INTEGER, ['notnull' => true, 'default' => 1]);
        $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
        $table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
        $table->addUniqueIndex(['uuid'], 'sc_recipe_uuid_uniq');
        $table->addIndex(['user_id', 'updated_at'], 'sc_recipe_user_updated');
        $table->addIndex(['user_id', 'favorite'], 'sc_recipe_user_fav');
    }

    private function createIngredients(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_ingr')) {
            return;
        }
        $table = $schema->createTable('smartcook_ingr');
        $this->addId($table);
        $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
        $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('norm_name', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 128]);
        $table->addColumn('allergens', Types::TEXT, ['notnull' => false]);
        $table->addColumn('substitutes', Types::TEXT, ['notnull' => false]);
        $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
        $table->addUniqueIndex(['user_id', 'norm_name'], 'sc_ingr_user_name_uniq');
    }

    private function createRecipeIngredients(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_r_ingr')) {
            return;
        }
        $table = $schema->createTable('smartcook_r_ingr');
        $this->addId($table);
        $table->addColumn('recipe_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('ingredient_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
        $table->addColumn('original_text', Types::TEXT, ['notnull' => false]);
        $table->addColumn('quantity', Types::STRING, ['notnull' => false, 'length' => 64]);
        $table->addColumn('amount', Types::DECIMAL, ['notnull' => false, 'precision' => 12, 'scale' => 4]);
        $table->addColumn('unit', Types::STRING, ['notnull' => false, 'length' => 32]);
        $table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->addColumn('optional', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        $table->addColumn('group_name', Types::STRING, ['notnull' => false, 'length' => 128]);
        $table->addIndex(['recipe_id', 'sort_order'], 'sc_ringr_recipe_sort');
        $table->addIndex(['ingredient_id'], 'sc_ringr_ingr');
    }

    private function createSteps(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_steps')) {
            return;
        }
        $table = $schema->createTable('smartcook_steps');
        $this->addId($table);
        $table->addColumn('recipe_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        $table->addColumn('text', Types::TEXT, ['notnull' => true]);
        $table->addColumn('timer_secs', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('temperature', Types::DECIMAL, ['notnull' => false, 'precision' => 8, 'scale' => 2]);
        $table->addColumn('temp_unit', Types::STRING, ['notnull' => false, 'length' => 8]);
        $table->addColumn('image_path', Types::STRING, ['notnull' => false, 'length' => 1024]);
        $table->addColumn('notes', Types::TEXT, ['notnull' => false]);
        $table->addColumn('ingredient_ids', Types::TEXT, ['notnull' => false]);
        $table->addColumn('tool_ids', Types::TEXT, ['notnull' => false]);
        $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
        $table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
        $table->addIndex(['recipe_id', 'sort_order'], 'sc_steps_recipe_sort');
    }

    private function createTools(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_tools')) {
            return;
        }
        $table = $schema->createTable('smartcook_tools');
        $this->addId($table);
        $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
        $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('norm_name', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 128]);
        $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
        $table->addUniqueIndex(['user_id', 'norm_name'], 'sc_tools_user_name_uniq');
    }

    private function createRecipeTools(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_r_tools')) {
            return;
        }
        $table = $schema->createTable('smartcook_r_tools');
        $this->addId($table);
        $table->addColumn('recipe_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('tool_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addUniqueIndex(['recipe_id', 'tool_id'], 'sc_rtools_pair_uniq');
        $table->addIndex(['tool_id'], 'sc_rtools_tool');
    }

    private function createTags(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_tags')) {
            return;
        }
        $table = $schema->createTable('smartcook_tags');
        $this->addId($table);
        $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
        $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('norm_name', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('color', Types::STRING, ['notnull' => false, 'length' => 16]);
        $table->addColumn('parent_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
        $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
        $table->addUniqueIndex(['user_id', 'norm_name'], 'sc_tags_user_name_uniq');
    }

    private function createRecipeTags(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_r_tags')) {
            return;
        }
        $table = $schema->createTable('smartcook_r_tags');
        $this->addId($table);
        $table->addColumn('recipe_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('tag_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addUniqueIndex(['recipe_id', 'tag_id'], 'sc_rtags_pair_uniq');
        $table->addIndex(['tag_id'], 'sc_rtags_tag');
    }

    private function createCategories(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_cats')) {
            return;
        }
        $table = $schema->createTable('smartcook_cats');
        $this->addId($table);
        $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
        $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('norm_name', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('color', Types::STRING, ['notnull' => false, 'length' => 16]);
        $table->addColumn('parent_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
        $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
        $table->addUniqueIndex(['user_id', 'norm_name'], 'sc_cats_user_name_uniq');
    }

    private function createRecipeCategories(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_r_cats')) {
            return;
        }
        $table = $schema->createTable('smartcook_r_cats');
        $this->addId($table);
        $table->addColumn('recipe_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('category_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addUniqueIndex(['recipe_id', 'category_id'], 'sc_rcats_pair_uniq');
        $table->addIndex(['category_id'], 'sc_rcats_cat');
    }

    private function createMedia(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_media')) {
            return;
        }
        $table = $schema->createTable('smartcook_media');
        $this->addId($table);
        $table->addColumn('recipe_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('step_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
        $table->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 16]);
        $table->addColumn('path', Types::STRING, ['notnull' => true, 'length' => 1024]);
        $table->addColumn('mime', Types::STRING, ['notnull' => false, 'length' => 128]);
        $table->addColumn('alt_text', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
        $table->addIndex(['recipe_id', 'sort_order'], 'sc_media_recipe_sort');
    }

    private function createVersions(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_versions')) {
            return;
        }
        $table = $schema->createTable('smartcook_versions');
        $this->addId($table);
        $table->addColumn('recipe_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('revision', Types::INTEGER, ['notnull' => true]);
        $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
        $table->addColumn('snapshot', Types::TEXT, ['notnull' => true]);
        $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
        $table->addUniqueIndex(['recipe_id', 'revision'], 'sc_versions_rev_uniq');
    }

    private function createMeals(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_meals')) {
            return;
        }
        $table = $schema->createTable('smartcook_meals');
        $this->addId($table);
        $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
        $table->addColumn('recipe_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('meal_date', Types::STRING, ['notnull' => true, 'length' => 10]);
        $table->addColumn('slot', Types::STRING, ['notnull' => true, 'length' => 16]);
        $table->addColumn('servings', Types::INTEGER, ['notnull' => true, 'default' => 1]);
        $table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
        $table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
        $table->addIndex(['user_id', 'meal_date'], 'sc_meals_user_date');
    }

    private function createShoppingLists(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_shop_lists')) {
            return;
        }
        $table = $schema->createTable('smartcook_shop_lists');
        $this->addId($table);
        $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
        $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'active']);
        $table->addColumn('source', Types::TEXT, ['notnull' => false]);
        $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
        $table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
        $table->addIndex(['user_id', 'updated_at'], 'sc_lists_user_updated');
    }

    private function createShoppingItems(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_shop_items')) {
            return;
        }
        $table = $schema->createTable('smartcook_shop_items');
        $this->addId($table);
        $table->addColumn('list_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('norm_name', Types::STRING, ['notnull' => true, 'length' => 255]);
        $table->addColumn('quantity', Types::STRING, ['notnull' => false, 'length' => 64]);
        $table->addColumn('amount', Types::DECIMAL, ['notnull' => false, 'precision' => 12, 'scale' => 4]);
        $table->addColumn('unit', Types::STRING, ['notnull' => false, 'length' => 32]);
        $table->addColumn('category', Types::STRING, ['notnull' => false, 'length' => 128]);
        $table->addColumn('checked', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $table->addColumn('notes', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
        $table->addIndex(['list_id', 'sort_order'], 'sc_items_list_sort');
    }

    private function createImports(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_imports')) {
            return;
        }
        $table = $schema->createTable('smartcook_imports');
        $this->addId($table);
        $table->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
        $table->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 16]);
        $table->addColumn('source_ref', Types::STRING, ['notnull' => false, 'length' => 2048]);
        $table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'queued']);
        $table->addColumn('use_ai', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
        $table->addColumn('provider', Types::STRING, ['notnull' => false, 'length' => 64]);
        $table->addColumn('payload', Types::TEXT, ['notnull' => false]);
        $table->addColumn('result', Types::TEXT, ['notnull' => false]);
        $table->addColumn('error', Types::TEXT, ['notnull' => false]);
        $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
        $table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
        $table->addIndex(['user_id', 'created_at'], 'sc_import_user_created');
        $table->addIndex(['status', 'created_at'], 'sc_import_status');
    }

    private function createShares(ISchemaWrapper $schema): void {
        if ($schema->hasTable('smartcook_shares')) {
            return;
        }
        $table = $schema->createTable('smartcook_shares');
        $this->addId($table);
        $table->addColumn('recipe_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
        $table->addColumn('share_type', Types::STRING, ['notnull' => true, 'length' => 16]);
        $table->addColumn('share_with', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->addColumn('permission', Types::INTEGER, ['notnull' => true, 'default' => 1]);
        $table->addColumn('token', Types::STRING, ['notnull' => false, 'length' => 64]);
        $table->addColumn('password', Types::STRING, ['notnull' => false, 'length' => 255]);
        $table->addColumn('expires_at', Types::BIGINT, ['notnull' => false]);
        $table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
        $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
        $table->addIndex(['recipe_id'], 'sc_share_recipe');
        $table->addIndex(['share_type', 'share_with'], 'sc_share_target');
        $table->addUniqueIndex(['token'], 'sc_share_token_uniq');
    }
}
