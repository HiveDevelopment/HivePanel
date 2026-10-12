<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\AdminThemeController;
use App\Http\Controllers\Settings\ThemeController;
use App\Models\AppSetting;
use App\Support\PanelThemes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PanelThemesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id(); $table->string('key'); $table->text('value'); $table->timestamps();
        });
    }

    private function request(array $data, bool $admin = true): Request
    {
        $request = Request::create('/', 'PUT', $data);
        $request->setUserResolver(fn () => new class($admin) {
            public function __construct(private bool $admin) {}
            public function hasAdminPermission($permission): bool { return $this->admin; }
        });
        return $request;
    }

    public function test_unoffered_themes_are_filtered(): void
    {
        $catalog = PanelThemes::catalog(); $catalog['themes'][1]['enabled'] = false;
        AppSetting::create(['key' => 'themes', 'value' => $catalog]);
        $this->assertSame(['hive', 'forest'], array_column(PanelThemes::offered()['themes'], 'id'));
    }

    public function test_default_must_be_offered(): void
    {
        $catalog = PanelThemes::catalog(); $catalog['themes'][0]['enabled'] = false;
        $this->expectException(ValidationException::class);
        (new AdminThemeController())->update($this->request($catalog));
    }

    public function test_invalid_css_is_rejected(): void
    {
        $catalog = PanelThemes::catalog(); $catalog['themes'][0]['tokens']['accent'] = 'url(example)';
        $this->expectException(ValidationException::class);
        (new AdminThemeController())->update($this->request($catalog));
    }

    public function test_users_cannot_select_unoffered_themes(): void
    {
        $this->expectException(ValidationException::class);
        (new ThemeController())->update($this->request(['theme_id' => 'unoffered']));
    }

    public function test_non_admin_cannot_edit_themes(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new AdminThemeController())->update($this->request(PanelThemes::catalog(), false));
    }
}
