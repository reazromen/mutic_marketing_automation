<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Model;

use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Event\LeadListEvent;
use Mautic\LeadBundle\LeadEvents;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * Class LeadListModel
 * {@inheritdoc}
 * @package Mautic\CoreBundle\Model\FormModel
 */
class LeadListModel extends FormModel
{

    /**
     * {@inheritdoc}
     */
    protected function init()
    {
        $this->repository = 'MauticLeadBundle:LeadList';
    }

    public function saveEntity($entity)
    {
        $isNew = ($entity->getId()) ? false : true;

        //set some defaults
        $this->setTimestamps($entity, $isNew);

        $alias = $entity->getAlias();
        if (empty($alias)) {
            $alias = strtolower(InputHelper::alphanum($entity->getName()));
        } else {
            $alias = strtolower(InputHelper::alphanum($alias));
        }

        //make sure alias is not already taken
        $testAlias = $alias;
        $user      = $this->security->getCurrentUser();
        $existing  = $this->em->getRepository('MauticLeadBundle:LeadList')->getUserSmartLists($user, $testAlias, $entity->getId());
        $count     = count($existing);
        $aliasTag  = $count;

        while ($count) {
            $testAlias = $alias . $aliasTag;
            $existing  = $this->em->getRepository('MauticLeadBundle:LeadList')->getUserSmartLists($user, $testAlias, $entity->getId());
            $count     = count($existing);
            $aliasTag++;
        }
        if ($testAlias != $alias) {
            $alias = $testAlias;
        }
        $entity->setAlias($alias);

        $event = $this->dispatchEvent("pre_save", $entity, $isNew);
        $this->em->getRepository($this->repository)->saveEntity($entity);
        $this->dispatchEvent("post_save", $entity, $isNew, $event);

        return $entity;
    }

    /**
     * {@inheritdoc}
     *
     * @param      $entity
     * @param      $formFactory
     * @param null $action
     * @return mixed
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    public function createForm($entity, $formFactory, $action = null)
    {
        if (!$entity instanceof LeadList) {
            throw new MethodNotAllowedHttpException(array('LeadList'), 'Entity must be of class LeadList()');
        }
        $params = (!empty($action)) ? array('action' => $action) : array();
        return $formFactory->create('leadlist', $entity, $params);
    }

    /**
     * Get a specific entity or generate a new one if id is empty
     *
     * @param $id
     * @return null|object
     */
    public function getEntity($id = null)
    {
        if ($id === null) {
            return new LeadList();
        }

        $entity = parent::getEntity($id);


        return $entity;
    }

    /**
     * {@inheritdoc}
     *
     * @param $action
     * @param $event
     * @param $entity
     * @param $isNew
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    protected function dispatchEvent($action, &$entity, $isNew = false, $event = false)
    {
        if (!$entity instanceof LeadList) {
            throw new MethodNotAllowedHttpException(array('LeadList'), 'Entity must be of class LeadList()');
        }

        if (empty($event)) {
            $event = new LeadListEvent($entity, $isNew);
            $event->setEntityManager($this->em);
        }

        switch ($action) {
            case "pre_save":
                $this->dispatcher->dispatch(LeadEvents::LIST_PRE_SAVE, $event);
                break;
            case "post_save":
                $this->dispatcher->dispatch(LeadEvents::LIST_POST_SAVE, $event);
                break;
            case "pre_delete":
                $this->dispatcher->dispatch(LeadEvents::LIST_PRE_DELETE, $event);
                break;
            case "post_delete":
                $this->dispatcher->dispatch(LeadEvents::LIST_POST_DELETE, $event);
                break;
        }

        return $event;
    }

    /**
     * @return mixed
     */
    public function getFilterExpressionFunctions()
    {
        return $this->em->getRepository('MauticLeadBundle:Lead')->getFilterExpressionFunctions();
    }


    /**
     * Get a list of field choices for filters
     *
     * @return array
     */
    public function getChoiceFields()
    {
        //field choices
        $choices = array(
            'dateAdded' => array(
                'label'       => $this->translator->trans('mautic.lead.list.filter.dateadded'),
                'properties'  => array('type' => 'date')
            ),
            'owner'     => array(
                'label'      => $this->translator->trans('mautic.lead.list.filter.owner'),
                'properties' => array(
                    'type'     => 'lookup_id',
                    'callback' => 'activateLeadFieldTypeahead'
                )
            ),
            'score'     => array(
                'label'      => $this->translator->trans('mautic.lead.list.filter.score'),
                'properties' => array('type' => 'number')
            )
        );

        //get list of custom fields
        $fields = $this->factory->getModel('leadfield')->getEntities(
            array('filter' => array(
                'isListable' => true
            ))
        );
        foreach ($fields as $field) {
            $type = $field->getType();
            $properties = $field->getProperties();
            $properties['type'] = $type;
            if (in_array($type, array('lookup', 'select', 'boolean'))) {
                $properties['callback'] = 'activateLeadFieldTypeahead';
                if ($type == 'boolean') {
                    //create a lookup list with ID
                    $properties['list'] = $properties['yes'].'|'.$properties['no'] . '||1|0';
                }
            }
            $choices["field_" . $field->getAlias()] = array(
                'label'      => $field->getLabel(),
                'properties' => $properties
            );
        }

        $cmp = function ($a, $b) {
            return strcmp($a["label"], $b["label"]);
        };

        uasort($choices, $cmp);
        return $choices;
    }

    /**
     * @return mixed
     */
    public function getSmartLists()
    {
        $user = (!$this->security->isGranted('lead:lists:viewother')) ?
            $this->security->getCurrentUser() : false;
        $lists = $this->em->getRepository('MauticLeadBundle:LeadList')->getUserSmartLists($user);
        return $lists;
    }
}