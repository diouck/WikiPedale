<?php

namespace Progracqteur\WikipedaleBundle\Resources\Services;

use Doctrine\Common\Persistence\ObjectManager;
use Progracqteur\WikipedaleBundle\Resources\Security\ChangesetInterface;
use Progracqteur\WikipedaleBundle\Entity\Model\Report\ReportTracking;
use Progracqteur\WikipedaleBundle\Resources\Security\ChangeService;

/**
 * A service that make changeset consistent: it replace the value with proper
 * object extracted from the entity manager
 *
 * @author Julien Fastré <julien arobase fastre point info>
 */
class ChangesetConsistent {
    /**
     *
     * @var Doctrine\Common\Persistence\ObjectManager 
     */
    private $om;
    
    public function __construct(ObjectManager $om) {
        $this->om = $om;
    }
    
    /**
     * 
     * @param \Progracqteur\WikipedaleBundle\Resources\Security\ChangesetInterface $changeset
     * @return array
     */
    public function getChanges(ChangesetInterface $changeset) {
        if ($changeset instanceof ReportTracking) {
            $changes = $changeset->getChanges();
            
            $correctedChanges = array();
            //replace the change with proper objects
            foreach ($changes as $key => $value) {
                switch($value->getType()) {
                    default: 
                        $correctedChanges[$value->getType()] = $value->getNewValue();
                        break;
                    case ChangeService::REPORT_ADD_PHOTO:
                        $correctedChanges[$value->getType()] = $this->om->
                            getRepository('ProgracqteurWikipedaleBundle:Model\Photo')
                                ->findBy(array('filename' => $value->getNewValue()));
                        break;
                    case ChangeService::REPORT_ADD_CATEGORY:
                    case ChangeService::REPORT_REMOVE_CATEGORY:
                        //foreach category in array
                        $a = $value->getNewValue(); 
                        $b = array();
                        foreach($a as $categoryId) {
                            $b[] = $this->om
                                    ->getRepository('ProgracqteurWikipedaleBundle:Model\Category')
                                    ->find($categoryId);       
                        }
                        $correctedChanges[$value->getType()] = $b;
                        break;
                    case ChangeService::REPORT_REPORTTYPE_ALTER:
                        $correctedChanges[$value->getType()] = $this->om
                            ->getRepository('ProgracqteurWikipedaleBundle:Model\Report\ReportType')
                                ->find($value->getNewValue());
                        break;
                    case ChangeService::REPORT_COMMENT_ADD:
                        $correctedChanges[$value->getType()] = $this->om
                            ->getRepository('ProgracqteurWikipedaleBundle:Model\Comment')
                            ->find($value->getNewValue());
                }
            }
            
            return $correctedChanges;
            
        } else {
            return $changeset->getChanges();
        }
    }
}

