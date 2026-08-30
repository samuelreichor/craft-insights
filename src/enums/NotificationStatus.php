<?php

namespace samuelreichor\insights\enums;

/**
 * Notification status enum
 *
 * Lifecycle of a row in the notification log audit table.
 */
enum NotificationStatus: string
{
    /**
     * A report job was pushed to the queue and hasn't finished yet.
     * Blocks further jobs from being queued for the same frequency.
     */
    case Queued = 'queued';

    /**
     * The report email was delivered successfully.
     */
    case Sent = 'sent';

    /**
     * The delivery failed; errorMessage holds the reason.
     */
    case Failed = 'failed';

    /**
     * The job ran but didn't send — the report was no longer due or the
     * configured frequency changed since the job was queued.
     */
    case Skipped = 'skipped';
}
