<?php

namespace App\Observers;

use App\Models\Attachment;
use App\Models\Company;

class AttachmentObserver
{
    /**
     * Handle the Attachment "creating" event.
     * When creating a new attachment for a Company, delete the old logo if it exists.
     * This ensures only one logo exists per company.
     */
    public function creating(Attachment $attachment): void
    {
        // If this attachment is being created for a Company
        if ($attachment->model_type === Company::class && $attachment->model_id) {
            // Find and delete the existing logo directly from the database
            Attachment::where('model_type', Company::class)
                ->where('model_id', $attachment->model_id)
                ->delete();
        }
    }
}

