<?php

namespace Progracqteur\WikipedaleBundle\Entity\Model;

use Doctrine\ORM\Mapping as ORM;
use Progracqteur\WikipedaleBundle\Resources\Container\Hash;
use Progracqteur\WikipedaleBundle\Entity\Model\Comment;
use Progracqteur\WikipedaleBundle\Resources\Geo\Point;
use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Progracqteur\WikipedaleBundle\Resources\Container\Address;
use Progracqteur\WikipedaleBundle\Entity\Management\UnregisteredUser;
use Progracqteur\WikipedaleBundle\Resources\Security\ChangeableInterface;
use Doctrine\Common\NotifyPropertyChanged;
use Doctrine\Common\PropertyChangedListener;
use Progracqteur\WikipedaleBundle\Resources\Security\ChangeService;
use Progracqteur\WikipedaleBundle\Entity\Model\Report\ReportStatus;
use Symfony\Component\Validator\ExecutionContext;
use Progracqteur\WikipedaleBundle\Entity\Model\Category;
use Progracqteur\WikipedaleBundle\Entity\Management\Group;

/**
 * Progracqteur\WikipedaleBundle\Entity\Model\Report
 */
class Report implements ChangeableInterface, NotifyPropertyChanged
{
    /**
     * @var integer $id
     */
    private $id;

    /**
     * @var Progracqteur\WikipedaleBundle\Resources\Container\Address $address
     */
    private $address;

    /**
     * @var Progracqteur\WikipedaleBundle\Resources\Geo\Point $geom
     */
    private $geom;



    /**
     * @var datetime $createDate
     */
    private $createDate;

    /**
     * @var int $nbVote
     */
    private $nbVote = 0;

    /**
     * @var int $nbComm
     */
    private $nbComm = 0;

    /**
     * @var Progracqteur\WikipedaleBundle\Resources\Container\Hash $infos
     */
    private $infos;
    
    /**
     *
     * @var boolean
     */
    private $accepted = true;

    /**
     * @var Progracqteur\WikipedaleBundle\Entity\Management\User
     */
    private $creator;
    
    private $creatorUnregisteredProxy;
    
    /**
     * @var Doctrine\Common\Collections\ArrayCollection
     */
    private $photos;
    /**
     * @var string $description
     */
    private $description = '';
    
    private $nbPhoto = 0;
    
    private $lastUpdate;
    
    /**
     *
     * @var Progracqteur\WikipedaleBundle\Entity\Model\Report\ReportTracking 
     */
    private $changeset = null;
    
    /**
     * pour la persistance
     * @var \Doctrine\Common\Collections\ArrayCollection 
     */
    private $changesets;
    
    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     */
    private $category;
    
    /**
     *
     * @var \Progracqteur\WikipedaleBundle\Entity\Management\Group
     */
    private $manager;
    
    /**
     *
     * @var \Progracqteur\WikipedaleBundle\Entity\Report\ReportType 
     */
    private $type;
    
    /**
     *
     * @var string 
     */
    private $term ;
    
    /**
     * comment for moderators of the system
     * 
     * @var string
     */
    private $moderatorComment = '';
    
    /**
     *
     * @var Doctrine\Common\Collections\ArrayCollection 
     */
    private $comments;
            
    
    private $_listeners = array();

