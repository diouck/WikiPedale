<?php

namespace Progracqteur\WikipedaleBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Progracqteur\WikipedaleBundle\Entity\Model\Place;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Progracqteur\WikipedaleBundle\Resources\Geo\BBox;
use Symfony\Component\HttpFoundation\Request;
use Progracqteur\WikipedaleBundle\Resources\Container\NormalizedResponse;
use Progracqteur\WikipedaleBundle\Resources\Container\NormalizedExceptionResponse;
use Progracqteur\WikipedaleBundle\Resources\Normalizer\NormalizerSerializerService;
use Progracqteur\WikipedaleBundle\Resources\Security\ChangeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Progracqteur\WikipedaleBundle\Entity\Management\User;
use Progracqteur\WikipedaleBundle\Resources\Security\Authentication\WsseUserToken;
use Progracqteur\WikipedaleBundle\Form\Model\PlaceType;

/**
 * Description of PlaceController
 *
 * @author Julien Fastré
 */
class PlaceController extends Controller {
    
    
    public function viewAction($_format, $id)
    {
        $em = $this->getDoctrine()->getEntityManager();
        
        $place = $em->getRepository('ProgracqteurWikipedaleBundle:Model\\Place')->find($id);
        
        if ($place === null OR $place->isAccepted() == false)
        {
            throw $this->createNotFoundException("L'endroit n'a pas été trouvé dans la base de donnée");
        }
        
        switch ($_format){
            case 'json':
                $normalizer = $this->get('progracqteurWikipedaleSerializer');
                $rep = new NormalizedResponse($place);
                $ret = $normalizer->serialize($rep, $_format);
                return new Response($ret);
                
                /* code mort
                $jsonencoder = new JsonEncoder();
                $serializer = new Serializer(array(new CustomNormalizer()) , array('json' => $jsonencoder));
		$rep = array('results' => array($place));
                $ret = $serializer->serialize($rep, $_format);
                return new Response($ret);*/
                break;
            
            case 'html' :
                return $this->render('ProgracqteurWikipedaleBundle:Place:view.html.twig', 
                        array(
                            'place' => $place
                        ));
                break;
        }
    }
    
