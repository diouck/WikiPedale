<?php

namespace Progracqteur\WikipedaleBundle\Resources\Security;

use Symfony\Component\HttpFoundation\Session;
use Progracqteur\WikipedaleBundle\Entity\Management\User;
use Doctrine\ORM\EntityManager;
use Progracqteur\WikipedaleBundle\Entity\Model\Place;
use Progracqteur\WikipedaleBundle\Resources\Services\GeoService;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;

/**
 * Description of ChangeService
 *
 * @author Julien Fastré <julien arobase fastre point info>
 */
class ChangeService {
    
    const PLACE_CREATION = 100;
    /**
     * @deprecated since 2012-09-27
     */
    const PLACE_DETAILS = 105;
    const PLACE_DESCRIPTION = 1050;
    const PLACE_ADDRESS = 1060;
    const PLACE_GEOM = 1070;
    const PLACE_ADD_COMMENT = 110;
    const PLACE_ADD_VOTE = 120;
    const PLACE_ADD_PHOTO = 130;
    const PLACE_REMOVE_PHOTO = 135;
    const PLACE_STATUS = 141;
    const PLACE_STATUS_BICYCLE = 140;
    const PLACE_STATUS_CITY = 150;
    const PLACE_CREATOR = 160;
    const PLACE_ACCEPTED = 170;
    
    
    /**
     *
     * @var Symfony\Component\Security\Core\SecurityContext 
     */
    private $securityContext;
    
    /**
     *
     * @var Doctrine\ORM\EntityManager 
     */
    private $em;
    
    /**
     *
     * @var Progracqteur\WikipedaleBundle\Resources\Services\GeoService 
     */
    private $geoService;
    
    public function __construct($securityContext, EntityManager $em, GeoService $geoService)
    {
        $this->securityContext = $securityContext;
        $this->em = $em;
        $this->geoService = $geoService;
    }
    
