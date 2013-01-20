<?php

namespace Progracqteur\WikipedaleBundle\Form\Model;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Doctrine\ORM\Query\Expr;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder
            ->add('label')
            ->add('parent', 'entity', array(
                'class' => 'ProgracqteurWikipedaleBundle:Model\Category',
                'query_builder' => function(\Doctrine\ORM\EntityRepository $er)
                {
                    $qb = $er->createQueryBuilder('c');
                    return  $qb->add('where', $qb->expr()->isNull('c.parent') )
                                ->orderBy('c.label', 'ASC')
                            ;
                },
                'empty_value' => 'admin.category.form.parent.empty_value',
                'required' => false
            ))
        ;
    }

    public function getName()
    {
        return 'progracqteur_wikipedalebundle_model_categorytype';
    }
}