<?php

namespace AmirhMoradi\CoolifyEnhanced\Livewire;

use App\Models\S3Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Livewire component for managing S3 backup encryption settings.
 *
 * Injected into Coolify's storage form page via middleware. Provides
 * a UI for configuring rclone crypt encryption on S3 destinations.
 */
class StorageEncryptionForm extends Component
{
    use AuthorizesRequests;

    public ?int $storageId = null;

    public bool $encryptionEnabled = false;

    public string $encryptionPassword = '';

    public string $encryptionSalt = '';

    public string $filenameEncryption = 'off';

    public bool $directoryNameEncryption = false;

    public string $saveMessage = '';

    public string $saveStatus = '';

    protected function rules(): array
    {
        return [
            'encryptionEnabled' => 'boolean',
            'encryptionPassword' => 'required_if:encryptionEnabled,true|max:255',
            'encryptionSalt' => 'nullable|max:255',
            'filenameEncryption' => 'in:off,standard,obfuscate',
            'directoryNameEncryption' => 'boolean',
        ];
    }

    public function mount(int $storageId): void
    {
        $storage = S3Storage::findOrFail($storageId);
        $this->authorize('update', $storage);

        $this->storageId = $storage->id;
        $this->encryptionEnabled = (bool) $storage->encryption_enabled;
        $this->encryptionPassword = $storage->encryption_password ?? '';
        $this->encryptionSalt = $storage->encryption_salt ?? '';
        $this->filenameEncryption = $storage->filename_encryption ?? 'off';
        $this->directoryNameEncryption = (bool) $storage->directory_name_encryption;
    }

    public function save(): void
    {
        try {
            $storage = S3Storage::findOrFail($this->storageId);
            $this->authorize('update', $storage);

            if ($this->encryptionEnabled && empty($this->encryptionPassword)) {
                $this->dispatch('error', 'Encryption password is required when encryption is enabled.');

                return;
            }

            $storage->encryption_enabled = $this->encryptionEnabled;
            $storage->encryption_password = $this->encryptionEnabled ? $this->encryptionPassword : null;
            $storage->encryption_salt = $this->encryptionEnabled ? ($this->encryptionSalt ?: null) : null;
            $storage->filename_encryption = $this->encryptionEnabled ? $this->filenameEncryption : 'off';
            $storage->directory_name_encryption = $this->encryptionEnabled ? $this->directoryNameEncryption : false;
            $storage->save();

            $this->saveMessage = 'Encryption settings saved.';
            $this->saveStatus = 'success';
            $this->dispatch('success', 'Encryption settings saved successfully.');
        } catch (\Throwable $e) {
            $this->saveMessage = 'Failed to save: '.$e->getMessage();
            $this->saveStatus = 'error';
            $this->dispatch('error', 'Failed to save encryption settings.', $e->getMessage());
        }
    }

    public function render()
    {
        return view('coolify-enhanced::livewire.storage-encryption-form');
    }
}
