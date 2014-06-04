<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\UserBundle\Form\Type;

use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Form\EventListener\CleanFormSubscriber;
use Mautic\CoreBundle\Form\EventListener\FormExitSubscriber;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class RoleType
 *
 * @package Mautic\UserBundle\Form\Type
 */
class RoleType extends AbstractType
{

    private $translator;
    private $em;


    public function __construct(TranslatorInterface $translator, EntityManager $em) {
        $this->translator = $translator;
        $this->em         = $em;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm (FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new CleanFormSubscriber());
        $builder->addEventSubscriber(new FormExitSubscriber($this->translator->trans(
            'mautic.core.form.inform'
        )));

        $builder->add('name', 'text', array(
            'label'      => 'mautic.user.role.form.name',
            'label_attr' => array('class' => 'control-label'),
            'attr'       => array('class' => 'form-control')
        ));

        $builder->add('description', 'text', array(
            'label'      => 'mautic.user.role.form.description',
            'label_attr' => array('class' => 'control-label'),
            'attr'       => array('class' => 'form-control'),
            'required' => false
        ));

        $builder->add('isAdmin', 'choice', array(
            'choice_list' => new ChoiceList(
                array(false, true),
                array('mautic.core.form.no', 'mautic.core.form.yes')
            ),
            'expanded'      => true,
            'multiple'      => false,
            'label'         => 'mautic.user.role.form.isadmin',
            'attr'          => array(
                'onclick' => 'Mautic.togglePermissionVisibility();'
            ),
            'empty_value'   => false,
            'required'      => false
        ));

        // add a normal text field, but add your transformer to it
        $hidden = ($options['data']->isAdmin()) ? ' hide' : '';

        //get current permissions saved to the database for this role if applicable
        $permissionsArray = array();
        if ($options['data']->getId()) {
           $repo             = $this->em->getRepository('MauticUserBundle:Permission');
           $permissionsArray = $repo->getPermissionsByRole($options['data'], true);
        }

        $builder->add(
            'permissions', 'permissions', array(
                'label'    => 'mautic.user.role.form.permissions',
                'mapped'   => false, //we'll have to manually build the permissions for persisting
                'required' => false,
                'attr'     => array(
                    'class' => $hidden
                ),
                'permissions'     => $permissionsArray
            )
        );

        $builder->add('save', 'submit', array(
            'label' => 'mautic.core.form.save',
            'attr'  => array(
                'class' => 'btn btn-primary',
                'icon'  => 'fa fa-check padding-sm-right'
            ),
        ));

        $builder->add('cancel', 'submit', array(
            'label' => 'mautic.core.form.cancel',
            'attr'  => array(
                'class'   => 'btn btn-danger',
                'icon'    => 'fa fa-times padding-sm-right'
            )
        ));

        if (!empty($options["action"])) {
            $builder->setAction($options["action"]);
        }
    }


    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class'         => 'Mautic\UserBundle\Entity\Role',
            'cascade_validation' => true
        ));
    }

    /**
     * @return string
     */
    public function getName() {
        return "role";
    }
}