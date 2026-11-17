<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Webtack\Ecms\Database\Factories\UserFactory;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Models\MenuItemTranslation;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Models\Term;
use Webtack\Ecms\Models\TermTranslation;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Support\UserModel;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * Every model that has a factory finds it in the package namespace (`Webtack\Ecms\Database\Factories`), not by the
 * framework's guess `Database\Factories\<Model>Factory` of a host application.
 */
class FactoryResolutionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string}> */
    public static function models(): array
    {
        return collect([
            AppLanguage::class, ContentField::class, ContentNode::class, ContentType::class, File::class, Menu::class,
            MenuItem::class, MenuItemTranslation::class, Role::class, Term::class, TermTranslation::class, Vocabulary::class,
        ])->mapWithKeys(fn (string $class) => [class_basename($class) => [$class]])->all();
    }

    /**
     * @param  class-string  $model
     */
    #[DataProvider('models')]
    public function test_the_factory_of_a_model_is_the_package_factory(string $model): void
    {
        /** @var Factory $factory */
        $factory = $model::factory();

        $this->assertStringStartsWith('Webtack\Ecms\Database\Factories\\', $factory::class);
        $this->assertInstanceOf($model, $factory->make());
    }

    public function test_every_model_with_a_factory_trait_is_covered(): void
    {
        $covered = array_values(array_map(fn (array $row) => $row[0], self::models()));
        $found = [];

        foreach (glob($this->packagePath('src/Models/*.php')) as $file) {
            $class = 'Webtack\\Ecms\\Models\\'.basename($file, '.php');

            if (in_array(HasFactory::class, class_uses($class), true)) {
                $found[] = $class;
            }
        }

        $this->assertEqualsCanonicalizing($found, $covered, 'A model got a factory: add it to FactoryResolutionTest::models()');
    }

    public function test_the_user_factory_builds_the_configured_user_model(): void
    {
        $this->assertSame(User::class, UserModel::class());
        $this->assertInstanceOf(User::class, User::factory()->create());

        // The model name comes from the user resolver, so a host model works the same way.
        config(['auth.providers.users.model' => HostUser::class]);

        $this->assertSame(HostUser::class, (new UserFactory)->modelName());
    }
}

/** A user model of another name, the way a host defines it. */
class HostUser extends User {}
