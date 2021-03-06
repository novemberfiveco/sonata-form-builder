<?php
/**
 * Created By Andrea Pirastru
 * Date: 08/01/2014
 * Time: 17:28.
 */

namespace Pirastru\FormBuilderBundle\Block;

use Sonata\BlockBundle\Block\BlockContextInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\CoreBundle\Validator\ErrorElement;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\BlockBundle\Block\BaseBlockService;

/**
 * @author     Andrea Pirastru
 */
class FormBuilderBlockService extends BaseBlockService
{
    protected $formBuilderAdmin;

    /**
     * @param string             $name
     * @param EngineInterface    $templating
     * @param ContainerInterface $container
     */
    public function __construct($name, EngineInterface $templating, ContainerInterface $container)
    {
        parent::__construct($name, $templating);

        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function configureSettings(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'template' => 'PirastruFormBuilderBundle:Block:block_form_builder.html.twig',
            'formBuilderId' => null,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildEditForm(FormMapper $formMapper, BlockInterface $block)
    {
        $formMapper->add('settings', 'sonata_type_immutable_array', array(
            'keys' => array(
                array($this->getFieldFormBuilder($formMapper), null, array()),
            ),
        ));
    }

    /**
     * @return mixed
     */
    public function getFormBuilderAdmin()
    {
        if (!$this->formBuilderAdmin) {
            $this->formBuilderAdmin = $this->container->get('pirastru_form_builder.admin');
        }

        return $this->formBuilderAdmin;
    }

    /**
     * @param \Sonata\AdminBundle\Form\FormMapper $formMapper
     *
     * @return \Symfony\Component\Form\FormBuilder
     */
    protected function getFieldFormBuilder(FormMapper $formMapper)
    {
        // simulate an association ...
        $fieldDescription = $this->getFormBuilderAdmin()->getModelManager()->getNewFieldDescriptionInstance($this->formBuilderAdmin->getClass(), 'form_builder');
        $fieldDescription->setAssociationAdmin($this->getFormBuilderAdmin());
        $fieldDescription->setAdmin($formMapper->getAdmin());
        $fieldDescription->setOption('edit', 'list');
        $fieldDescription->setAssociationMapping(array(
            'fieldName' => 'form_builder',
            'type' => \Doctrine\ORM\Mapping\ClassMetadataInfo::MANY_TO_ONE,
        ));

        return $formMapper->create('formBuilderId', 'sonata_type_model', array(
            'sonata_field_description' => $fieldDescription,
            'label' => 'Form Builder',
            'class' => $this->getFormBuilderAdmin()->getClass(),
            'model_manager' => $this->getFormBuilderAdmin()->getModelManager(),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function validateBlock(ErrorElement $errorElement, BlockInterface $block)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function execute(BlockContextInterface $blockContext, Response $response = null)
    {
        $formBuilderId = $blockContext->getBlock()->getSetting('formBuilderId');
        $formBuilder = $this->container->get('doctrine')
            ->getRepository('PirastruFormBuilderBundle:FormBuilder')
            ->findOneBy(array('id' => $formBuilderId));

        // In case the FormBuilder Object is not defined
        // return a empty Response
        if ($formBuilder === null) {
            return $this->renderResponse($blockContext->getTemplate(), array(), $response);
        }

        $form_pack = $this->container->get('pirastru_form_builder.controller')
            ->generateFormFromFormBuilder($formBuilder);

        $form = $form_pack['form'];
        $success = false;
        $request = $this->container->get('request');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /***************************************
             * operations when the form Builder is submitted
             ***************************************/
            $this->preUpdate($blockContext->getBlock());

            $this->container->get('pirastru_form_builder.controller')
                ->submitOperations($formBuilder, $form_pack['title_col']);

            $success = true;
        }

        return $this->renderResponse($blockContext->getTemplate(), array(
            'formBuilderId' => $formBuilder->getId(),
            'block' => $blockContext->getBlock(),
            'settings' => $blockContext->getSettings(),
            'form' => $form->createView(),
            'title_col' => $form_pack['title_col'],
            'size_col' => $form_pack['size_col'],
            'success' => $success,
        ), $response);
    }

    /**
     * {@inheritdoc}
     */
    public function load(BlockInterface $block)
    {
        $formBuilderId = $block->getSetting('formBuilderId');

        if ($formBuilderId) {
            $formBuilderId = $this->container->get('doctrine')
                ->getRepository('PirastruFormBuilderBundle:FormBuilder')
                ->findOneBy(array('id' => $formBuilderId));
        }

        $block->setSetting('formBuilderId', $formBuilderId);
    }

    /**
     * {@inheritdoc}
     */
    public function prePersist(BlockInterface $block)
    {
        $block->setSetting('formBuilderId', is_object($block->getSetting('formBuilderId')) ? $block->getSetting('formBuilderId')->getId() : null);
    }

    /**
     * {@inheritdoc}
     */
    public function preUpdate(BlockInterface $block)
    {
        $block->setSetting('formBuilderId', is_object($block->getSetting('formBuilderId')) ? $block->getSetting('formBuilderId')->getId() : null);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'Form';
    }
}
