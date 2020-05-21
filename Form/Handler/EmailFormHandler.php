<?php

namespace Lexik\Bundle\MailerBundle\Form\Handler;

use Doctrine\ORM\EntityManager;
use Lexik\Bundle\MailerBundle\Entity\Email;
use Lexik\Bundle\MailerBundle\Entity\EmailTranslation;
use Lexik\Bundle\MailerBundle\Form\Model\EntityTranslationModel;
use Lexik\Bundle\MailerBundle\Form\Type\EmailType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Languages;

/**
 * @author Cédric Girard <c.girard@lexik.fr>
 */
class EmailFormHandler implements FormHandlerInterface
{
    /**
     * @var \Symfony\Component\Form\FormFactoryInterface
     */
    private $factory;

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * @var string
     */
    private $locale;
    private $supportedLocales;

    /**
     * @param FormFactoryInterface $factory
     * @param EntityManager $em
     * @param string $defaultLocale
     * @param $supportedLocales
     */
    public function __construct(FormFactoryInterface $factory, EntityManager $em, $defaultLocale, $supportedLocales)
    {
        $this->factory          = $factory;
        $this->em               = $em;
        $this->defaultLocale    = $defaultLocale;
        $this->supportedLocales = $supportedLocales;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * {@inheritdoc}
     */
    public function createForm($email = null, $lang = null)
    {
        $edit         = ($email !== null);
        $this->locale = $lang ?: $this->defaultLocale;

        if ($edit) {
            $translation = $email->getTranslation($this->locale);
        } else {
            $email       = new Email();
            $translation = new EmailTranslation($this->defaultLocale);
            $translation->setEmail($email);
        }

        $model = new EntityTranslationModel($email, $translation);

        $supportedLocaleChoices = $this->getSupportedLocaleChoices();
        if (!empty($lang) and !in_array($lang, $supportedLocaleChoices)) {
            throw new \Exception(sprintf(
                'Unsupported language: %s. Please check your LexikMailerBundle configuration.',
                $lang
            ));
        }

        return $this->factory->create(EmailType::class, $model, [
            'data_translation'  => $translation,
            'edit'              => $edit,
            'supported_locales' => $supportedLocaleChoices
        ]);
    }

    private function getSupportedLocaleChoices()
    {
        $choices = [];
        foreach (preg_split('/[ ,]/', $this->supportedLocales, -1, PREG_SPLIT_NO_EMPTY) as $locale) {
            $alpha2 = substr($locale, 0, 2);
            try {
                $name = Languages::getName($alpha2);
            } catch (MissingResourceException $e) {
                $name = '- unsupported -';
            }
            $label           = sprintf('%s [%s]', $name, $locale);
            $choices[$label] = $locale;
        }

        return $choices;
    }

    /**
     * {@inheritdoc}
     */
    public function processForm(FormInterface $form, Request $request)
    {
        $valid = false;
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $model = $form->getData();
            $model->getEntity()->addTranslation($model->getTranslation());

            $this->em->persist($model->getEntity());
            $this->em->flush();

            $valid = true;
        }

        return $valid;
    }
}
