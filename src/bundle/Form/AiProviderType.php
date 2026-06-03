<?php

namespace Masilia\Bundle\AiAssistant\Form;

use Masilia\Bundle\AiAssistant\Entity\AiProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class AiProviderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Provider Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g. OpenAI, Anthropic, Ollama'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a name for the provider.']),
                    new Length(['max' => 100]),
                ]
            ])
            ->add('identifier', ChoiceType::class, [
                'label' => 'Service Type',
                'choices' => [
                    'OpenAI' => 'openai',
                    'Anthropic' => 'anthropic',
                    'Mistral' => 'mistral',
                    'MiniMax' => 'minimax',
                    'Ollama / Custom Local' => 'ollama',
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please select a service type.']),
                ]
            ])
            ->add('apiKey', PasswordType::class, [
                'label' => 'API Key / Secret',
                'required' => false,
                'always_empty' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter API Key'
                ],
                'constraints' => [
                    new Length(['max' => 255]),
                ]
            ])
            ->add('apiUrl', UrlType::class, [
                'label' => 'Custom Endpoint URL',
                'required' => false,
                'default_protocol' => 'http',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g. http://localhost:11434/v1 (leave empty for default)'
                ],
                'constraints' => [
                    new Length(['max' => 255]),
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Enable this Provider',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AiProvider::class,
        ]);
    }
}
