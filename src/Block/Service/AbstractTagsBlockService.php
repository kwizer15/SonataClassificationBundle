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

namespace Sonata\ClassificationBundle\Block\Service;

use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\BlockBundle\Block\BlockContextInterface;
use Sonata\BlockBundle\Meta\Metadata;
use Sonata\BlockBundle\Model\BlockInterface;
use Sonata\ClassificationBundle\Model\ContextManagerInterface;
use Sonata\ClassificationBundle\Model\TagInterface;
use Sonata\ClassificationBundle\Model\TagManagerInterface;
use Sonata\Form\Type\ImmutableArrayType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

/**
 * @author Christian Gripp <mail@core23.de>
 */
abstract class AbstractTagsBlockService extends AbstractClassificationBlockService
{
    /**
     * @var TagManagerInterface
     */
    private $tagManager;

    /**
     * @var AdminInterface|null
     */
    private $tagAdmin;

    public function __construct(
        Environment $twig,
        ContextManagerInterface $contextManager,
        TagManagerInterface $tagManager,
        ?AdminInterface $tagAdmin
    ) {
        parent::__construct($twig, $contextManager);

        $this->tagManager = $tagManager;
        $this->tagAdmin = $tagAdmin;
    }

    public function execute(BlockContextInterface $blockContext, ?Response $response = null): Response
    {
        $tag = $this->getTag($blockContext->getSetting('tagId'), $blockContext->getSetting('tag'));
        $tags = $this->tagManager->findBy([
            'enabled' => true,
            'context' => $blockContext->getSetting('context'),
        ]);

        return $this->renderResponse($blockContext->getTemplate(), [
            'context' => $blockContext,
            'settings' => $blockContext->getSettings(),
            'block' => $blockContext->getBlock(),
            'tag' => $tag,
            'tags' => $tags,
        ], $response);
    }

    public function buildEditForm(FormMapper $formMapper, BlockInterface $block): void
    {
        if (null === $this->tagAdmin) {
            throw new \BadMethodCallException('You need the sonata-project/admin-bundle library to edit this block.');
        }

        $adminField = $this->getFormAdminType($formMapper, $this->tagAdmin, 'tagId', 'tag', [
            'label' => 'form.label_tag',
        ], [
            'translation_domain' => 'SonataClassificationBundle',
            'link_parameters' => [
                [
                    'context' => $block->getSetting('context'),
                ],
            ],
        ]);

        $formMapper->add(
            'settings',
            ImmutableArrayType::class,
            [
                'keys' => [
                    ['title', TextType::class, [
                        'required' => false,
                        'label' => 'form.label_title',
                    ]],
                    ['translation_domain', TextType::class, [
                        'label' => 'form.label_translation_domain',
                        'required' => false,
                    ]],
                    ['icon', TextType::class, [
                        'label' => 'form.label_icon',
                        'required' => false,
                    ]],
                    ['class', TextType::class, [
                        'label' => 'form.label_class',
                        'required' => false,
                    ]],
                    ['context', ChoiceType::class, [
                        'label' => 'form.label_context',
                        'required' => false,
                        'choices' => $this->getContextChoices(),
                    ]],
                    [$adminField, null, []],
                ],
                'translation_domain' => 'SonataClassificationBundle',
            ]
        );
    }

    public function configureSettings(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'title' => null,
            'translation_domain' => null,
            'icon' => 'fa fa-tags',
            'class' => null,
            'tag' => false,
            'tagId' => null,
            'context' => null,
            'template' => '@SonataClassification/Block/base_block_tags.html.twig',
        ]);
    }

    public function load(BlockInterface $block): void
    {
        if (is_numeric($block->getSetting('tagId'))) {
            $block->setSetting('tagId', $this->getTag($block->getSetting('tagId')));
        }
    }

    public function prePersist(BlockInterface $block): void
    {
        $this->resolveIds($block);
    }

    public function preUpdate(BlockInterface $block): void
    {
        $this->resolveIds($block);
    }

    public function getBlockMetadata($code = null)
    {
        $description = (null !== $code ? $code : $this->getName());

        return new Metadata($this->getName(), $description, false, 'SonataClassificationBundle', [
            'class' => 'fa fa-tags',
        ]);
    }

    /**
     * @param TagInterface|int $id
     * @param mixed            $default
     *
     * @return TagInterface|null
     */
    final protected function getTag($id, $default = null)
    {
        if ($id instanceof TagInterface) {
            return $id;
        }

        if (is_numeric($id)) {
            return $this->tagManager->find($id);
        }

        if ($default instanceof TagInterface) {
            return $default;
        }

        return null;
    }

    private function resolveIds(BlockInterface $block): void
    {
        $block->setSetting(
            'tagId',
            \is_object($block->getSetting('tagId')) ? $block->getSetting('tagId')->getId() : null
        );
    }
}
