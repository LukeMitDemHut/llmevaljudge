<?php

namespace App\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class BaseApiController extends AbstractController
{
    protected EntityManagerInterface $entityManager;
    protected NormalizerInterface $normalizer;
    protected ValidatorInterface $validator;

    public function __construct(
        EntityManagerInterface $entityManager,
        NormalizerInterface $normalizer,
        ValidatorInterface $validator
    ) {
        $this->entityManager = $entityManager;
        $this->normalizer = $normalizer;
        $this->validator = $validator;
    }

    protected function jsonResponse($data, int $status = Response::HTTP_OK, array $groups = ['api']): JsonResponse
    {
        if (is_object($data) || is_array($data)) {
            $normalizedData = $this->normalizer->normalize($data, null, ['groups' => $groups]);
            return new JsonResponse($normalizedData, $status);
        }

        return new JsonResponse($data, $status);
    }

    protected function handleValidationErrors($entity): ?JsonResponse
    {
        $errors = $this->validator->validate($entity);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->jsonResponse([
                'errors' => $errorMessages,
                'message' => 'Validation failed'
            ], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    protected function getRequestData(Request $request): array
    {
        $data = json_decode($request->getContent(), true);
        return $data ?? [];
    }

    protected function createEntity(string $entityClass, array $data): object
    {
        $entity = new $entityClass();
        $this->populateEntity($entity, $data);
        return $entity;
    }

    protected function updateEntity(object $entity, array $data): object
    {
        $this->populateEntity($entity, $data);
        return $entity;
    }

    protected function populateEntity(object $entity, array $data): void
    {
        foreach ($data as $property => $value) {
            $setter = 'set' . ucfirst($property);
            if (method_exists($entity, $setter)) {
                $entity->$setter($value);
            }
        }
    }

    protected function deleteEntity(object $entity): void
    {
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    protected function persistEntity(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }
}
