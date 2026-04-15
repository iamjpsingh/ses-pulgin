<?php

declare(strict_types=1);

namespace XtrusioPlugin\XtrusioAmazonSesBundle;

final class SesEvents
{
    /**
     * Dispatched when evaluating the "SES: Contact Bounced" campaign condition.
     */
    public const ON_CAMPAIGN_SES_BOUNCE_CHECK = 'xtrusio.plugin.ses.campaign.bounce_check';

    /**
     * Dispatched when evaluating the "SES: Contact Complained" campaign condition.
     */
    public const ON_CAMPAIGN_SES_COMPLAINT_CHECK = 'xtrusio.plugin.ses.campaign.complaint_check';
}
