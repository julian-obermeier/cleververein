<?php

namespace Tests\Feature\Installation;

use Tests\TestCase;

class InstallerLockTest extends TestCase
{
    public function test_installer_redirects_when_lock_exists(): void
    {
        file_put_contents(storage_path('app/installed'), '{}');
        try {
            $this->get('/install')->assertRedirect(route('login'));
        } finally {
            @unlink(storage_path('app/installed'));
        }
    }
}
