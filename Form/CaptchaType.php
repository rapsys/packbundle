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

use Rapsys\PackBundle\Util\ImageUtil;
use Rapsys\PackBundle\Util\SluggerUtil;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;

/**
 * Captcha Type class definition
 *
 * @see https://symfony.com/doc/current/form/create_custom_field_type.html
 */
class CaptchaType extends AbstractType {
	/**
	 * Constructor
	 *
	 * @param ImageUtil $image
	 * @param SluggerUtil $slugger
	 * @param TranslatorInterface $translator The translator instance
	 */
	public function __construct(protected ImageUtil $image, protected SluggerUtil $slugger, protected TranslatorInterface $translator) {
	}

	/**
	 * {@inheritdoc}
	 *
	 * Build form
	 */
	public function buildForm(FormBuilderInterface $builder, array $options): void {
		//Set captcha
		$captcha = $this->image->getCaptcha((new \DateTime('-1 year'))->getTimestamp());

		//Add captcha token
		$builder->add('_captcha_token', HiddenType::class, ['data' => $captcha['token'], 'empty_data' => $captcha['token']]);

		//Add captcha
		$builder->add('captcha', IntegerType::class, ['label_attr' => ['class' => 'captcha'], 'label' => '<img src="'.htmlentities($captcha['src']).'" alt="'.htmlentities($captcha['equation']).'" />', 'label_html' => true, 'translation_domain' => false]);

		//Add event listener on captcha
		$builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'validateCaptcha']);
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
