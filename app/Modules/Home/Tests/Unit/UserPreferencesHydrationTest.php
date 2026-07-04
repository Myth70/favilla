<?php

namespace App\Modules\Home\Tests\Unit;

use App\Core\Controller;
use Tests\ModuleTestCase;

class UserPreferencesHydrationTest extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrate(
            'CREATE TABLE user_preferences (
                user_id INTEGER PRIMARY KEY,
                theme TEXT NOT NULL,
                primary_color TEXT NOT NULL,
                sidebar_collapsed INTEGER NOT NULL,
                sidebar_style TEXT NOT NULL,
                background_pattern TEXT NOT NULL,
                theme_skin TEXT NOT NULL,
                font_family TEXT NOT NULL,
                language TEXT NOT NULL
            )'
        );
    }

    public function testGetUserPreferencesHydratesBackgroundPatternIntoSessionCache(): void
    {
        $this->insertRow('user_preferences', [
            'user_id' => 42,
            'theme' => 'dark',
            'primary_color' => '#ef4444',
            'sidebar_collapsed' => 1,
            'sidebar_style' => 'accent',
            'background_pattern' => 'mesh',
            'theme_skin' => 'sharp',
            'font_family' => 'plex',
            'language' => 'es',
        ]);

        $_SESSION['user_id'] = 42;

        $controller = new class () extends Controller {
        };

        $method = new \ReflectionMethod(Controller::class, 'getUserPreferences');
        $method->setAccessible(true);

        $preferences = $method->invoke($controller);

        $this->assertSame('mesh', $preferences['background_pattern']);
        $this->assertSame('mesh', $_SESSION['user_preferences']['background_pattern']);
        $this->assertSame('sharp', $_SESSION['user_preferences']['theme_skin']);
        $this->assertSame('plex', $_SESSION['user_preferences']['font_family']);
        $this->assertSame('es', $_SESSION['user_preferences']['language']);
    }
}
