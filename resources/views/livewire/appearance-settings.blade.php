<div>
    <x-slot:title>
        Appearance | Coolify
    </x-slot>
    <x-settings.navbar />

    <h2>Appearance</h2>
    <div class="subtitle">
        Optional corporate-grade modern UI theme with refined colors for both light and dark mode.
        This only changes visual styling; no layout or behavior changes.
    </div>

    <div class="flex flex-col gap-2 pt-4 max-w-2xl">
        <x-forms.checkbox
            id="enhancedThemeEnabled"
            instantSave="saveEnhancedTheme"
            label="Use enhanced theme"
            helper="Applies the enhanced UI palette instance-wide. Refresh your browser after toggling to see the new styles everywhere."
        />
    </div>
</div>