    public function checkChangesAreAllowed(ChangeableInterface $object) 
    {
        /**
         * @var Progracqteur\WikipedaleBundle\Entity\Management\User
         */
        $author = $object->getChangeset()->getAuthor();
            
    
        //s'il s'agit d'une création
        if ($object->getChangeset()->isCreation())
        {
            if ($object instanceof Place) {
                // si l'utilisateur est authentifié, il ne peut créer un objet pour un autre
                if ( $author->isRegistered())
                {
                    if ( $object->getCreator()->equals($author))
                    {
                        return true;
                    } else 
                    {
                        throw ChangeException::mayNotCreateEntityForAnotherUser(10);
                    }
                } else { //si l'utilisateur n'est pas enregistré, alors il ne peut 
                    //pas créer d'objet pour un utilisateur enregistré
                    if ( $object->getCreator()->isRegistered() === true )
                    {
                        throw ChangeException::mayNotCreateEntityForAnotherUser(100);
                    } else {
                        return true;
                    }
                }
            } 
        }
        
        foreach ($object->getChangeset() as $change)
        {
            switch ($change->getType())
            {
                //pour les objets Place
                case self::PLACE_ADD_COMMENT : 
                case self::PLACE_ADD_VOTE :
                    continue; //tout le monde peut ajouter un commentaire ou un vote
                    break;
                case self::PLACE_CREATOR : 
                    throw ChangeException::param('creator');
                    break;
                //This case is deprecated
                case self::PLACE_STATUS_BICYCLE :
                    if ( $this->securityContext->isGranted(User::ROLE_STATUS_BICYCLE)
                            )
                    {
                        continue;
                    } else {
                        throw ChangeException::param('statusBicycle');
                    }
                    break;
                // this case is deprecated
                case self::PLACE_STATUS_CITY :
                    if ( $this->securityContext->isGranted(User::ROLE_STATUS_CITY)
                            )
                    {
                        continue;
                    } else {
                        throw ChangeException::param('statusCity');
                    }
                    break;
                 case self::PLACE_ACCEPTED:
                     if ($this->securityContext->isGranted(User::ROLE_ADMIN))
                     {
                         continue;
                     } else {
                         throw ChangeException::param('accepted');
                     }
                 case self::PLACE_ADDRESS : 
                 case self::PLACE_DESCRIPTION :
                 case self::PLACE_GEOM:
                 case self::PLACE_STATUS:
                     
                     
                     if ($object instanceof Place)
                     {
                        $geomString = $this->geoService->toString($object->getGeom());
                     } else 
                     {
                         throw new \Exception('Unexpected object : expecting Place, receiving '
                                 .get_class($object));
                     }
                     
                     $dql = "select g from ProgracqteurWikipedaleBundle:Management\Group g 
                         JOIN g.city c JOIN g.notation n 
                         WHERE 
                                EXISTS (select h from ProgracqteurWikipedaleBundle:Management\User u
                                           JOIN u.groups h
                                           WHERE u = :author)
                            AND
                                n.id like :notationId 
                            AND
                                COVERS(c.polygon, :geomString) = true
                            ";
                     
                     $q = $this->em->createQuery($dql);
                     $q->setParameters(array(
                         'geomString' => $geomString,
                         'author' => $author,
                         'notationId' => $change->getNewValue()->getType()
                     ));
                     
                     $groups = $q->getResult();
                     
                     if (count($groups) === 0)
                     {
                         throw ChangeException::param('Status '.$change->getnewValue()->getType().' no rights ');
                     }
                     
                     //filter by role
                     $groups_notation = array();
                     foreach ($groups as $group)
                     {
                         if ($group->hasRole('ROLE_NOTATION'))
                         {
                             $groups_notation[] = $group;
                         }
                     }
                     
                     if (count($groups_notation) > 0)
                     {
                         continue;
                     } else 
                     {
                         throw ChangeException::param('Status '.$change->getnewValue()->getType().' no rights ');
                     }
                      
                     /* old implementation
                     
                     $groups = $author->getGroups();
                     
                     //filter by role
                     $groups_notation = array();
                     foreach($groups as $group)
                     {
                         if ($group->hasRole('ROLE_NOTATION'))
                         {
                             $groups_notation[] = $group;
                         }
                     }
                     
                     //filter by notation
                     $groups_matched_notation = array();
                     foreach($groups_notation as $group)
                     {
                         if ($change->getNewValue()->getType() === $group->getNotation()->getId())
                         {
                             $groups_matched_notation[] = $group;
                         }
                     }
                     
                     //if there isn't any group any more, we may stop here:
                     if (count($groups_matched_notation) === 0)
                     {
                         throw ChangeException::param('Status '.$change->getnewValue()->getType());
                     }
                     
                     //filter by geography
                     if ($object instanceof Place)
                     {
                         $q = $this->em->createQuery();
                         
                         $geomString = $this->geoService->toString($object->getGeom());
                         
                         $dql = "SELECT count(g.id) from ProgracqteurWikipedaleBundle:Management\Group g
                                JOIN g.city c
                             WHERE COVERS(c.polygon, :placegeom) = true and (";
                         $q->setParameter('placegeom', $geomString);
                         
                         $i = 0;
                         foreach ($groups_matched_notation as $group)
                         {
                             if ($i > 0) 
                             {
                                 $dql .= ' OR ';
                             }
                             $dql .= "g = :group_$i";
                             $q->setParameter("group_$i", $group);
                         }
                         
                         $dql .= ")";
                         
                         $q->setDQL($dql);
                         
                         
                         $r = $q->getScalarResult();
                         
                         
                         if ($r[0] > 0)
                         {
                             continue;
                         } else {
                             throw ChangeException::param('Status '.$a['type'].' no rights ');
                         }
                     }*/
                     
                     break;
                 case self::PLACE_ADD_PHOTO:
                     //l'ajout de photo est réglé dans le controleur PhotoController
                     continue;
                     break;
                 case self::PLACE_REMOVE_PHOTO:
                     //la modification des photos est réglé dans le controleur PhotoController
                     continue;
                     break;
                 default:
                     throw ChangeException::param('inconnu');
            
            }
            
        }
        
        return true;
    }
    
    
}

