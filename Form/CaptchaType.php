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

use Rapsys\PackBundle\RapsysPackBundle;
use Rapsys\PackBundle\Util\ImageUtil;
use Rapsys\PackBundle\Util\SluggerUtil;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Captcha Type class definition
 *
 * @see https://symfony.com/doc/current/form/create_custom_field_type.html
 */
class CaptchaType extends AbstractType {
	/**
	 * Constructor
	 *
	 * @param ?ImageUtil $image The image instance
	 * @param ?SluggerUtil $slugger The slugger instance
	 * @param ?TranslatorInterface $translator The translator instance
	 */
	public function __construct(protected ?ImageUtil $image = null, protected ?SluggerUtil $slugger = null, protected ?TranslatorInterface $translator = null) {
	}

	/**
	 * {@inheritdoc}
	 *
	 * Build form
	 */
	public function buildForm(FormBuilderInterface $builder, array $options): void {
		//With image, slugger and translator
		if (!empty($options['captcha']) && $this->image !== null && $this->slugger !== null && $this->translator !== null) {
			//Set captcha
			$captcha = $this->image->getCaptcha();

			//Add captcha token
			$builder->add('_captcha_token', HiddenType::class, ['data' => $captcha['token'], 'empty_data' => $captcha['token'], 'mapped' => false]);

			//Add captcha
			$builder->add('captcha', IntegerType::class, ['label_attr' => ['class' => 'captcha'], 'label' => '<img src="'.htmlentities($captcha['src']).'" alt="'.htmlentities($captcha['equation']).'" />', 'label_html' => true, 'mapped' => false, 'translation_domain' => false]);

			//Add event listener on captcha
			$builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'validateCaptcha']);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function configureOptions(OptionsResolver $resolver): void {
		//Call parent configure options
		parent::configureOptions($resolver);

		//Set defaults
		$resolver->setDefaults(['captcha' => false, 'error_bubbling' => true, 'translation_domain' => RapsysPackBundle::getAlias()]);

		//Add extra captcha option
		$resolver->setAllowedTypes('captcha', 'boolean');
	}

	/**
	 * Validate captcha
	 *
	 * @param FormEvent $event The form event
	 */
	public function validateCaptcha(FormEvent $event): void {
		//Get form
		$form = $event->getForm();

		//Get event data
		$data = $event->getData();

		//Set token
		$token = $form->get('_captcha_token')->getConfig()->getData();

		//Without captcha
		if (empty($data['captcha'])) {
			//Add error on captcha
			$form->addError(new FormError($this->translator->trans('Captcha is empty')));

			//Reset captcha token
			$data['_captcha_token'] = $token;

			//Set event data
			$event->setData($data);
		//With invalid captcha
		} elseif ($this->slugger->hash($data['captcha']) !== $data['_captcha_token']) {
			//Add error on captcha
			$form->addError(new FormError($this->translator->trans('Captcha is invalid')));

			//Reset captcha token
			$data['_captcha_token'] = $token;

			//Set event data
			$event->setData($data);
		}
	}
}