    public function listByBBoxAction($_format, Request $request){
        
        throw new \Exception("cette fonction n'est plus fonctionnelle tant que la fonction covers n'a pas été adaptée");
        
        $em = $this->getDoctrine()->getEntityManager();
        
        $BboxStr = $request->get('bbox', null);
        if ($BboxStr === null) {
            throw new \Exception('Fournissez un bbox');
        }
        
        $BboxArr = explode(',', $BboxStr, 4);
        
        foreach($BboxArr as $value){
            if (!is_numeric($value))
            {
                throw new \Exception("Le Bbox n'est pas valide : $BboxStr");
            }
        }
        
        
        
        
        $bbox = BBox::fromCoord($BboxArr[0], $BboxArr[1], $BboxArr[2], $BboxArr[3]);
        
        $p = $em->createQuery('SELECT p from ProgracqteurWikipedaleBundle:Model\\Place p 
                  where covers(:bbox, p.geom) = true and p.accepted = true')
                ->setParameter('bbox', $bbox->toWKT());
        
        $r = $p->getResult();
        
        switch($_format) {
            case 'json':
                $normalizer = $this->get('progracqteurWikipedaleSerializer');
                $rep = new NormalizedResponse($r);
                $ret = $normalizer->serialize($rep, $_format);
                return new Response($ret);
                break;
            case 'html':
                return new Response('Pas encore implémenté');
                
        }
        
        
    }
    
    public function listByCityAction($_format, Request $request)
    {
        
        
        $em = $this->getDoctrine()->getEntityManager();
        
        $citySlug = $request->get('city', null);
        $citySlug = $this->get('progracqteur.wikipedale.slug')->slug($citySlug);
        
        if ($citySlug === null)
        {
            throw new \Exception('Renseigner une ville dans une variable \'city\' ');
        }
        
        $city = $em->getRepository('ProgracqteurWikipedaleBundle:Management\\Zone')
                ->findOneBy(array('slug' => $citySlug));
        
        if ($city === null)
        {
            throw $this->createNotFoundException("Aucune ville correspondant à $citySlug n'a pu être trouvée");
        }

        
        $p = $em->createQuery('SELECT p 
            from ProgracqteurWikipedaleBundle:Model\\Place p 
                
            where covers(:polygon, p.geom) = true and p.accepted = true')
                ->setParameter('polygon', $city->getPolygon());
        
        $r = $p->getResult();
        
        switch($_format) {
            case 'json':
                $normalizer = $this->get('progracqteurWikipedaleSerializer');
                $rep = new NormalizedResponse($r);
                
                $ret = $normalizer->serialize($rep, $_format);
                
                return new Response($ret);
                break;
            case 'html':
                return new Response('Pas encore implémenté');
                
        }
        
        
    }
    
    public function changeAction($_format, Request $request)
    {
        
        if ($request->getMethod() != 'POST')
        {
            throw new \Exception("Only post method accepted");
        }
        
        //check tokens
        $token = $request->attributes->get('token', null);
        if ($token === null)
        {
            //if WSSE authentication, does not need token
            if(! 
                    (
                        $this->get('security.context')->getToken() instanceof WsseUserToken
                        AND 
                        $this->get('security.context')->getToken()->isFullyAuthenticated()
                    )
              )
            {
                /*TODO: when the token will be enabled into javascript, if there
                 * is no token, the script must reject request without tokens
                 */
                $this->get('logger')->warn('Wikipedale:PlaceController:ChangeAction change place without token');
                
                //TODO: remove debug code below :
                if ($this->get('security.context')->getToken() instanceof WsseUserToken)
                {
                    if (!$this->get('security.context')->getToken()->isFullyAuthenticated())
                    {
                        $this->get('logger')->debug('Wikipedale:PlaceController:ChangeAction connected with WSSE but not fully');
                    }
                }
                
            }
                
        } else {
            if (false === $this->get('progracqteur.wikipedale.token_provider')->isCsrfTokenValid($token))
            {
                $this->get('logger')->warn('Wikipedale:PlaceController:ChangeAction use of invalid token');
                $response = new Response('invalid token provided');
                $response->setStatusCode(400);
                return $response;
            }
        }
        
        $serializedJson = $request->get('entity', null);
        
        if ($serializedJson === null)
        {
            throw new \Exception("Aucune entitée envoyée");
        }
        
        $serializer = $this->get('progracqteurWikipedaleSerializer');
        
        $place = $serializer->deserialize($serializedJson, NormalizerSerializerService::PLACE_TYPE, $_format);
        
        //SECURITE: refuse la modification d'une place par un utilisateur anonyme
        if (
                ($this->get('security.context')->getToken()->getUser() instanceof User) == false 
                && 
                $place->getChangeset()->isCreation() == false
            )
        {
            $r = new Response("Il faut être enregistré pour modifier une place");
            $r->setStatusCode(403);
            return $r;
        }
        
        
        //ajoute l'utilisateur courant comme créateur si connecté
        if ($place->getId() == null && $this->get('security.context')->getToken()->getUser() instanceof User)
        {
            $u = $this->get('security.context')->getToken()->getUser();
            $place->setCreator($u);
        }
        
        //ajoute l'adresse IP du modificateur si utilisateur anonyme et création
        
        //ajoute l'utilisateur courant au changeset
        if ($place->getChangeset()->isCreation()) // si création
        {
            
            if ($this->get('security.context')->getToken()->getUser() instanceof User) //si utilisateur connecté
            {
                $place->getChangeset()->setAuthor($this->get('security.context')->getToken()->getUser());
            } else { 
                $user = $place->getCreator();
                
                $place->getChangeset()->setAuthor($user);
            }
        } else { //si modification d'une place
            //les vérifications de sécurité ayant eu lieu, il suffit d'ajouter l'utilisateur courant
            $place->getChangeset()->setAuthor($this->get('security.context')->getToken()->getUser());
        }
        
        
        
        /**
         * @var Progracqteur\WikipedaleBundle\Resources\Security\ChangeService 
         */
        $securityController = $this->get('progracqteurWikipedaleSecurityControl');
        
        try {
        //TODO implémenter une réponse avec code d'erreur en JSON
        $return = $securityController->checkChangesAreAllowed($place);
        } catch (ChangeException $exc) {
            $r = new Response($exc->getMessage());
            $r->setStatusCode(403);
            return $r;
        }
        
        if ($return == false)
        {
            $r = new Response("Vous n'avez pas de droits suffisant pour effectuer cette modification");
            $r->setStatusCode(403);
            return $r;
        }
        
        $validator = $this->get('validator');
        
        if ($place->getId() === null)
            $arrayValidation = array('Default', 'creation');
        else
            $arrayValidation = array('Default');
        
        $errors = $validator->validate($place, $arrayValidation);
        
        if ($errors->count() > 0)
        {
            $stringErrors = ''; $k = 0;
            foreach ($errors as $error)
            {
                $stringErrors .= $k.'. '.$error->getMessage();
            }
            
            throw new HttpException(403, 'Erreurs de validation : '.$stringErrors);
            
        }
        
        $em = $this->getDoctrine()->getEntityManager();
        $em->persist($place);
        $em->flush();
               
        return $this->redirect(
                $this->generateUrl('wikipedale_place_view', 
                        array('id' => $place->getId(), 
                            '_format' => 'json',
                            'return' => $return,
                            'addUserInfo' => $request->get('addUserInfo', false))
                        )
                );
    }
    
    public function placeManagerFormAction($id, Request $request)
    {
        if (!$this->get('security.context')->isGranted(User::ROLE_ADMIN))
        {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException(); 
        }
        
        $em = $this->getDoctrine()->getEntityManager();
        $place = $em->createQuery('SELECT p from ProgracqteurWikipedaleBundle:Model\Place p where p.id = :id')
                ->setParameter('id', $id)
                ->getSingleResult();
        
        if ($place === null)
        {
            throw $this->createNotFoundException(
                    $this->get('translator')->trans('errors.404.place.not_found')
                    );
        }
        
        $form = $this->createForm(new PlaceType(), $place);
        
        
        return $this->render('ProgracqteurWikipedaleBundle:Place:manager_view.html.twig',
                array(
                    'form' => $form->createView(), 
                    'place'=> $place
                    )
                );
        
        
    }
    
}

