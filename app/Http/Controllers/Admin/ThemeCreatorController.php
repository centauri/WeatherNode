<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\CustomTheme;
use App\Support\PublicAppearance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ThemeCreatorController extends Controller
{
    public function edit()
    {
        $presets = CustomTheme::presets();

        return view('admin.settings.theme-creator', [
            'presets' => $presets,
            'draft' => CustomTheme::stored() ?? $presets[PublicAppearance::settings()['palette']],
            'tokens' => CustomTheme::TOKENS,
            'palettes' => PublicAppearance::PALETTES,
        ]);
    }

    public function preview()
    {
        return response()->view('admin.settings.theme-preview')->header('Cache-Control', 'private, no-store');
    }

    private function document(Request $request): array
    {
        $request->validate(['theme' => ['required', 'string', 'max:'.CustomTheme::MAX_BYTES]]);

        return CustomTheme::decode($request->input('theme'));
    }

    public function validateImport(Request $request)
    {
        return response()->json(['theme' => $this->document($request)])->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request)
    {
        $theme = $this->document($request);
        $filename = (Str::slug($theme['name']) ?: 'weathernode-theme').'.json';

        return response(json_encode($theme, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n")
            ->header('Content-Type', 'application/json')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('Cache-Control', 'private, no-store');
    }

    public function save(Request $request)
    {
        $theme = $this->document($request);
        $request->validate(['action' => ['required', 'in:save,apply']]);
        DB::transaction(function () use ($theme, $request) {
            Setting::setValue('appearance.custom_theme', $theme, 'json', 'appearance');
            if ($request->input('action') === 'apply') {
                Setting::setValue('appearance.active_custom_theme', $theme, 'json', 'appearance');
                Setting::setValue('appearance.palette', 'custom', 'select', 'appearance');
            }
        });

        return redirect()->route('admin.settings.theme-creator')->with('success', $request->input('action') === 'apply' ? __('Theme saved and applied.') : __('Theme saved.'));
    }

    public function reset()
    {
        DB::transaction(function () {
            Setting::setValue('appearance.palette', 'weathernode', 'select', 'appearance');
            Setting::setValue('appearance.color_mode', 'dark', 'select', 'appearance');
        });

        return redirect()->route('admin.settings.theme-creator')->with('success', __('Original theme restored. Your custom theme is still saved.'));
    }
}
