<?php

namespace Base\StaticBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Base\StaticBundle\Entity\Page;
use Base\StaticBundle\Form\PageType;
use APY\DataGridBundle\Grid\Source\Entity;
use APY\DataGridBundle\Grid\Action\RowAction;
use APY\DataGridBundle\Grid\Column\ActionsColumn;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Page controller.
 */
class PageController extends Controller
{
    /**
     * Show for static menu group.
     *
     * @param string  $groupName
     * @param Request $request
     *
     * @return mixed
     */
    public function staticMenuAction($groupName, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $entities = $em->getRepository('BaseStaticBundle:Page')
            ->findBy(
                [
                    'groupName' => $groupName,
                    'language' => $request->getLocale(),
                ],
                ['position' => 'ASC']
            );

        return $this->container->get('templating')->renderResponse(
            'BaseStaticBundle::staticMenu.html.twig',
            [
                'pages' => $entities,
            ]
        );
    }

    /**
     * Show for static info block group.
     *
     * @param string  $groupName
     * @param Request $request
     *
     * @return mixed
     */
    public function staticInfoAction($groupName, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $entities = $em->getRepository('BaseStaticBundle:Page')
            ->findBy(
                [
                    'groupName' => $groupName,
                    'language' => $request->getLocale(),
                ],
                ['position' => 'ASC']
            );

        return $this->container->get('templating')->renderResponse(
            'BaseStaticBundle::staticInfo.html.twig',
            [
                'pages' => $entities,
            ]
        );
    }

    /**
     * Lists all Page entities.
     *
     * @Route("/pages.html", name="page")
     * @Template()
     *
     * @return mixed
     */
    public function indexAction()
    {
        $source = new Entity('BaseStaticBundle:Page');

        /* @var $grid \APY\DataGridBundle\Grid\Grid */
        $grid = $this->get('grid');

        $tableAlias = $source->getTableAlias();
        $source->manipulateQuery(
            function ($query) use ($tableAlias) {
                $query->resetDQLPart('orderBy');
                $query->addOrderBy($tableAlias . '.groupName', 'ASC');
                $query->addOrderBy($tableAlias . '.position', 'ASC');
            }
        );

        $grid->setSource($source);
        $grid->setNoResultMessage($this->get('translator')->trans('No data'));

        // Custom colums config.
        $grid->hideColumns('id');

        /* @var $column \APY\DataGridBundle\Grid\Column\Column */
        $column = $grid->getColumn('title');
        $column->setOperators(['like']);
        $column->setOperatorsVisible(false);
        $column->setDefaultOperator('like');
        $column->setSortable(false);
        $column->setTitle($this->get('translator')->trans('form.title', [], 'StaticBundle'));

        $column = $grid->getColumn('groupName');
        $column->setOperators(['like']);
        $column->setOperatorsVisible(false);
        $column->setDefaultOperator('like');
        $column->setSortable(false);
        $column->setTitle($this->get('translator')->trans('form.group', [], 'StaticBundle'));
        $column->setValues(
            [
                'help' => $this->get('translator')->trans('form.help', [], 'StaticBundle'),
                'front_page' => $this->get('translator')->trans('form.front_page', [], 'StaticBundle'),
            ]
        );

        // Add actions column.
        $rowAction = new RowAction($this->get('translator')->trans('Edit'), 'page_edit');
        $actionsColumn = new ActionsColumn(
            'info_column',
            $this->get('translator')->trans('Actions'),
            [$rowAction],
            '<br/>'
        );
        $actionsColumn->setSize(110);
        $grid->addColumn($actionsColumn);

        return $grid->getGridResponse('BaseStaticBundle::Page\index.html.twig');
    }

    /**
     * Creates a new Page entity.
     *
     * @param Request $request
     *
     * @Route("/pages/", name="page_create")
     * @Method("POST")
     * @Template("BaseStaticBundle:Page:new.html.twig")
     *
     * @return mixed
     */
    public function createAction(Request $request)
    {
        $entity = new Page();
        $form = $this->createCreateForm($entity);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            $position = $em->getRepository('BaseStaticBundle:Page')
                    ->getMaxTextPosition($entity->getGroupName())['max_position'] + 1;
            if (empty($position)) {
                $position = 1;
            }

            $entity->setPosition($position);

            $em->persist($entity);
            $em->flush();

            $this->get('session')->getFlashBag()->add('notice', 'messages.created');

            return $this->redirect($this->generateUrl('page'));
        }

