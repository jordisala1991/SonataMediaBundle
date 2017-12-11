<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Sonata\MediaBundle\Controller\MediaAdminController;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Command\DebugCommand;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Form\TwigRenderer;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class EntityWithGetId
{
    public function getId(): void
    {
    }
}

class MediaAdminControllerTest extends TestCase
{
    private $container;
    private $admin;
    private $request;
    private $controller;

    protected function setUp(): void
    {
        $this->container = $this->prophesize('Symfony\Component\DependencyInjection\ContainerInterface');
        $this->admin = $this->prophesize('Sonata\MediaBundle\Admin\BaseMediaAdmin');
        $this->request = $this->prophesize('Symfony\Component\HttpFoundation\Request');

        $this->configureCRUDController();

        $this->controller = new MediaAdminController();
        $this->controller->setContainer($this->container->reveal());
    }

    public function testCreateActionToSelectProvider(): void
    {
        $pool = $this->prophesize('Sonata\MediaBundle\Provider\Pool');

        $this->configureRender(
            'SonataMediaBundle:MediaAdmin:select_provider.html.twig',
            Argument::type('array'),
            'renderResponse'
        );
        $pool->getProvidersByContext('context')->willReturn(['provider']);
        $pool->getDefaultContext()->willReturn('default_context');
        $this->admin->checkAccess('create')->shouldBeCalled();
        $this->container->get('sonata.media.pool')->willReturn($pool->reveal());
        $this->request->get('provider')->willReturn(false);
        $this->request->isMethod('get')->willReturn(true);
        $this->request->get('context', 'default_context')->willReturn('context');

        $response = $this->controller->createAction($this->request->reveal());

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        $this->assertSame('renderResponse', $response->getContent());
    }

    public function testCreateAction(): void
    {
        $this->configureCreateAction('Sonata\MediaBundle\Tests\Entity\Media');
        $this->configureRender('template', Argument::type('array'), 'renderResponse');
        $this->admin->checkAccess('create')->shouldBeCalled();
        $this->request->get('provider')->willReturn(true);
        $this->request->isMethod('get')->willReturn(true);

        $response = $this->controller->createAction($this->request->reveal());

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        $this->assertSame('renderResponse', $response->getContent());
    }

    public function testListAction(): void
    {
        $datagrid = $this->prophesize('Sonata\AdminBundle\Datagrid\DatagridInterface');
        $pool = $this->prophesize('Sonata\MediaBundle\Provider\Pool');
        $categoryManager = $this->prophesize('Sonata\MediaBundle\Model\CategoryManagerInterface');
        $category = $this->prophesize();
        $category->willExtend('Sonata\MediaBundle\Tests\Controller\EntityWithGetId');
        $category->willImplement('Sonata\ClassificationBundle\Model\CategoryInterface');
        $form = $this->prophesize('Symfony\Component\Form\Form');
        $formView = $this->prophesize('Symfony\Component\Form\FormView');

        $this->configureSetFormTheme($formView->reveal(), 'filterTheme');
        $this->configureSetCsrfToken('sonata.batch');
        $this->configureRender('templateList', Argument::type('array'), 'renderResponse');
        $datagrid->setValue('context', null, 'another_context')->shouldBeCalled();
        $datagrid->setValue('category', null, 1)->shouldBeCalled();
        $datagrid->getForm()->willReturn($form->reveal());
        $pool->getDefaultContext()->willReturn('context');
        $categoryManager->getRootCategory('another_context')->willReturn($category->reveal());
        $categoryManager->findOneBy([
            'id' => 2,
            'context' => 'another_context',
        ])->willReturn($category->reveal());
        $category->getId()->willReturn(1);
        $form->createView()->willReturn($formView->reveal());
        $this->container->get('sonata.media.pool')->willReturn($pool->reveal());
        $this->container->has('sonata.media.manager.category')->willReturn(true);
        $this->container->get('sonata.media.manager.category')->willReturn($categoryManager->reveal());
        $this->admin->checkAccess('list')->shouldBeCalled();
        $this->admin->setListMode('mosaic')->shouldBeCalled();
        $this->admin->getDatagrid()->willReturn($datagrid->reveal());
        $this->admin->getPersistentParameter('context', 'context')->willReturn('another_context');
        $this->admin->getFilterTheme()->willReturn('filterTheme');
        $this->admin->getTemplate('list')->willReturn('templateList');
        $this->request->get('_list_mode', 'mosaic')->willReturn('mosaic');
        $this->request->get('filter')->willReturn([]);
        $this->request->get('category')->willReturn(2);

        $response = $this->controller->listAction($this->request->reveal());

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        $this->assertSame('renderResponse', $response->getContent());
    }

