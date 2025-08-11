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
use App\Validator\ValidationFailedException;
use KimaiPlugin\PeriodInsertBundle\Entity\PeriodInsert;
use KimaiPlugin\PeriodInsertBundle\Form\PeriodInsertForm;
use KimaiPlugin\PeriodInsertBundle\Form\PeriodInsertPreCreateForm;
use KimaiPlugin\PeriodInsertBundle\Repository\PeriodInsertRepository;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/period_insert')]
#[IsGranted('period_insert')]
final class PeriodInsertController extends AbstractController
{
    public function __construct(
        protected readonly PeriodInsertRepository $repository,
        protected readonly TimesheetService $timesheetService,
        protected readonly SystemConfiguration $configuration
    )
    {
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route(path: '', name: 'period_insert', methods: ['GET', 'POST'])]
    public function indexAction(Request $request): Response
    {
        $timesheet = $this->timesheetService->createNewTimesheet($this->getUser(), $request);

        $periodInsert = new PeriodInsert();
        $periodInsert->setUser($this->getUser());
        $periodInsert->setMetaFields($timesheet->getMetaFields());
        if (!$this->timesheetService->getActiveTrackingMode()->canEditBegin()) {
            $periodInsert->setBeginTime($timesheet->getBegin());
        }

        $preForm = $this->createFormForGetRequest(PeriodInsertPreCreateForm::class, $periodInsert, [
            'include_user' => $this->isGranted('create_other_timesheet'),
        ]);
        $preForm->submit($request->query->all(), false);

        $form = $this->getInsertForm($periodInsert, $timesheet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->repository->savePeriodInsert($periodInsert);
                $this->flashSuccess('action.update.success');
                
                return $this->redirectToRoute('period_insert');
            } catch (ValidationFailedException $ex) {
                $this->handleFormUpdateException($ex, $form);
            }
        }

        return $this->render('@PeriodInsert/index.html.twig', [
            'page_setup' => $this->createPageSetup(),
            'route_back' => 'timesheet',
            'period_insert' => $periodInsert,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param PeriodInsert $periodInsert
     * @param Timesheet $timesheet
     * @return FormInterface
     */
    private function getInsertForm(PeriodInsert $periodInsert, Timesheet $timesheet): FormInterface
    {
        return $this->createForm(PeriodInsertForm::class, $periodInsert, [
            'action' => $this->generateUrl('period_insert'),
            'include_user' => $this->isGranted('create_other_timesheet'),
            'include_rate' => $this->isGranted('edit_rate', $timesheet),
            'include_billable' => $this->isGranted('edit_billable', $timesheet),
            'include_exported' => $this->isGranted('edit_export', $timesheet),
            'allow_begin_datetime' => $this->timesheetService->getActiveTrackingMode()->canEditBegin(),
            'duration_minutes' => $this->configuration->getTimesheetIncrementDuration(),
            'timezone' => $this->getDateTimeFactory()->getTimezone()->getName(),
        ]);
    }

    /**
     * @return PageSetup
     */
    private function createPageSetup(): PageSetup
    {
        $page = new PageSetup('periodinsert');
        $page->setHelp('https://www.kimai.org/store/lnngyn-period-insert-bundle.html');

        return $page;
    }
}
