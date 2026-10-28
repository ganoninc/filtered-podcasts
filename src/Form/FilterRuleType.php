<?php

namespace App\Form;

use App\Entity\PodcastFeed;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FilterRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', ChoiceType::class, [
                'choices' => PodcastFeed::categoryChoices(),
            ])
            ->add('operator', ChoiceType::class, [
                'choices' => PodcastFeed::operatorChoicesByCategory()[PodcastFeed::CATEGORY_TITLE],
            ])
            ->add('value', TextType::class, [
                'help' => 'For title filters, enter text. For duration filters, enter minutes.',
            ])
            ->add('combinator', ChoiceType::class, [
                'choices' => PodcastFeed::combinatorChoices(),
                'help' => 'This combines the rule with the previous rule in the same category.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
