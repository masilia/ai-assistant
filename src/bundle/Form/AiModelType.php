<?php

namespace Masilia\Bundle\AiAssistant\Form;

use Masilia\AiAssistant\Entity\AiModel;
use Masilia\AiAssistant\Entity\AiProvider;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class AiModelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('provider', EntityType::class, [
                'class' => AiProvider::class,
                'choice_label' => 'name',
                'label' => 'Associated Provider',
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please select a provider.']),
                ]
            ])
            ->add('name', TextType::class, [
                'label' => 'Model Display Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g. GPT-4o, Claude 3.5 Sonnet'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a model name.']),
                    new Length(['max' => 100]),
                ]
            ])
            ->add('identifier', TextType::class, [
                'label' => 'API Model Identifier',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g. gpt-4o, claude-3-5-sonnet-20241022'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter the API identifier.']),
                    new Length(['max' => 100]),
                ]
            ])
            ->add('temperature', NumberType::class, [
                'label' => 'Temperature (0.0 to 2.0)',
                'scale' => 2,
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.1',
                    'min' => '0.0',
                    'max' => '2.0'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please specify a temperature.']),
                    new GreaterThanOrEqual(['value' => 0.0, 'message' => 'Temperature cannot be lower than 0.0']),
                    new LessThanOrEqual(['value' => 2.0, 'message' => 'Temperature cannot be higher than 2.0']),
                ]
            ])
            ->add('maxTokens', IntegerType::class, [
                'label' => 'Max Tokens',
                'attr' => [
                    'class' => 'form-control',
                    'min' => '1'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please specify max tokens.']),
                    new GreaterThanOrEqual(['value' => 1, 'message' => 'Max tokens must be at least 1']),
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Enable this Model',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AiModel::class,
        ]);
    }
}
