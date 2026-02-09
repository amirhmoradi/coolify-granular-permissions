<?php

namespace AmirhMoradi\CoolifyEnhanced\Http\Middleware;

use AmirhMoradi\CoolifyEnhanced\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\HttpFoundation\Response;

class InjectPermissionsUI
{
    /**
     * Inject UI components into Coolify pages:
     * - Access matrix on team admin page
     * - Encryption settings on storage detail pages
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Must be HTML, successful, and authenticated
        if (! $this->isInjectableResponse($response) || ! auth()->check()) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false) {
            return $response;
        }

        $injections = '';

        // Inject access matrix on team admin page
        if ($request->routeIs('team.admin-view') && PermissionService::hasRoleBypass(auth()->user())) {
            $component = $this->renderAccessMatrix();
            if (! empty($component)) {
                $injections .= $this->wrapWithInjector($component);
            }
        }

        // Inject encryption form on storage detail page
        if ($request->routeIs('storage.show')) {
            $storageUuid = $request->route('storage_uuid');
            $encryptionForm = $this->renderEncryptionForm($storageUuid);
            if (! empty($encryptionForm)) {
                $injections .= $this->wrapWithStorageInjector($encryptionForm);
            }
        }

        if (! empty($injections)) {
            $content = str_replace('</body>', $injections.'</body>', $content);
            $response->setContent($content);
        }

        return $response;
    }

    /**
     * Check if the response is injectable (HTML, successful).
     */
    protected function isInjectableResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html') && $response->isSuccessful();
    }

    /**
     * Render the Livewire access matrix component.
     */
    protected function renderAccessMatrix(): string
    {
        try {
            return Blade::render('@livewire(\'enhanced::access-matrix\')');
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
    }

    /**
     * Render the encryption form for a specific S3 storage.
     */
    protected function renderEncryptionForm(?string $storageUuid): string
    {
        if (empty($storageUuid)) {
            return '';
        }

        try {
            $storage = \App\Models\S3Storage::where('uuid', $storageUuid)->first();
            if (! $storage) {
                return '';
            }

            return Blade::render(
                '@livewire(\'enhanced::storage-encryption-form\', [\'storageId\' => '.$storage->id.'])'
            );
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
    }

    /**
     * Wrap the rendered component with a container and positioning script.
     *
     * Coolify's layout structure:
     *   <main class="lg:pl-56">
     *     <div class="p-4 sm:px-6 lg:px-8 lg:py-6">
     *       <div> <!-- Livewire component root (admin-view) -->
     *         <x-team.navbar />
     *         <h2>Admin View</h2>
     *         ...content...
     *       </div>
     *     </div>
     *   </main>
     *
     * We append our section inside the Livewire component root div.
     */
    protected function wrapWithInjector(string $componentHtml): string
    {
        return <<<HTML

<!-- Coolify Granular Permissions - Injected Access Matrix -->
<div id="granular-permissions-inject" style="display:none;">
    {$componentHtml}
</div>
<script data-navigate-once>
(function() {
    function isAdminPage() {
        return window.location.pathname === '/team/admin';
    }

    function positionPermissionsUI() {
        var wrapper = document.getElementById('granular-permissions-inject');
        if (!wrapper) return;

        // Only show on team admin page — hide on all other pages
        if (!isAdminPage()) {
            wrapper.style.display = 'none';
            wrapper.dataset.positioned = '';
            return;
        }

        if (wrapper.dataset.positioned === 'true') return;

        // Target: the Livewire admin-view component root div inside main content
        // Coolify structure: main.lg\\:pl-56 > div.p-4 > div (livewire root)
        var target = document.querySelector('main > div > div > div:first-child');

        // Fallback: find the div containing "Admin View" heading
        if (!target) {
            var headings = document.querySelectorAll('h2');
            for (var i = 0; i < headings.length; i++) {
                if (headings[i].textContent.trim() === 'Admin View') {
                    target = headings[i].closest('div');
                    break;
                }
            }
        }

        // Final fallback: main content padding div
        if (!target) {
            target = document.querySelector('main > div');
        }

        if (target && target !== wrapper) {
            target.appendChild(wrapper);
            wrapper.dataset.positioned = 'true';
        }

        wrapper.style.display = 'block';
    }

    // Run after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', positionPermissionsUI);
    } else {
        positionPermissionsUI();
    }

    // Re-run after Livewire SPA navigation (wire:navigate)
    document.addEventListener('livewire:navigated', function() {
        var wrapper = document.getElementById('granular-permissions-inject');
        if (wrapper) wrapper.dataset.positioned = '';
        setTimeout(positionPermissionsUI, 50);
    });
})();
</script>
<!-- End Coolify Enhanced - Access Matrix -->

HTML;
    }

    /**
     * Wrap the encryption form with a container and positioning script.
     *
     * Coolify's storage form page structure:
     *   <form class="flex flex-col gap-2 pb-6" wire:submit='submit'>
     *     ... storage fields ...
     *     <button>Validate Connection</button>
     *   </form>
     *
     * We inject our encryption form after the storage form.
     */
    protected function wrapWithStorageInjector(string $componentHtml): string
    {
        return <<<HTML

<!-- Coolify Enhanced - Encryption Settings -->
<div id="enhanced-encryption-inject" style="display:none;">
    {$componentHtml}
</div>
<script data-navigate-once>
(function() {
    function isStoragePage() {
        return window.location.pathname.startsWith('/storages/');
    }

    function positionEncryptionUI() {
        var wrapper = document.getElementById('enhanced-encryption-inject');
        if (!wrapper) return;

        if (!isStoragePage()) {
            wrapper.style.display = 'none';
            wrapper.dataset.positioned = '';
            return;
        }

        if (wrapper.dataset.positioned === 'true') return;

        // Target: the storage form's parent div
        var storageForm = document.querySelector('form[wire\\\\:submit\\.prevent="submit"], form[wire\\\\:submit="submit"]');
        var target = storageForm ? storageForm.parentElement : null;

        // Fallback: find "Storage Details" heading
        if (!target) {
            var headings = document.querySelectorAll('h1');
            for (var i = 0; i < headings.length; i++) {
                if (headings[i].textContent.trim() === 'Storage Details') {
                    target = headings[i].closest('div');
                    break;
                }
            }
        }

        if (!target) {
            target = document.querySelector('main > div > div');
        }

        if (target && target !== wrapper) {
            target.appendChild(wrapper);
            wrapper.dataset.positioned = 'true';
        }

        wrapper.style.display = 'block';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', positionEncryptionUI);
    } else {
        positionEncryptionUI();
    }

    document.addEventListener('livewire:navigated', function() {
        var wrapper = document.getElementById('enhanced-encryption-inject');
        if (wrapper) wrapper.dataset.positioned = '';
        setTimeout(positionEncryptionUI, 50);
    });
})();
</script>
<!-- End Coolify Enhanced - Encryption Settings -->

HTML;
    }
}