        return [
            'entity' => $entity,
            'form' => $form->createView(),
        ];
    }

    /**
     * Creates a form to create a Page entity.
     *
     * @param Page $entity The entity.
     *
     * @return \Symfony\Component\Form\Form
     */
    private function createCreateForm(Page $entity)
    {
        $form = $this->createForm(
            new PageType(),
            $entity,
            [
                'action' => $this->generateUrl('page_create'),
                'method' => 'POST',
            ]
        );

        $form->add('submit', 'submit', ['label' => 'Create']);

        return $form;
    }

    /**
     * Displays a form to create a new Page entity.
     *
     * @Route("/pages/new.html", name="page_new")
     * @Method("GET")
     * @Template()
     *
     * @return array
     */
    public function newAction()
    {
        $entity = new Page();
        $form = $this->createCreateForm($entity);

        return [
            'entity' => $entity,
            'form' => $form->createView(),
        ];
    }

    /**
     * Finds and displays a Page entity.
     *
     * @param string $slug
     *
     * @Route("/page/{slug}.html", name="page_show")
     * @Method("GET")
     * @Template()
     *
     * @throws NotFoundHttpException
     *
     * @return array
     */
    public function showAction($slug)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var Page $entity */
        $entity = $em->getRepository('BaseStaticBundle:Page')->findOneBy(['slug' => $slug]);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Page entity.');
        }
        $entity->setText(str_replace('<table', '<table class="list"', $entity->getText()));

        return [
            'entity' => $entity,
        ];
    }

    /**
     * Displays a form to edit an existing Page entity.
     *
     * @param int $id
     *
     * @Route("/pages/{id}/edit.html", name="page_edit")
     * @Method("GET")
     * @Template()
     *
     * @throws NotFoundHttpException
     *
     * @return array
     */
    public function editAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('BaseStaticBundle:Page')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Page entity.');
        }

        $editForm = $this->createEditForm($entity);
        $deleteForm = $this->createDeleteForm($id);

        return [
            'entity' => $entity,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ];
    }

    /**
     * Creates a form to edit a Page entity.
     *
     * @param Page $entity The entity.
     *
     * @return \Symfony\Component\Form\Form
     */
    private function createEditForm(Page $entity)
    {
        $form = $this->createForm(
            new PageType(),
            $entity,
            [
                'action' => $this->generateUrl('page_update', ['id' => $entity->getId()]),
                'method' => 'PUT',
            ]
        );

        $form->add('submit', 'submit', ['label' => 'Update']);

        return $form;
    }

    /**
     * Edits an existing Page entity.
     *
     * @param Request $request
     * @param int     $id
     *
     * @Route("/pages/{id}", name="page_update")
     * @Method("PUT")
     * @Template("BaseStaticBundle:Page:edit.html.twig")
     *
     * @throws NotFoundHttpException
     *
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function updateAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var Page $entity */
        $entity = $em->getRepository('BaseStaticBundle:Page')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Page entity.');
        }

        $deleteForm = $this->createDeleteForm($id);
        $editForm = $this->createEditForm($entity);
        $editForm->handleRequest($request);

        if ($editForm->isValid()) {
            $entity->setSlug(null);

            $em->flush();

            $this->get('session')->getFlashBag()->add('notice', 'messages.updated');

            return $this->redirect($this->generateUrl('page_edit', ['id' => $id]));
        }

        return [
            'entity' => $entity,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ];
    }

    /**
     * Deletes a Page entity.
     *
     * @param Request $request
     * @param int     $id
     *
     * @Route("/pages/{id}", name="page_delete")
     * @Method("DELETE")
     *
     * @throws NotFoundHttpException
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction(Request $request, $id)
    {
        $form = $this->createDeleteForm($id);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $entity = $em->getRepository('BaseStaticBundle:Page')->find($id);

            if (!$entity) {
                throw $this->createNotFoundException('Unable to find Page entity.');
            }

            $em->remove($entity);
            $em->flush();

            $this->get('session')->getFlashBag()->add('notice', 'messages.deleted');
        }

        return $this->redirect($this->generateUrl('page'));
    }

    /**
     * Creates a form to delete a Page entity by id.
     *
     * @param mixed $id The entity id.
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm($id)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('page_delete', ['id' => $id]))
            ->setMethod('DELETE')
            ->getForm();
    }

    /**
     * Change position of shop group text.
     *
     * @param int $id
     *
     * @Route("/pages/{id}/up", name="page_up")
     * @Method("GET")
     *
     * @throws NotFoundHttpException
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function upPagePositionAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var Page $entityText */
        $entityText = $em->getRepository('BaseStaticBundle:Page')->find($id);

        if (!$entityText) {
            throw $this->createNotFoundException('Unable to find Page entity.');
        }

        if ($entityText->getPosition() > 1) {
            $newPosition = $entityText->getPosition() - 1;
            /** @var Page $entityTextSwap */
            $entityTextSwap = $em->getRepository('BaseStaticBundle:Page')
                ->getNextUpPosition($newPosition, $entityText->getGroupName());

            if ($entityTextSwap) {
                $entityTextSwap->setPosition($entityText->getPosition());
                $entityText->setPosition($newPosition);

                $em->merge($entityText);
                $em->merge($entityTextSwap);
                $em->flush();
            }
        }

        return $this->redirect($this->generateUrl('page'));
    }

    /**
     * Change position of shop group text.
     *
     * @param int $id
     *
     * @Route("/pages/{id}/down", name="page_down")
     * @Method("GET")
     *
     * @throws NotFoundHttpException
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function downPagePositionAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var Page $entityText */
        $entityText = $em->getRepository('BaseStaticBundle:Page')->find($id);

        if (!$entityText) {
            throw $this->createNotFoundException('Unable to find Page entity.');
        }

        $newPosition = $entityText->getPosition() + 1;
        /** @var Page $entityTextSwap */
        $entityTextSwap = $em->getRepository('BaseStaticBundle:Page')
            ->getNextDownPosition($newPosition, $entityText->getGroupName());

        if ($entityTextSwap) {
            $entityTextSwap->setPosition($entityText->getPosition());
            $entityText->setPosition($newPosition);

            $em->merge($entityText);
            $em->merge($entityTextSwap);
            $em->flush();
        }

        return $this->redirect($this->generateUrl('page'));
    }
}
