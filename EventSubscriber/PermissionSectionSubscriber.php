<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\EventSubscriber;

use App\Event\PermissionSectionsEvent;
use App\Model\PermissionSection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PermissionSectionSubscriber implements EventSubscriberInterface
{
    public const SECTION_TITLE = 'Period Insert';

    public static function getSubscribedEvents(): array
    {
        return [
            PermissionSectionsEvent::class => ['onEvent', 100],
        ];
    }

    public function onEvent(PermissionSectionsEvent $event): void
    {
        $event->addSection(new PermissionSection(self::SECTION_TITLE, '_period_insert'));
    }
}
