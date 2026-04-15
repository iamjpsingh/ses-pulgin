<?php

declare(strict_types=1);

namespace XtrusioPlugin\XtrusioAmazonSesBundle\EventSubscriber;

use Xtrusio\LeadBundle\Entity\DoNotContact;
use Xtrusio\LeadBundle\Event\LeadListFiltersChoicesEvent;
use Xtrusio\LeadBundle\LeadEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentFilterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        $events = [
            LeadEvents::LIST_FILTERS_CHOICES_ON_GENERATE => ['onGenerateSegmentFilters', 0],
        ];

        // Register filtering handler if the event exists in this Xtrusio version
        if (defined('Xtrusio\\LeadBundle\\LeadEvents::LIST_FILTERS_ON_FILTERING')) {
            $events[LeadEvents::LIST_FILTERS_ON_FILTERING] = ['onListFiltering', 0];
        }

        return $events;
    }

    public function onGenerateSegmentFilters(LeadListFiltersChoicesEvent $event): void
    {
        $event->addChoice('lead', 'ses_bounced', [
            'label'      => $this->translator->trans('plugin.xtrusioamazonses.segment.ses_bounced'),
            'properties' => ['type' => 'boolean'],
            'operators'  => ['include' => ['eq']],
            'object'     => 'lead',
        ]);

        $event->addChoice('lead', 'ses_complained', [
            'label'      => $this->translator->trans('plugin.xtrusioamazonses.segment.ses_complained'),
            'properties' => ['type' => 'boolean'],
            'operators'  => ['include' => ['eq']],
            'object'     => 'lead',
        ]);
    }

    /**
     * Handles the SQL query building for SES segment filters.
     * This method is only called if Xtrusio dispatches the LIST_FILTERS_ON_FILTERING event.
     */
    public function onListFiltering(mixed $event): void
    {
        if (!method_exists($event, 'getDetails') || !method_exists($event, 'getQueryBuilder')) {
            return;
        }

        $details = $event->getDetails();
        $alias   = $details['field'] ?? '';

        if (!in_array($alias, ['ses_bounced', 'ses_complained'], true)) {
            return;
        }

        $filterValue = (int) ($details['filter'] ?? 1);
        $qb          = $event->getQueryBuilder();
        $tablePrefix = XTRUSIO_TABLE_PREFIX;

        if ('ses_bounced' === $alias) {
            $reason         = DoNotContact::BOUNCED;
            $commentPattern = 'SES Bounce%';
        } else {
            $reason         = DoNotContact::UNSUBSCRIBED;
            $commentPattern = 'SES Complaint%';
        }

        $paramSuffix = $alias;
        $subQuery    = sprintf(
            'SELECT dnc.lead_id FROM %slead_donotcontact dnc WHERE dnc.channel = :dncChannel_%s AND dnc.reason = :dncReason_%s AND dnc.comments LIKE :dncComment_%s',
            $tablePrefix,
            $paramSuffix,
            $paramSuffix,
            $paramSuffix
        );

        if (1 === $filterValue) {
            $qb->andWhere("l.id IN ({$subQuery})");
        } else {
            $qb->andWhere("l.id NOT IN ({$subQuery})");
        }

        $qb->setParameter("dncChannel_{$paramSuffix}", 'email');
        $qb->setParameter("dncReason_{$paramSuffix}", $reason);
        $qb->setParameter("dncComment_{$paramSuffix}", $commentPattern);

        if (method_exists($event, 'setFilteringStatus')) {
            $event->setFilteringStatus(true);
        }
    }
}
