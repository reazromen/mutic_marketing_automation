<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\UserBundle\Form\Type;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class PermissionsType
 *
 * @package Mautic\UserBundle\Form\Type
 */
class PermissionsType extends AbstractType
{

    private $security;
    private $translator;

    /**
     * @param TranslatorInterface      $translator
     * @param CorePermissions $security
     */
    public function __construct(TranslatorInterface $translator, CorePermissions $security) {
        $this->translator = $translator;
        $this->security   = $security;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm (FormBuilderInterface $builder, array $options)
    {
        $permissionClasses = $this->security->getPermissionClasses();

        $builder->add("permissions-panel-wrapper-start", 'panel_wrapper_start', array(
            'attr' => array(
                'id' => "permissions-panel"
            )
        ));

        foreach ($permissionClasses as $class) {
            if ($class->isEnabled()) {
                $bundle = $class->getName();
                //convert the permission bits from the db into readable names
                $data    = $class->convertBitsToPermissionNames($options['permissions']);
                //get the ratio of granted/total
                list($granted, $total) = $class->getPermissionRatio($data);
                $ratio = (!empty($total)) ?
                          ' <span class="permission-ratio">('
                            . '<span class="' . $bundle . '_granted">' . $granted . '</span>/'
                            . '<span class="' . $bundle . '_total">' . $total . '</span>'
                        . ')</span>'
                    : "";
                $label   = $this->translator->trans("mautic.{$bundle}.permissions.header") . $ratio;
                $builder->add("{$bundle}-panel-start", 'panel_start', array(
                    'label'      => $label,
                    'dataParent' => "permissions-panel",
                    'bodyId'     => "{$bundle}-panel"
                ));
                $class->buildForm($builder, $options, $data);

                $builder->add("{$bundle}-panel-end", 'panel_end');
            }
        }

        $builder->add("permissions-panel-wrapper-end", 'panel_wrapper_end');

        if (!empty($options["action"])) {
            $builder->setAction($options["action"]);
        }
    }

    /**
     * @return string
     */
    public function getName() {
        return "permissions";
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'cascade_validation' => true,
            'permissions'        => array()
        ));
    }
}