    private function configureCRUDController(): void
    {
        $pool = $this->prophesize('Sonata\AdminBundle\Admin\Pool');
        $breadcrumbsBuilder = $this->prophesize('Sonata\AdminBundle\Admin\BreadcrumbsBuilderInterface');

        $this->configureGetCurrentRequest($this->request->reveal());
        $pool->getAdminByAdminCode('admin_code')->willReturn($this->admin->reveal());
        $this->request->isXmlHttpRequest()->willReturn(false);
        $this->request->get('_xml_http_request')->willReturn(false);
        $this->request->get('_sonata_admin')->willReturn('admin_code');
        $this->request->get('uniqid')->shouldBeCalled();
        $this->container->get('sonata.admin.pool')->willReturn($pool->reveal());
        $this->container->get('sonata.admin.breadcrumbs_builder')->willReturn($breadcrumbsBuilder->reveal());
        $this->admin->getTemplate('layout')->willReturn('layout.html.twig');
        $this->admin->isChild()->willReturn(false);
        $this->admin->setRequest($this->request->reveal())->shouldBeCalled();
    }

    private function configureCreateAction($class): void
    {
        $object = $this->prophesize('Sonata\MediaBundle\Tests\Entity\Media');
        $form = $this->prophesize('Symfony\Component\Form\Form');
        $formView = $this->prophesize('Symfony\Component\Form\FormView');

        $this->configureSetFormTheme($formView->reveal(), 'formTheme');
        $this->admin->hasActiveSubClass()->willReturn(false);
        $this->admin->getClass()->willReturn($class);
        $this->admin->getNewInstance()->willReturn($object->reveal());
        $this->admin->setSubject($object->reveal())->shouldBeCalled();
        $this->admin->getForm()->willReturn($form->reveal());
        $this->admin->getFormTheme()->willReturn('formTheme');
        $this->admin->getTemplate('edit')->willReturn('template');
        $form->createView()->willReturn($formView->reveal());
        $form->setData($object->reveal())->shouldBeCalled();
        $form->handleRequest($this->request->reveal())->shouldBeCalled();
        $form->isSubmitted()->willReturn(false);
    }

    private function configureGetCurrentRequest($request): void
    {
        $requestStack = $this->prophesize(RequestStack::class);

        $this->container->has('request_stack')->willReturn(true);
        $this->container->get('request_stack')->willReturn($requestStack->reveal());
        $requestStack->getCurrentRequest()->willReturn($request);
    }

    private function configureSetFormTheme($formView, $formTheme): void
    {
        $twig = $this->prophesize(\Twig_Environment::class);

        // Remove this trick when bumping Symfony requirement to 3.4+
        if (method_exists(DebugCommand::class, 'getLoaderPaths')) {
            $rendererClass = FormRenderer::class;
        } else {
            $rendererClass = TwigRenderer::class;
        }

        $twigRenderer = $this->prophesize($rendererClass);

        $this->container->get('twig')->willReturn($twig->reveal());

        // Remove this trick when bumping Symfony requirement to 3.2+.
        if (method_exists(AppVariable::class, 'getToken')) {
            $twig->getRuntime($rendererClass)->willReturn($twigRenderer->reveal());
        } else {
            $formExtension = $this->prophesize(FormExtension::class);
            $formExtension->renderer = $twigRenderer->reveal();

            // This Throw is for the CRUDController::setFormTheme()
            $twig->getRuntime($rendererClass)->willThrow(\Twig_Error_Runtime::class);
            $twig->getExtension(FormExtension::class)->willReturn($formExtension->reveal());
        }
        $twigRenderer->setTheme($formView, $formTheme)->shouldBeCalled();
    }

    private function configureSetCsrfToken($intention): void
    {
        $tokenManager = $this->prophesize(CsrfTokenManagerInterface::class);
        $token = $this->prophesize(CsrfToken::class);

        $tokenManager->getToken($intention)->willReturn($token->reveal());
        $token->getValue()->willReturn('token');
        $this->container->has('security.csrf.token_manager')->willReturn(true);
        $this->container->get('security.csrf.token_manager')->willReturn($tokenManager->reveal());
    }

    private function configureRender($template, $data, $rendered): void
    {
        $templating = $this->prophesize('Symfony\Bundle\FrameworkBundle\Templating\EngineInterface');
        $response = $this->prophesize('Symfony\Component\HttpFoundation\Response');
        $pool = $this->prophesize('Sonata\MediaBundle\Provider\Pool');

        $this->admin->getPersistentParameters()->willReturn(['param' => 'param']);
        $this->container->has('templating')->willReturn(true);
        $this->container->get('templating')->willReturn($templating->reveal());
        $this->container->get('sonata.media.pool')->willReturn($pool->reveal());
        $response->getContent()->willReturn($rendered);
        $templating->renderResponse($template, $data, null)->willReturn($response->reveal());
        $templating->render($template, $data)->willReturn($rendered);
    }
}
