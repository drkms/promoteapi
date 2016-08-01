<?php

namespace bigz\halapi\Factory;

use bigz\halapi\Annotation\Embeddable;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Request\ParamFetcherInterface;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;

class RelationFactory
{
    private $router;

    private $annotationReader;

    private $entityManager;

    private $paramFetcher;

    private $serializer;

    public function __construct(
        RouterInterface $router,
        Reader $annotationReader,
        EntityManagerInterface $entityManager,
        ParamFetcherInterface $paramFetcher
    ) {
        $this->router = $router;
        $this->annotationReader = $annotationReader;
        $this->entityManager = $entityManager;
        $this->paramFetcher = $paramFetcher;
        $this->serializer = SerializerBuilder::create()->build();
    }

    public function getLinks($resource)
    {
        $reflectionClass = new \ReflectionClass($resource);
        $links = $this->getSelfLink($resource, $reflectionClass);

        foreach ($reflectionClass->getProperties() as $property) {
            if ($this->isEmbbedable($property) && $property->getName()) {
                $propertyName = $property->getName();
                $relationContent = $resource->{'get'.ucfirst($propertyName)}();
                $links[$propertyName] = $this->getRelationLinks($property, $relationContent);

                if (!$links[$propertyName]) {
                    unset($links[$propertyName]);
                }
            }
        }

        return $links;
    }

    public function getEmbedded($resource)
    {
        $reflectionClass = new \ReflectionClass($resource);
        $embedded = [];
        $requestedEmbedded = $this->addEmbedParams($this->paramFetcher);
        
        foreach ($reflectionClass->getProperties() as $property) {
            $propertyName = $property->getName();
            
            if ($this->isEmbbedable($property) && $this->isEmbeddedRequested($propertyName, $requestedEmbedded)) {
                $embedded[$property->getName()] = $this->getEmbeddedContent($resource, $property);
            }
        }

        return $embedded;
    }

    private function isEmbeddedRequested($propertyName, $requestedEmbedded)
    {
        return in_array($propertyName, $requestedEmbedded);
    }

    private function getEmbeddedContent($resource, $property)
    {
        $value = $resource->{'get'.ucfirst($property->getName())}();

        return $this->serializer->toArray($value);
    }

    private function addEmbedParams(ParamFetcher $paramFetcher)
    {
        $embeddedParam = new QueryParam();
        $embeddedParam->name = "embed";
        $embeddedParam->array = true;
        $paramFetcher->addParam($embeddedParam);

        return $paramFetcher->get('embed');
    }

    private function getSelfLink($resource, $reflectionClass)
    {
        return [
            'self' => $this->router->generate(
                'get_'.strtolower($reflectionClass->getShortName()),
                [strtolower($reflectionClass->getShortName()) => $resource->getId()]
            )
        ];
    }

    private function isEmbbedable($property)
    {
        return null !== $this->annotationReader->getPropertyAnnotation($property, Embeddable::class);
    }


    private function getRelationLinks($property, $relationContent)
    {
        if ($relationContent instanceof Collection) {
            $links = [];

            foreach ($relationContent as $relation) {
                $links[] = $this->getRelationLink($property, $relation);
            }

            return $links;
        }

        return $this->getRelationLink($property, $relationContent);;
    }

    protected function getRelationLink($property, $relationContent)
    {
        $meta = $this->entityManager->getClassMetadata(get_class($relationContent));
        $identifier = $meta->getSingleIdentifierFieldName();

        foreach ($this->annotationReader->getPropertyAnnotations($property) as $annotation) {
            if (isset($annotation->targetEntity)) {
                try {
                    $id = $this->entityManager->getUnitOfWork()->getEntityIdentifier($relationContent)[$identifier];

                    return $this->router->generate(
                        'get_'.strtolower($annotation->targetEntity),
                        [strtolower($annotation->targetEntity) => $id]
                    );
                } catch (\Exception $exception) {
                    return null;
                }
            }
        }

        return null;
    }
}