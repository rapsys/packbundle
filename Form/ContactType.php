<?php declare(strict_types=1);

/*
 * This file is part of the Rapsys PackBundle package.
 *
 * (c) Raphaël Gertz <symfony@rapsys.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rapsys\PackBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * {@inheritdoc}
 */
class ContactType extends CaptchaType {
	/**
	 * {@inheritdoc}
	 */
	public function buildForm(FormBuilderInterface $builder, array $options): void {
		//Add fields
		$builder
			->add('name', TextType::class, ['attr' => ['placeholder' => 'Your name'], 'constraints' => [new NotBlank(['message' => 'Please provide your name'])]])
			->add('subject', TextType::class, ['attr' => ['placeholder' => 'Subject'], 'constraints' => [new NotBlank(['message' => 'Please provide your subject'])]])
			->add('mail', EmailType::class, ['attr' => ['placeholder' => 'Your mail'], 'constraints' => [new NotBlank(['message' => 'Please provide a valid mail']), new Email(['message' => 'Your mail doesn\'t seems to be valid'])]])
			->add('message', TextareaType::class, ['attr' => ['placeholder' => 'Your message', 'cols' => 50, 'rows' => 15], 'constraints' => [new NotBlank(['message' => 'Please provide your message'])]])
			->add('submit', SubmitType::class, ['label' => 'Send', 'attr' => ['class' => 'submit']]);

		//Call parent
		parent::buildForm($builder, $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver): void {
		//Call parent configure options
		parent::configureOptions($resolver);

		//Set defaults
		$resolver->setDefaults(['captcha' => true]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return 'contact_form';
	}
}