    public function __construct()
    {
        $this->photos = new \Doctrine\Common\Collections\ArrayCollection();
        $d = new \DateTime();
        $this->setLastUpdate($d);
        $this->setCreateDate($d);
        $this->infos = new Hash();
        $this->address = new Address();
        $this->changesets = new \Doctrine\Common\Collections\ArrayCollection();
        $this->getChangeset()->addChange(ChangeService::REPORT_CREATION, null);
        $this->category = new \Doctrine\Common\Collections\ArrayCollection();
        $this->comments = new \Doctrine\Common\Collections\ArrayCollection();
        
        //initialize the placeStatuses (tel quel dans la db)
        $this->infos->placeStatuses = new Hash();
    }


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }
    
    private function change($propName, $oldValue, $newValue) {
/* FIXME: il semble que les chagnements de la description (et de l'adresse ?)
 * ne soient 
 * pas pris en compte lorsque le trackingPolicy est sur Notify
 */
        if ($this->_listeners) {
            foreach ($this->_listeners as $listener) {
                $listener->propertyChanged($this, $propName, $oldValue, $newValue);
            }
        }
        
        
        $oldUpdate = $this->getLastUpdate();
        $this->setLastUpdateNow();
        
        //change date update
        if ($this->_listeners) {
            foreach ($this->_listeners as $listener) {
                $listener->propertyChanged($this, 'lastUpdate', $oldUpdate, $this->getLastUpdate());
            }
            
        }  
    }

    /**
     * Set address
     *
     * @param Progracqteur\WikipedaleBundle\Resources\Container\Address $adress
     */
    public function setAddress(Address $address)
    {
        if (! $address->equals($this->address))
        {
            $this->change('address', $this->address, $address);
            $this->address = $address;
            $this->getChangeset()->addChange(ChangeService::REPORT_ADDRESS, $address);
        }
        
    }

    /**
     * Get adress
     *
     * @return Progracqteur\WikipedaleBundle\Resources\Container\Hash 
     */
    public function getAddress()
    {
        if ($this->address != null) 
        {
            return $this->address;
        } else 
        {
            $addr =  new Address();
            $addr->setRoad('Adresse inconnue');
            return $addr;
        }
    }

    /**
     * Set geom
     *
     * @param Progracqteur\WikipedaleBundle\Resources\Geo\Point $geom
     */
    public function setGeom(Point $geom)
    {
        if ($this->getGeom() === null)
        {
            $this->geom = $geom;
            return;
        }
        
        if (
                $this->getGeom()->getLat() != $geom->getLat()
                && $this->getGeom()->getLon() != $geom->getLon()) 
        {
            $this->change('geom', $this->geom, $geom);
            $this->geom = $geom;
            $this->getChangeset()->addChange(ChangeService::REPORT_GEOM, $geom );
        }
    }

    /**
     * Get geom
     *
     * @return Progracqteur\WikipedaleBundle\Resources\Geo\Point 
     */
    public function getGeom()
    {
        return $this->geom;
    }
    
    
    /**
     * 
     * @param string $term
     */
    public function setTerm($term) {
        if ($term !== $this->term) {
            $this->term = $term;
            $this->change('term', $this->term, $term);
            
            $this->getChangeset()->addChange(ChangeService::REPORT_TERM, $term);
            
        }   
    }
    
    public function getTerm() {
        return $this->term;
    }

    /**
     * Set createDate
     *
     * @param datetime $createDate
     */
    private function setCreateDate($createDate)
    {
        $this->change('createDate', $this->createDate, $createDate);
        $this->createDate = $createDate;
    }

    /**
     * Get createDate
     *
     * @return datetime 
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }


    /**
     * Get nbVote
     *
     * @return int 
     */
    public function getNbVote()
    {
        return $this->nbVote;
    }



    /**
     * Get nbComm
     *
     * @return int 
     * @deprecated
     */
    public function getNbComm()
    {
        return $this->nbComm;
    }

    /**
     * Set infos
     *
     * @param Progracqteur\WikipedaleBundle\Resources\Container\Hash $infos
     * @deprecated
     */
    private function setInfos(Hash $infos)
    {
        $this->infos = $infos;
        $this->setLastUpdateNow();
    }

    /**
     * Get infos
     *
     * @return Progracqteur\WikipedaleBundle\Resources\Container\Hash 
     */
    public function getInfos()
    {
        return $this->infos;
    }

    /**
     * Set creator
     *
     * @param Progracqteur\WikipedaleBundle\Entity\Management\User $creator
     */
    public function setCreator(\Progracqteur\WikipedaleBundle\Entity\Management\User $creator)
    {
        if ($this->_getCreator() === null OR  !($creator->equals($this->_getCreator())))
        {
            if ($creator instanceof UnregisteredUser)
            {
                if ($this->creator !== null)
                {
                    $this->change('creator', $this->creator, null);
                    $this->creator = null;
                }


                $old = clone($this->getInfos());
                $this->infos->creator = $creator->toHash();
                $this->creatorUnregisteredProxy = $creator;

                $this->change('infos', $old, $this->getInfos());
                $this->getChangeset()->addChange(ChangeService::REPORT_CREATOR, $creator);

            } else {
                $this->change('creator', $this->creator, $creator);
                $this->creator = $creator;
                $this->getChangeset()->addChange(ChangeService::REPORT_CREATOR, $creator);
                //TODO : si un unregistreredCreator existe, il faut l'enlever
            }
        }
    }
    
    /**
     * 
     * this is a proxy method to set a confirmed creator to the report
     * and set the report as accepted.
     * 
     * a reporttracking instance is also set with code ChangeService::REPORT_CREATOR_CONFIRMATIN
     * 
     * 
     * @param \Progracqteur\WikipedaleBundle\Entity\Model\Unregistereduser $creator
     */
    public function setConfirmedCreator(Unregistereduser $creator)
    {
        $this->forceSetCreator($creator);
        $this->getChangeset()->addChange(ChangeService::REPORT_CREATOR_CONFIRMATION, 1);
        $this->getChangeset()->setAuthor($creator);
        $this->getChangeset()->setCreation(true);
        $this->setAccepted(true);
    }
    
    private function forceSetCreator(UnregisteredUser $creator)
    {
        if ($creator instanceof UnregisteredUser)
            {
                if ($this->creator !== null)
                {
                    $this->change('creator', $this->creator, null);
                    $this->creator = null;
                }


                $old = clone($this->getInfos());
                $this->infos->creator = $creator->toHash();
                $this->creatorUnregisteredProxy = $creator;

                $this->change('infos', $old, $this->getInfos());
                //$this->getChangeset()->addChange(ChangeService::REPORT_CREATOR, $creator);
                //normalement le creator ne doit pas être modifié

            } else {
                $this->change('creator', $this->creator, $creator);
                $this->creator = $creator;
                $this->getChangeset()->addChange(ChangeService::REPORT_CREATOR, $creator);
                //TODO : si un unregistreredCreator existe, il faut l'enlever
            }
    }
    
    /**
     * Get creator
     *
     * @return Progracqteur\WikipedaleBundle\Entity\Management\User 
     */
    public function getCreator()
    {
        return $this->_getCreator();
        
        //TODO : modifier les lignes suivantes, devenues inutiles
        
        if ($creator === null)
        {
            throw new \Exception('Aucun créateur enregistré');
        } else {
            return $creator;
        }
        
        
    }
    
    /**
     * variable used as proxy by the getStatuses function
     * 
     * @return \Doctrine\Common\Collections\ArrayCollection()|null
     */
    private $proxyStatuses = null;
    
    private function initializeProxyStatuses()
    {
        if ($this->proxyStatuses === null)
        {
            $this->proxyStatuses = new \Doctrine\Common\Collections\ArrayCollection();
            
            foreach ($this->infos->placeStatuses->toArray() as $type => $value)
            {
                $status = new ReportStatus();
                $status->setType($type)->setValue($value);
                $this->proxyStatuses->add($status);
            }
        }
        
        /*if (! $this->infos->has('placeStatuses'))
        {
            $this->infos->placeStatuses = new Hash();
        }*/
    }
    
    /**
     * return the statuses
     * 
     * @return \Doctrine\Common\Collections\ArrayCollection()
     */
    public function getStatuses()
    {
        $this->initializeProxyStatuses();
        
        return $this->proxyStatuses;
    }
        
    /**
     * Add a new status to the class, and retrieve old status 
     * with same type.
     * 
     * @param \Progracqteur\WikipedaleBundle\Entity\Model\Report\ReportStatus $status
     * @return \Progracqteur\WikipedaleBundle\Entity\Model\Report
     */
    public function addStatus(ReportStatus $status)
    {
        $this->initializeProxyStatuses(); 
        
        $old = clone($this->infos);
 
        foreach ($this->getStatuses() as $key => $oldStatus)
        {
            if ($status->getType() ===  $oldStatus->getType())
            {
                if ($status->getValue() !== $oldStatus->getValue())
                {
                    $this->proxyStatuses->remove($key);
                    $this->infos->placeStatuses->remove($status->getType());
                } else {
                    return $this;
                }
                
                break;
            }
        }
        
        $this->proxyStatuses->add($status);
        $this->infos->placeStatuses->__set($status->getType(), $status->getValue());
        
        $this->change('infos', $old, $this->infos);
        
        $this->getChangeset()->addChange(ChangeService::REPORT_STATUS, $status);
        
        $this->proxyCountStatusChanges++;

        return $this;
    }
    
    /**
     * Remove completely the statuses equals of the given status
     * 
     * @param \Progracqteur\WikipedaleBundle\Entity\Model\Report\ReportStatus $status
     * @return \Progracqteur\WikipedaleBundle\Entity\Model\Report
     */
    public function removeStatus(ReportStatus $status)
    {
        $this->initializeProxyStatuses();
        
        foreach ($this->getStatuses() as $key => $inStatus)
        {
            if ($status->equals($inStatus))
            {
                $old = clone($this->infos);
                $this->proxyStatuses->remove($key);
                $this->infos->placeStatuses->remove($status->getType());
                $this->change('infos', $old, $this->infos);
                $this->proxyCountStatusChanges++;
                break;
            }
        }
        return $this;
    }
    
    /**
     * Currently, we must update only ONE change at a time. 
     * 
     * This is necessary for the changes to be kept into changesets.
     * 
     * @var int 
     */
    private $proxyCountStatusChanges = 0;
    
    //TODO: allow changeset to record more than one change of status
    
    //TODO: reset proxyCountStatusChange after update
    
    /**
     * check if value of statuses are valid.
     * 
     * the verifiation of validity of statuses's types are delegated 
     * to the controller.
     * 
     * function used by validation service.
     * 
     * @return boolean
     */
    public function isStatusesValid(ExecutionContext $context)
    {
        echo "Is StatusesValid\n";
        $this->initializeProxyStatuses();

        foreach($this->getStatuses() as $status) {
            echo $status->getValue() . "\n";
            if (!($status->getValue() >= -1 && $status->getValue() <= 3)) {
                $propertyPath = $context->getPropertyPath() . '.status';
                $context->setPropertyPath($propertyPath);
                $context->addViolation('place.validation.message.status.valueNotCorrect', array(), null);
            }
        }
        
        echo "Is StatusesValid : True\n";
        return true;
    }
    
    /**
     * Currently, we must update only ONE change at a time. 
     * 
     * This is necessary for the changes to be kept into changesets.
     * 
     * This function check if only one change has been made and is used
     * for validation
     * @return boolean
     */
    public function hasOnlyOneChange(ExecutionContext $context)
    {
        echo "hasOnlyOneChange \n";
        if ($this->proxyCountStatusChanges <= 1) {
            return true;
        } else {
            $context->addViolationAtSubPath('status', 'place.validation.message.onlyOneStatusAtATime', array(), null);
        }
    }    
    
    /**
     * return the common way to name a report
     * (currently the name of the street)
     * 
     * @return string
     */
    public function getLabel()
    {
        $l =  $this->getAddress()->getRoad();
        
        if (strlen($l) < 2) {
            $l = "Sans label";
        }
        
        return $l;
    }
    
    /**
     * transform the report into a string displayable on UI
     * @return string
     */
    public function __toString() {
        return $this->getLabel();
    }
    
    /**
     * REnvoie le créateur
     * Méthode privée utilisée pour éviter l'exception de la méthode
     * publique getCreator. Utilisée dans setCreator().
     * 
     * @return Progracqteur\WikipedaleBundle\Entity\Management\User 
     * @deprecated
     */
    private function _getCreator()
    {
        if (!is_null($this->creator)) {
            return $this->creator;
        } elseif (!is_null($this->creatorUnregisteredProxy)) {
            return $this->creatorUnregisteredProxy;
        } elseif ($this->infos->has('creator')) {
            $u = UnregisteredUser::fromHash($this->infos->creator);
            $this->creatorUnregisteredProxy = $u;
            return $u;
        } else {
            return null;
        }
    }

    /**
     * Get photos
     *
     * @return Doctrine\Common\Collections\Collection 
     */
    public function getPhotos()
    {
        return $this->photos;
    }
    
    public function getNbPhoto()
    {
        return $this->nbPhoto;
    }
    
    public function increaseComment()
    {
        $this->nbComm++;
        $this->change('nbComm', ($this->nbComm -1 ), $this->nbComm);
        $this->getChangeset()->addChange(ChangeService::REPORT_ADD_COMMENT, 1);
    }
    
    public function increaseVote()
    {
        $this->nbVote++;
        $this->change('nbVote', ($this->nbVote - 1), $this->nbVote);
        $this->getChangeset()->addChange(ChangeService::REPORT_ADD_VOTE, 1);
    }
    
    private function increasePhoto()
    {
        $this->nbPhoto++;
        $this->change('nbPhoto', ($this->nbPhoto -1), $this->nbPhoto);
    }
    
    private function decreasePhoto()
    {
        $this->nbPhoto--;
        $this->change('nbPhoto', ($this->nbPhoto +1), $this->nbPhoto);
        //TODO: implémenter tracking policy
                
    }

 

    /**
     * Add photos
     *
     * @param Progracqteur\WikipedaleBundle\Entity\Model\Photos $photos
     */
    public function addPhotos(\Progracqteur\WikipedaleBundle\Entity\Model\Photo $photos)
    {
        //TODO: implémenter le tracking policy pour les photos
        $this->photos[] = $photos;
        $this->increasePhoto();
        $this->getChangeset()->addChange(ChangeService::REPORT_ADD_PHOTO, $photos);
    }
    
    public function removePhotos(\Progracqteur\WikipedaleBundle\Entity\Model\Photo $photo)
    {
        //TODO: compléter la fonction removePhoto
        $this->decreasePhoto();
        $this->getChangeset()->addChange(ChangeService::REPORT_REMOVE_PHOTO, $photo->getFileName());
    }
    


    /**
     * Set description
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $description = trim($description);
        if ($this->description !== $description) {
            $this->change('description', $this->description, $description);
            $this->description = $description;
            $this->getChangeset()->addChange(ChangeService::REPORT_DESCRIPTION, $description);
        }
    }

    /**
     * Get description
     *
     * @return string 
     */
    public function getDescription()
    {
        return $this->description;
    }
    
    public function setModeratorComment($comment) 
    {
        if ($this->moderatorComment !== $comment) {
            $this->change('moderatorComment', $this->moderatorComment, $comment);
            $this->moderatorComment = $comment;
            $this->getChangeset()->addChange(
                ChangeService::REPORT_MODERATOR_COMMENT_ALTER, 
                $comment);
        }
        
        return $this;
    }
    
    public function getModeratorComment()
    {
        return $this->moderatorComment;
    }
    
    public function setAccepted($accepted)
    {
        if ($this->accepted != $accepted) {
            $this->change('accepted', $this->accepted, $accepted);
            $this->accepted = $accepted;
            $this->getChangeset()->addChange(
                ChangeService::REPORT_ACCEPTED, 
                $accepted);
        }
    }
    
    public function isAccepted()
    {
        return $this->accepted;
    }
    
    private function setLastUpdate(\DateTime $d)
    {
        $this->lastUpdate = $d;
    }
    
    private $proxyLastUpdate = false;
    
    private function setLastUpdateNow()
    {
        if ($this->proxyLastUpdate === false) {
            $this->lastUpdate = new \DateTime();
            $this->proxyLastUpdate = true;
        }
        
        //be careful: do not add method $this->change you risk recursive operation
                
    }
    
    public function getLastUpdate()
    {
        return $this->lastUpdate;
    }
    
    public function setManager(Group $manager = null)
    {
        
        if ($manager === null) {
            return $this->removeManager();
        }
        
        if ($this->getManager() === null) {
            if ($this->getChangeset()->isCreation()) {
                $this->manager = $manager;
                $this->getChangeset()
                        ->addChange(ChangeService::REPORT_MANAGER_ADD, $manager);
            } else {
                $this->change('manager', $this->manager, $manager);
                $this->getChangeset()->addChange(ChangeService::REPORT_MANAGER_ALTER, $manager);
            } 
        } elseif ($this->getManager()->getId() !== $manager->getId()) {
            $this->change('manager', $this->manager, $manager);
            $this->getChangeset()->addChange(ChangeService::REPORT_MANAGER_ALTER, $manager);
        }
        
        return $this;
    }
    
    public function removeManager()
    {
        if ($this->getManager() !== null) {
            $oldManager = -1;
        } else {
            $oldManager = $this->getManager();
        }
            
        $this->change('manager', $this->manager, null);
        $this->getChangeset()
                ->addChange(ChangeService::REPORT_MANAGER_REMOVE, $oldManager);
        
        
        return $this;
    }
    
    /**
     * 
     * 
     * @return \Progracqteur\WikipedaleBundle\Entity\Management\Group
     */
    public function getManager()
    {
        return $this->manager;
    }
    
    /**
     * 
     * @param \Progracqteur\WikipedaleBundle\Entity\Model\Report\ReportType $type
     * @return \Progracqteur\WikipedaleBundle\Entity\Model\Report
     */
    public function setType(Report\ReportType $type)
    {
        if ($this->getType() === null) {
            $this->type = $type;
            $this->getChangeset()->addChange(ChangeService::REPORT_REPORTTYPE_ALTER, $type);
            
        } elseif ($this->getType()->getId() !== $type->getId()) {
            $this->change('type', $this->type, $type);
            $this->getChangeset()->addChange(ChangeService::REPORT_REPORTTYPE_ALTER, $type);
        }
        
        
        return $this;
    }
    
    /**
     * 
     * @return \Progracqteur\WikipedaleBundle\Entity\Model\Report\ReportType
     */
    public function getType()
    {
        return $this->type;
    }
    
    public function registerComment(Comment $comment)
    {
        //$this->getChangeset()->addChange(ChangeService::REPORT_COMMENT_ADD, $comment);
        
        $type = $comment->getType();
        $oldInfos = clone($this->getInfos());
        
        //create the entry in the hash, update it if necessary
        if (! $this->infos->has('nbComments')) {
            $this->infos->nbComments = new Hash();
        }
        
        if (! $this->infos->nbComments->has($type)) {
            $this->infos->nbComments->{$type} = 1;
        } else {
            $this->infos->nbComments->{$type} ++;
        }
        
        $this->comments->add($comment);
        
        $this->change('infos', $oldInfos, $this->infos);
        
        $this->getChangeset()->addChange(
                ChangeService::REPORT_COMMENT_MODERATOR_MANAGER_ADD, $comment
                );
    }
    
    
    public function getNbComments($type)
    {
        //create the entry in the hash, update it if necessary
        if (! $this->infos->has('nbComments')) {
            return 0;
        }
        
        if (! $this->infos->nbComments->has($type)) {
            return 0;
        } else {
            return (int) $this->infos->nbComments->{$type};
        }
    }
    
    public function setChecked()
    {
        $this->getChangeset()->addChange(ChangeService::REPORT_CHECK, true);
    }
    /**
     * return the changeset made since the entity was created or 
     * retrieved from the database.
     * 
     * @return \Progracqteur\WikipedaleBundle\Entity\Model\Report\ReportTracking
     */
    public function getChangeset() {
        
        if ($this->changeset === null) {
            $this->changeset = new Report\ReportTracking($this);
            //$this->changesets->add($this->changeset);
        }
        
        return $this->changeset;
    }
    
    private function destroyCurrentChangeset() {
        $changeset = $this->getChangeset();
        
        $hashId = spl_object_hash($changeset);
        
        foreach ($this->changesets as $key => $value) {
            if ($hashId === spl_object_hash($value)) {
                $this->changesets->remove($key);
            }
        }
        
        $this->changeset = null;
        
        unset($changeset);
        
    }

    public function addPropertyChangedListener(PropertyChangedListener $listener) {
        $this->_listeners[] = $listener;
    }
   
    
    /**
     *
     * @var \Doctrine\Common\Collections\ArrayCollection 
     */
    private $proxyAddCategory = null;

    /**
     * Add category
     *
     * @param Progracqteur\WikipedaleBundle\Entity\Model\Category $category
     * @return Progracqteur\WikipedaleBundle\Entity\Model\Report
     */
    public function addCategory(Category $category)
    {
        $this->category[] = $category;
        if ($this->proxyAddCategory === null) {
            $this->proxyAddCategory = new \Doctrine\Common\Collections\ArrayCollection();
        }
        
        $this->proxyAddCategory->add($category);
        
        $this->getChangeset()->addChange(ChangeService::REPORT_ADD_CATEGORY, $this->proxyAddCategory);
        
        return $this;
    }

    /**
     * Get category
     *
     * @return Doctrine\Common\Collections\Collection 
     */
    public function getCategory()
    {
        return $this->category;
    }
    
    
    private $proxyRemoveCagory = null;
    
    /**
     * 
     * @param \Progracqteur\WikipedaleBundle\Entity\Model\Category $category
     * @return \Progracqteur\WikipedaleBundle\Entity\Model\Report
     */
    public function removeCategory(Category $category)
    {
        foreach ($this->category as $key => $categoryRecorded)
        {
            if ($categoryRecorded->getId() === $category->getId()) {
                $this->category->remove($key);
                if ($this->proxyRemoveCagory === null) {
                    $this->proxyRemoveCagory = new \Doctrine\Common\Collections\ArrayCollection();
                }
                $this->proxyRemoveCagory->add($category);
                
                $this->getChangeset()
                        ->addChange(ChangeService::REPORT_REMOVE_CATEGORY, 
                                $this->proxyRemoveCagory);
            }
        }
        
        return $this;
    }
    
    /**
     * 
     * @param \Progracqteur\WikipedaleBundle\Entity\Model\Category\Progracqteur\WikipedaleBundle\Entity\Model\Category $category
     * @return boolean
     */
    public function hasCategory(Category $category)
    {
        foreach($this->category as $cat) {
            if ($cat->getId() == $category->getId()) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * check if the categories added to the report are valid. 
     * 
     * Until now: no categories with children are accepted !
     * 
     * @param \Symfony\Component\Validator\ExecutionContext $context
     */
    public function isCategoriesValid(ExecutionContext $context)
    {
        echo "isCategoriesValid \n";
        foreach($this->getCategory() as $cat) {
            if ($cat->hasChildren()) {
                $context->addViolationAtSubPath('category', 'validation.report.category.have_children', array(), null);
                return;
            }
        }
    }
    
    public function isManagerValid(ExecutionContext $context)
    {
        echo "isManagerValid \n";
        if ($this->getManager() !== null 
                && $this->getManager()->getType() !== Group::TYPE_MANAGER ) {
            echo $this->getManager()->getType(); echo "\n";
            echo Group::TYPE_MANAGER; echo "\n";
            $context->addViolationAtSubPath('manager', 'validation.report.manager.group_is_not_type_manager', 
                    array(), $this->getManager());
        }
        echo "fin\n ";
    }
    
    public function checkEmptyReportTracking() {
        $reportTracking = $this->getChangeset();
        
        $changes = $reportTracking->getChanges();
        
        
        if (count($changes) === 0) {
            $this->destroyCurrentChangeset();
        }
    }
    
    public function registerCurrentChangeset() {
        $reportTracking = $this->getChangeset();
        
        $changes = $reportTracking->getChanges();
        
        
        if (count($changes) > 0) {
            $this->changesets->add($reportTracking);
        }
    }
}