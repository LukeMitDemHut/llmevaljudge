<?php

namespace App\Controller\Api;

use App\Entity\Setting;
use App\Repository\SettingRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/settings', name: 'api_setting_')]
class SettingController extends BaseApiController
{
    private SettingRepository $settingRepository;

    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
        SettingRepository $settingRepository
    ) {
        parent::__construct($entityManager, $normalizer, $validator);
        $this->settingRepository = $settingRepository;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $settings = $this->settingRepository->findAll();

        // Convert to key-value pairs for easier frontend consumption
        $settingsData = [];
        foreach ($settings as $setting) {
            $settingsData[$setting->getName()] = $setting->getValue();
        }

        return $this->jsonResponse($settingsData);
    }

    #[Route('/{name}', name: 'show', methods: ['GET'])]
    public function show(string $name): JsonResponse
    {
        $setting = $this->settingRepository->findOneBy(['name' => $name]);

        if (!$setting) {
            return $this->jsonResponse(['error' => 'Setting not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->jsonResponse([
            'name' => $setting->getName(),
            'value' => $setting->getValue()
        ]);
    }

    #[Route('/{name}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, string $name): JsonResponse
    {
        $data = $this->getRequestData($request);

        if (!isset($data['value'])) {
            return $this->jsonResponse(['error' => 'Value is required'], Response::HTTP_BAD_REQUEST);
        }

        $setting = $this->settingRepository->findOneBy(['name' => $name]);

        if (!$setting) {
            // Create new setting if it doesn't exist
            $setting = new Setting($name, $data['value']);
        } else {
            // Update existing setting
            $setting->setValue($data['value']);
            $setting->setUpdatedAt(new \DateTimeImmutable());
        }

        $validationError = $this->handleValidationErrors($setting);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($setting);

        return $this->jsonResponse([
            'name' => $setting->getName(),
            'value' => $setting->getValue()
        ]);
    }

    #[Route('', name: 'bulk_update', methods: ['POST'])]
    public function bulkUpdate(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);

        $updatedSettings = [];

        foreach ($data as $name => $value) {
            $setting = $this->settingRepository->findOneBy(['name' => $name]);

            if (!$setting) {
                $setting = new Setting($name, $value);
            } else {
                $setting->setValue($value);
                $setting->setUpdatedAt(new \DateTimeImmutable());
            }

            $validationError = $this->handleValidationErrors($setting);
            if ($validationError) {
                return $validationError;
            }

            $this->entityManager->persist($setting);
            $updatedSettings[$name] = $value;
        }

        $this->entityManager->flush();

        return $this->jsonResponse($updatedSettings);
    }
}
