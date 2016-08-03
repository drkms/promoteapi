<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Artist;
use AppBundle\Exception\InvalidFormException;
use AppBundle\Form\Type\ArtistType;
use bigz\halapi\Factory\PaginationFactory;
use bigz\halapi\Factory\RelationFactory;
use bigz\halapi\HALAPIBuilder;
use bigz\halapi\Representation\PaginatedRepresentation;
use FOS\RestBundle\Request\ParamFetcher;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Response;

class ArtistController extends HALController
{
    /**
     * Get artists.
     *
     * @ApiDoc(resource=true,filters={
     *      {"name"="page", "dataType"="integer"},
     *      {"name"="limit", "dataType"="integer"},
     *      {"name"="sorting", "dataType"="array", "pattern"="[field]=(asc|desc)"},
     *      {"name"="filtervalue", "dataType"="array", "pattern"="[field]=value"},
     *      {"name"="filteroperator", "dataType"="array", "pattern"="[field]=(<|>|<=|>=|=|!=)"}
     *  })
     *
     * @param  ParamFetcher $paramFetcher
     * @return array
     */
    public function getArtistsAction(ParamFetcher $paramFetcher)
    {
        return $this->get('bigz_halapi.pagination_factory')->getRepresentation(Artist::class, $paramFetcher);
    }

    /**
     * Get an artist.
     *
     * @Apidoc()
     *
     * @param  Artist $artist
     * @return array
     */
    public function getArtistAction(Artist $artist)
    {
        return $artist;
    }

    /**
     * Create a new Artist.
     *
     * @ApiDoc(
     *  input="AppBundle\Form\Type\ArtistType",
     *  output="AppBundle\Artist",
     *  statusCodes = {
     *     200 = "Returned when successful",
     *     400 = "Returned when the form has errors"
     *   }
     * )
     *
     * @param Request $request
     *
     * @Security("is_granted('create')")
     *
     * @return mixed
     */
    public function postArtistAction(Request $request)
    {
        $artist = new Artist();
        $form = $this->createForm(ArtistType::class, $artist);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $manager = $this->getDoctrine()->getManager();
            $artist->setCreatedBy($this->getUser());
            $manager->persist($artist);
            $manager->flush();

            return ['status' => 'created', 'resource_id' => $artist->getId()];
        }

        return $form;
    }

    /**
     * Update an Artist.
     *
     * @ApiDoc(
     *  input="AppBundle\Form\Type\ArtistType",
     *  output="AppBundle\Artist"
     * )
     *
     * @param Request $request
     *
     * @Security("is_granted('EDIT')")
     *
     * @return mixed
     */
    public function putArtistAction(Request $request, Artist $artist)
    {
        $form = $this->createForm(ArtistType::class, $artist);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $manager = $this->getDoctrine()->getManager();
            $manager->persist($artist);
            $manager->flush();

            return ['status' => 'updated', 'resource_id' => $artist->getId()];
        }

        return $form;
    }

    /**
     * Delete an Artist.
     *
     * @ApiDoc(
     *  input="AppBundle\Artist"
     * )
     * @Security("is_granted('DELETE')")
     * @param                            Artist $artist
     * @return                           array
     */
    public function deleteArtistAction(Artist $artist)
    {
        $id = $artist->getId();
        $manager = $this->getDoctrine()->getManager();

        $manager->remove($artist);
        $manager->flush();

        return ['status' => 'deleted', 'resource_id' => $id];
    }

    public function putArtistPictureAction(Request $request, Artist $artist)
    {
        $tmpFile = tmpfile();
        $tmpFilePath = stream_get_meta_data($tmpFile)['uri'];

        file_put_contents($tmpFilePath, $request->getContent());
        $file = new UploadedFile($tmpFilePath, $tmpFilePath);

        $artist->setImageFile($file);
        $manager = $this->getDoctrine()->getManager();
        $manager->persist($artist);
        $manager->flush();

        return ['status' => 'updated', 'resource_id' => $artist->getId()];
    }

    protected function getRepository()
    {
        return $this->getDoctrine()->getManager()->getRepository('AppBundle:Artist');
    }
}
