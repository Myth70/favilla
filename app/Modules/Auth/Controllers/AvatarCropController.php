<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Core\Controller;
use App\Modules\Auth\Helpers\AvatarHelper;
use App\Services\FileUploadService;
use App\Services\UserService;
use App\Traits\ControllerHelpers;

class AvatarCropController extends Controller
{
    use ControllerHelpers;

    /**
     * POST /api/avatar/crop
     *
     * Reusable endpoint for cropping and saving avatars.
     * Accepts a pre-cropped image blob from Cropper.js and saves it as 256x256 PNG.
     *
     * FormData:
     *   - cropped_image: Blob (PNG/JPEG from canvas)
     *   - context: "profile" | "team"
     *   - context_id: int
     */
    public function crop(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if (!$userId) {
            $this->json(['success' => false, 'error' => 'Non autenticato.'], 401);
            return;
        }

        $clean     = $this->cleanPost(['context_id']);
        $contextId = (int) ($clean['context_id'] ?? 0);

        if ($contextId !== $userId) {
            $this->json(['success' => false, 'error' => 'Non autorizzato.'], 403);
            return;
        }

        $context = 'profile';

        // Validate uploaded blob
        $file = $_FILES['cropped_image'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'error' => 'Nessuna immagine ricevuta.'], 400);
            return;
        }

        $prefix = 'avatar_' . $contextId . '_';

        try {
            $filename = FileUploadService::saveCroppedAvatar(
                $file['tmp_name'],
                'avatars',
                $prefix
            );
        } catch (\RuntimeException $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 400);
            return;
        }

        $userService = app(UserService::class);
        $userService->updateAvatar($contextId, $filename);
        $_SESSION['user_avatar'] = $filename;

        $this->json([
            'success'  => true,
            'filename' => $filename,
            'url'      => AvatarHelper::url($filename),
        ]);
    }

}
