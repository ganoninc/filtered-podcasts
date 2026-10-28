<?php

namespace App\Form;

use App\Entity\PodcastFeed;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class PodcastFeedType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 255),
                ],
            ])
            ->add('sourceUrl', TextType::class, [
                'label' => 'Source RSS URL',
                'constraints' => [
                    new NotBlank(),
                    new Url(),
                    new Length(max: 2048),
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'help' => 'Optional label shown in the filtered feed and admin screens.',
            ])
            ->add('filters', CollectionType::class, [
                'entry_type' => FilterRuleType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'prototype' => true,
                'help' => 'Add as many title or duration filters as needed. Rules are cumulative per category.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PodcastFeed::class,
        ]);
    }
}
