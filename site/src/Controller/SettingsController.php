<?php

namespace App\Controller;

use App\Repository\ModelRepository;
use App\Repository\ProviderRepository;
use App\Repository\SettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_settings')]
    public function index(
        ProviderRepository $providerRepository,
        ModelRepository $modelRepository,
        SettingRepository $settingRepository,
        NormalizerInterface $normalizer
    ): Response {
        // get all providers and models
        $providers = $providerRepository->findAll();
        $models = $modelRepository->findAll();
        $settings = $settingRepository->findAll();

        // Serialize entities to arrays
        $providersData = $normalizer->normalize($providers, null, ['groups' => 'settings']);
        $modelsData = $normalizer->normalize($models, null, ['groups' => 'settings']);

        // Convert settings to key-value pairs
        $settingsData = [];
        foreach ($settings as $setting) {
            $settingsData[$setting->getName()] = $setting->getValue();
        }

        return $this->render('vue/index.html.twig', [
            'title' => 'Settings',
            'component' => 'settings/index',
            'providers' => $providersData,
            'models' => $modelsData,
            'settings' => $settingsData
        ]);
    }
}
