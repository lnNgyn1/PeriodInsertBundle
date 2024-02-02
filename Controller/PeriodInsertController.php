<?php

/*
 * This file is part of the PeriodInsertBundle.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\PeriodInsertBundle\Controller;

use App\Configuration\SystemConfiguration;
use App\Controller\AbstractController;
use App\Entity\Timesheet;
use App\Timesheet\TimesheetService;
use App\Utils\PageSetup;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;
use KimaiPlugin\PeriodInsertBundle\Form\PeriodInsertType;
use KimaiPlugin\PeriodInsertBundle\Repository\PeriodInsertRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/period_insert')]
#[IsGranted('period_insert')]
class PeriodInsertController extends AbstractController
{
    public function __construct(
        protected PeriodInsertRepository $repository,
        protected TimesheetService $service,
        protected SystemConfiguration $configuration)
    {
    }

    #[Route(path: '', name: 'period_insert', methods: ['GET', 'POST'])]
    public function indexAction(Request $request): Response
    {
        $entry = $this->service->createNewTimesheet($this->getUser(), $request);

        $entity = $this->repository->getTimesheet();
        $entity->setUser($this->getUser());

        $form = $this->getInsertForm($entity, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var PeriodInsert $entity */
            $entity = $form->getData();

            if ($entity->getDurationPerDay() > 0) {
                try {
                    $this->repository->saveTimesheet($entity);
                    $this->flashSuccess('action.update.success');

                    return $this->redirectToRoute('period_insert');
                } catch (Exception $ex) {
                    $this->flashUpdateException($ex);
                }
            }
            else {
                $this->flashError('need_timerange');
            }
        }

        $page = new PageSetup('periodinsert.title');
        $page->setHelp('https://github.com/lnNgyn1/PeriodInsertBundle');

        return $this->render('@PeriodInsert/index.html.twig', [
            'page_setup' => $page,
            'route_back' => 'timesheet',
            'entity' => $entity,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param PeriodInsert $entity
     * @param Timesheet $entry
     * @return FormInterface
     */
    protected function getInsertForm(PeriodInsert $entity, Timesheet $entry)
    {
        return $this->createForm(PeriodInsertType::class, $entity, [
            'action' => $this->generateUrl('period_insert'),
            'include_user' => $this->isGranted('ROLE_SUPER_ADMIN'),
            'include_rate' => $this->isGranted('edit_rate', $entry),
            'include_billable' => $this->isGranted('edit_billable', $entry),
            'include_exported' => $this->isGranted('edit_export', $entry),
            'create_activity' => $this->isGranted('create_activity'),
            'duration_minutes' => $this->configuration->getTimesheetIncrementDuration(),
            'timezone' => $this->getDateTimeFactory()->getTimezone()->getName(),
            'customer' => true,
        ]);
    }
}
